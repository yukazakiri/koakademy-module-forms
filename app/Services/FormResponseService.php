<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Enums\FormResponseStatus;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Models\FormResponseLink;
use Modules\Forms\Models\FormResponseRevision;

final class FormResponseService
{
    public function __construct(
        private readonly FormDefinitionService $definitions,
        private readonly FormsModelRegistry $models,
        private readonly FormsTenantResolver $tenantResolver,
        private readonly FormsAuditService $audit,
    ) {}

    /** @param array<string, mixed> $validated */
    public function submit(Form $form, array $validated, ?object $user = null): FormResponse
    {
        if (! $form->isOpen()) {
            throw ValidationException::withMessages(['form' => 'This form is no longer accepting responses.']);
        }

        $answers = $this->definitions->normalizeAnswers($form, $validated['answers'] ?? []);
        $email = $this->normalizeIdentifier($validated['respondent_email'] ?? null);
        $identifier = $this->normalizeIdentifier($validated['respondent_identifier'] ?? null);
        $userId = data_get($user, 'id');

        return DB::transaction(function () use ($form, $answers, $email, $identifier, $userId, $user): FormResponse {
            $response = $this->findExisting($form, $userId, $email, $identifier);
            $allowResubmit = (bool) data_get($form->settings, 'allow_resubmit', false);

            if ($response instanceof FormResponse && ! $allowResubmit) {
                throw ValidationException::withMessages(['form' => 'You have already submitted a response to this form.']);
            }

            $revision = $response instanceof FormResponse ? $response->latest_revision + 1 : 1;
            if (! $response instanceof FormResponse) {
                $response = FormResponse::query()->create([
                    'form_id' => $form->getKey(),
                    'tenant_key' => $this->tenantResolver->key() === null ? null : (string) $this->tenantResolver->key(),
                    'respondent_user_id' => $userId === null ? null : (string) $userId,
                    'respondent_email' => $email,
                    'respondent_identifier' => $identifier,
                    'respondent_email_hash' => $email === null ? null : hash('sha256', $email),
                    'respondent_identifier_hash' => $identifier === null ? null : hash('sha256', $identifier),
                    'status' => FormResponseStatus::Submitted,
                    'latest_revision' => 0,
                    'submitted_at' => now(),
                ]);
            } else {
                $response->update([
                    'status' => FormResponseStatus::Submitted,
                    'latest_revision' => $revision,
                    'submitted_at' => now(),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'applied_by' => null,
                    'applied_at' => null,
                ]);
            }

            $response->revisions()->create([
                'revision' => $revision,
                'answer_payload' => $this->encrypt($answers),
                'field_snapshot' => $this->encode($this->definitions->snapshot($form)),
                'created_by' => $userId === null ? null : (string) $userId,
                'created_at' => now(),
            ]);

            $response->update(['latest_revision' => $revision]);
            $this->createLinks($form, $response, $user, $email, $identifier);
            $this->audit->record($form, 'response_submitted', $response, ['revision' => $revision]);

            return $response->fresh('revisions', 'links');
        });
    }

    /** @return array<string, mixed> */
    public function latestAnswers(FormResponse $response): array
    {
        $revision = $response->revisions()->first();
        if (! $revision instanceof FormResponseRevision) {
            return [];
        }

        try {
            $decoded = json_decode(Crypt::decryptString($revision->answer_payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    private function findExisting(Form $form, ?string $userId, ?string $email, ?string $identifier): ?FormResponse
    {
        $query = $form->responses();

        if ($userId !== null) {
            return $query->where('respondent_user_id', $userId)->latest()->first();
        }

        if ($email !== null) {
            return $query->where('respondent_email_hash', hash('sha256', $email))->latest()->first();
        }

        if ($identifier !== null) {
            return $query->where('respondent_identifier_hash', hash('sha256', $identifier))->latest()->first();
        }

        return null;
    }

    private function createLinks(Form $form, FormResponse $response, ?object $user, ?string $email, ?string $identifier): void
    {
        $modelKeys = $form->fields
            ->map(fn ($field): ?string => is_array($field->mapping) ? ($field->mapping['model'] ?? null) : null)
            ->filter()
            ->unique()
            ->values();

        foreach ($modelKeys as $modelKey) {
            $record = $user !== null
                ? $this->models->resolveForUser($modelKey, $user)
                : (($identifier !== null && $form->identity_type !== null)
                    ? $this->models->resolveByIdentifier($modelKey, $form->identity_type, $identifier)
                    : ($email !== null && $form->identity_type !== null
                        ? $this->models->resolveByIdentifier($modelKey, $form->identity_type, $email)
                        : null));

            FormResponseLink::query()->updateOrCreate(
                ['form_response_id' => $response->getKey(), 'model_key' => $modelKey],
                [
                    'model_type' => $record === null ? null : $record::class,
                    'model_id' => $record === null ? null : (string) $record->getKey(),
                    'match_method' => $user !== null ? 'authenticated_user' : ($record === null ? null : 'guest_identifier'),
                    'status' => $record === null ? 'unmatched' : 'pending',
                    'error_message' => $record === null ? 'No approved record matched the submitted identity.' : null,
                ],
            );
        }
    }

    private function normalizeIdentifier(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = Str::of((string) $value)->trim()->lower()->toString();

        return $normalized === '' ? null : $normalized;
    }

    /** @param array<string, mixed> $payload */
    private function encrypt(array $payload): string
    {
        return Crypt::encryptString($this->encode($payload));
    }

    private function encode(mixed $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            report($exception);

            throw ValidationException::withMessages(['answers' => 'The response could not be stored.']);
        }
    }
}
