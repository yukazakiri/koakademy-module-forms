<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Facades\DB;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Models\FormResponseLink;

final class FormMappingService
{
    public function __construct(
        private readonly FormsModelRegistry $models,
        private readonly FormResponseService $responses,
        private readonly FormsAuditService $audit,
    ) {}

    public function apply(FormResponse $response, bool $overwrite = false, ?object $actor = null): FormResponse
    {
        return DB::transaction(function () use ($response, $overwrite, $actor): FormResponse {
            $answers = $this->responses->latestAnswers($response);
            $applied = 0;

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

                $value = $answers[$field->field_key];
                if (! $overwrite && $this->isFilled($this->models->read($record, $mapping['path']))) {
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
            ]);

            return $response->fresh('form', 'revisions', 'links');
        });
    }

    private function isFilled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }
}
