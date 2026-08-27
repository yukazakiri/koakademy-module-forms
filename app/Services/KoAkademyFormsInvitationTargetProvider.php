<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use App\Models\Student;
use Illuminate\Support\Facades\Validator;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormInvitation;

final class KoAkademyFormsInvitationTargetProvider implements FormsInvitationTargetProvider
{
    public function __construct(
        private readonly FormsModelRegistry $models,
        private readonly FormsTenantResolver $tenantResolver,
    ) {}

    public function candidates(Form $form): iterable
    {
        $fields = $form->fields
            ->filter(fn ($field): bool => is_array($field->mapping) && ($field->mapping['model'] ?? null) === 'student')
            ->values();

        if ($fields->isEmpty()) {
            return [];
        }

        $query = Student::query()
            ->with(['studentContactsInfo', 'studentParentInfo', 'studentEducationInfo', 'personalInfo'])
            ->whereNotNull('email')
            ->when($this->tenantResolver->key() !== null, fn ($query) => $query->where('school_id', $this->tenantResolver->key()));

        foreach ($query->cursor() as $student) {
            if (! $this->hasMissingFields($student, $fields->all())) {
                continue;
            }

            $email = trim((string) $student->email);
            if (! Validator::make(['email' => $email], ['email' => ['required', 'email']])->passes()) {
                continue;
            }

            yield [
                'model_key' => 'student',
                'model_type' => $student::class,
                'model_id' => $student->getKey(),
                'email' => $email,
            ];
        }
    }

    public function resolve(FormInvitation $invitation): ?object
    {
        if ($invitation->model_key !== 'student') {
            return null;
        }

        return Student::query()
            ->with(['studentContactsInfo', 'studentParentInfo', 'studentEducationInfo', 'personalInfo'])
            ->when($this->tenantResolver->key() !== null, fn ($query) => $query->where('school_id', $this->tenantResolver->key()))
            ->find($invitation->model_id);
    }

    /** @param list<object> $fields */
    private function hasMissingFields(object $student, array $fields): bool
    {
        foreach ($fields as $field) {
            $mapping = $field->mapping;
            if (! is_array($mapping) || ! is_string($mapping['path'] ?? null)) {
                continue;
            }

            if ($this->isBlank($this->models->read($student, $mapping['path']))) {
                return true;
            }
        }

        return false;
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }
}
