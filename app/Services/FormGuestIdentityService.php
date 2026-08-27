<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Models\Form;

final class FormGuestIdentityService
{
    public function __construct(
        private readonly FormsModelRegistry $models,
    ) {}

    public function resolve(Form $form, string $identifier, string $email): object
    {
        if ($form->access_mode !== FormAccessMode::GuestIdentifier || $form->identity_type !== 'student_id') {
            $this->reject();
        }

        $studentId = $this->normalize($identifier);
        $studentEmail = $this->normalize($email);
        $student = $this->models->resolveByIdentifier('student', 'student_id', $studentId);
        $emailRecord = $this->models->resolveByIdentifier('student', 'email', $studentEmail);

        if ($student === null || $emailRecord === null || ! $this->sameRecord($student, $emailRecord)) {
            $this->reject();
        }

        return $student;
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->trim()->lower()->toString();
    }

    private function sameRecord(object $left, object $right): bool
    {
        $leftKey = $this->recordKey($left);
        $rightKey = $this->recordKey($right);

        return $leftKey !== null && $leftKey === $rightKey;
    }

    private function recordKey(object $record): ?string
    {
        if (method_exists($record, 'getKey')) {
            $key = $record->getKey();

            return $key === null ? null : (string) $key;
        }

        $key = data_get($record, 'id');

        return $key === null ? null : (string) $key;
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'respondent_identifier' => 'We could not verify that Student ID and registered email combination.',
        ]);
    }
}
