<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use App\Models\Student;
use App\Models\StudentContact;
use App\Models\StudentEducationInfo;
use App\Models\StudentParentsInfo;
use App\Models\StudentsPersonalInfo;
use App\Support\RegistrarStudentProfileWorkbook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Modules\Forms\Contracts\FormsModelRegistry;

final class KoAkademyFormsModelRegistry implements FormsModelRegistry
{
    public function models(): array
    {
        return [
            ['key' => 'student', 'label' => 'Student'],
        ];
    }

    public function fields(string $modelKey): array
    {
        if ($modelKey !== 'student' || ! class_exists(RegistrarStudentProfileWorkbook::class)) {
            return [];
        }

        return array_map(
            static fn (array $field): array => [
                'key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'options' => $field['options'] ?? [],
                'read_paths' => $field['read'] ?? [],
                'write_paths' => $field['write'] ?? [],
            ],
            app(RegistrarStudentProfileWorkbook::class)->fields(),
        );
    }

    public function resolveByIdentifier(string $modelKey, string $identifierType, string $identifier): ?object
    {
        if ($modelKey !== 'student' || ! class_exists(Student::class)) {
            return null;
        }

        $query = Student::query();

        if ($identifierType === 'student_id') {
            return $query->where('student_id', $identifier)->first();
        }

        if ($identifierType === 'email') {
            return $query->where('email', $identifier)->first();
        }

        return null;
    }

    public function resolveForUser(string $modelKey, mixed $user): ?object
    {
        if ($modelKey !== 'student' || ! $user || ! class_exists(Student::class)) {
            return null;
        }

        $userId = data_get($user, 'id');
        if ($userId === null) {
            return null;
        }

        return Student::query()->where('user_id', $userId)->first();
    }

    public function resolveById(string $modelKey, string|int $id): ?object
    {
        if ($modelKey !== 'student' || ! class_exists(Student::class)) {
            return null;
        }

        return Student::query()->find($id);
    }

    public function read(object $record, string $path): mixed
    {
        [$scope, $attribute] = array_pad(explode('.', $path, 2), 2, '');
        $value = match ($scope) {
            'student' => data_get($record, $attribute),
            'contact' => data_get($record->studentContactsInfo, $attribute),
            'parent' => data_get($record->studentParentInfo, $attribute),
            'education' => data_get($record->studentEducationInfo, $attribute),
            'personal' => data_get($record->personalInfo, $attribute),
            'contacts' => Arr::get(is_array($record->contacts ?? null) ? $record->contacts : [], $attribute),
            default => null,
        };

        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    public function write(object $record, string $path, mixed $value): void
    {
        [$scope, $attribute] = array_pad(explode('.', $path, 2), 2, '');

        if ($scope === 'student') {
            $record->setAttribute($attribute, $value);

            return;
        }

        if ($scope === 'contacts') {
            $contacts = is_array($record->contacts ?? null) ? $record->contacts : [];
            Arr::set($contacts, $attribute, $value);
            $record->setAttribute('contacts', $contacts);

            return;
        }

        $related = match ($scope) {
            'contact' => $this->related($record, 'studentContactsInfo', StudentContact::class, 'student_contact_id'),
            'parent' => $this->related($record, 'studentParentInfo', StudentParentsInfo::class, 'student_parent_info'),
            'education' => $this->related($record, 'studentEducationInfo', StudentEducationInfo::class, 'student_education_id'),
            'personal' => $this->related($record, 'personalInfo', StudentsPersonalInfo::class, 'student_personal_id'),
            default => null,
        };

        if ($related instanceof Model) {
            $related->setAttribute($attribute, $value);
            $related->save();
        }
    }

    public function persist(object $record): void
    {
        if ($record instanceof Model) {
            $record->save();
        }
    }

    private function related(Model $record, string $relation, string $class, string $foreignKey): ?Model
    {
        $related = $record->getRelationValue($relation);
        if ($related instanceof Model) {
            return $related;
        }

        $related = new $class;
        $related->save();
        $record->setAttribute($foreignKey, $related->getKey());
        $record->setRelation($relation, $related);

        return $related;
    }
}
