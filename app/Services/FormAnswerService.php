<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use JsonException;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Models\FormResponseRevision;

final class FormAnswerService
{
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

    /** @param array<string, mixed> $payload */
    public function encrypt(array $payload): string
    {
        return Crypt::encryptString($this->encode($payload));
    }

    public function encode(mixed $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            report($exception);

            throw ValidationException::withMessages(['answers' => 'The response could not be stored.']);
        }
    }
}
