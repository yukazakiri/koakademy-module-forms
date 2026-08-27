<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use App\Models\Student;
use App\Models\StudentContact;
use App\Models\StudentEducationInfo;
use App\Models\StudentParentsInfo;
use App\Models\StudentsPersonalInfo;
use App\Support\RegistrarStudentProfileWorkbook;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Modules\Forms\Contracts\FormsLockableModelRegistry;
use Modules\Forms\Contracts\FormsModelRegistry;

final class KoAkademyFormsModelRegistry implements FormsLockableModelRegistry, FormsModelRegistry
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

        $workbook = app(RegistrarStudentProfileWorkbook::class);

        return array_map(
            fn (array $field): array => [
                'key' => (string) $field['key'],
                'label' => (string) $field['label'],
                'type' => (string) $field['type'],
                'options' => $field['options'] ?? [],
                'read_paths' => $field['read'] ?? [],
                'write_paths' => $this->availableWritePaths($field),
                'group' => $field['group'] ?? 'Student Profile',
                'max' => $field['max'] ?? null,
                'suggestible' => in_array((string) $field['key'], [
                    'birthplace',
                    'region_of_origin',
                    'province_of_origin',
                    'city_of_origin',
                    'religion',
                    'nationality',
                    'civil_status',
                ], true),
            ],
            $workbook->fields(),
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
            'details' => Arr::get(is_array($record->profile_details ?? null) ? $record->profile_details : [], $attribute),
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

        if ($scope === 'details') {
            $details = is_array($record->profile_details ?? null) ? $record->profile_details : [];
            Arr::set($details, $attribute, $value);
            $record->setAttribute('profile_details', $details);

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
            if ($record instanceof Student && $record->isDirty('birth_date') && $record->birth_date !== null) {
                $record->setAttribute('age', Carbon::parse((string) $record->birth_date)->age);
            }

            $record->save();
        }
    }

    public function lock(object $record): object
    {
        if (! $record instanceof Model) {
            return $record;
        }

        return $record->newQuery()
            ->with(['studentContactsInfo', 'studentParentInfo', 'studentEducationInfo', 'personalInfo'])
            ->whereKey($record->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** @param array<string, mixed> $field
     *  @return list<string> */
    private function availableWritePaths(array $field): array
    {
        $paths = collect($field['write'] ?? [])
            ->filter(fn (mixed $path): bool => is_string($path))
            ->filter(fn (string $path): bool => $this->isPhysicalPath($path))
            ->values()
            ->all();

        return $paths !== [] ? $paths : ['details.'.(string) $field['key']];
    }

    private function isPhysicalPath(string $path): bool
    {
        [$scope, $attribute] = array_pad(explode('.', $path, 2), 2, '');
        $table = match ($scope) {
            'student' => 'students',
            'contact' => 'student_contacts',
            'parent' => 'student_parents_info',
            'education' => 'student_education_info',
            'personal' => 'students_personal_info',
            default => null,
        };

        return $table !== null && Schema::hasColumn($table, $attribute);
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
