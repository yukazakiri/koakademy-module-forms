<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Forms\Contracts\FormsLockableModelRegistry;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Models\FormResponseLink;

final class FormMappingService
{
    public function __construct(
        private readonly FormsModelRegistry $models,
        private readonly FormAnswerService $answers,
        private readonly FormsAuditService $audit,
    ) {}

    public function apply(FormResponse $response, bool $overwrite = false, ?object $actor = null): FormResponse
    {
        return DB::transaction(function () use ($response, $overwrite, $actor): FormResponse {
            if ($response->links->contains(fn (FormResponseLink $link): bool => $link->model_id === null || $link->status === 'unmatched')) {
                throw ValidationException::withMessages([
                    'response' => 'This response needs manual record matching before it can be applied.',
                ]);
            }

            $answers = $this->answers->latestAnswers($response);
            $applied = 0;
            $skipped = [];

            foreach ($response->form->fields as $field) {
                $mapping = $field->mapping;
                if (! is_array($mapping) || ! is_string($mapping['model'] ?? null) || ! is_string($mapping['path'] ?? null)) {
                    continue;
                }

                $link = $response->links->firstWhere('model_key', $mapping['model']);
                if (! $link instanceof FormResponseLink || $link->model_id === null) {
                    continue;
                }

                $record = $this->models->resolveById($mapping['model'], $link->model_id);
                if ($record === null || ! array_key_exists($field->field_key, $answers)) {
                    continue;
                }

                if ($this->models instanceof FormsLockableModelRegistry) {
                    $record = $this->models->lock($record);
                }

                $value = $answers[$field->field_key];
                if (! $overwrite && $this->isFilled($this->models->read($record, $mapping['path']))) {
                    $skipped[] = $field->field_key;

                    continue;
                }

                $this->models->write($record, $mapping['path'], $value);
                $this->models->persist($record);
                $applied++;
            }

            foreach ($response->links as $link) {
                $link->update([
                    'status' => 'applied',
                    'applied_by' => data_get($actor, 'id'),
                    'applied_at' => now(),
                    'error_message' => null,
                ]);
            }

            $response->update([
                'status' => 'applied',
                'applied_by' => data_get($actor, 'id'),
                'applied_at' => now(),
            ]);
            $this->audit->record($response->form, 'response_mapping_applied', $response, [
                'overwrite' => $overwrite,
                'fields_applied' => $applied,
                'fields_skipped' => $skipped,
            ]);

            return $response->fresh('form', 'revisions', 'links');
        });
    }

    private function isFilled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }
}
