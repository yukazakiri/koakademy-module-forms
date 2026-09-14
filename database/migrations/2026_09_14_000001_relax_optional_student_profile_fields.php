<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormTemplate;

return new class extends Migration
{
    /** @var list<string> */
    private const OPTIONAL_FIELDS = [
        'suffix',
        'middle_name',
        'religion',
        'father_name',
        'father_occupation',
        'father_contact',
        'father_email',
        'mother_name',
        'mother_occupation',
        'mother_contact',
        'mother_email',
        'guardian_name',
        'guardian_relationship',
        'guardian_contact',
        'guardian_email',
        'family_address',
        'is_solo_parent_dependent',
        'elementary_school',
        'elementary_graduate_year',
        'elementary_school_address',
        'junior_high_school_name',
        'junior_high_graduation_year',
        'junior_high_school_address',
        'senior_high_name',
        'senior_high_graduate_year',
        'senior_high_address',
        'college_school',
        'college_course',
        'college_year_graduated',
        'vocational_school',
        'vocational_course',
        'vocational_year_graduated',
        'scholarship_type',
        'scholarship_details',
        'employment_status',
        'employer_name',
        'job_position',
        'employment_date',
        'employed_by_institution',
    ];

    public function up(): void
    {
        Form::query()
            ->with('fields')
            ->where(function ($query): void {
                $query
                    ->where('settings->template_key', 'student_profile_completion')
                    ->orWhere(function ($query): void {
                        $query
                            ->where('title', 'Student Profile Completion')
                            ->whereIn('access_mode', ['invitation', 'guest_identifier'])
                            ->where('settings->mapping_mode', 'auto_fill_empty');
                    });
            })
            ->chunkById(100, function (Collection $forms): void {
                foreach ($forms as $form) {
                    $form->fields()
                        ->whereIn('field_key', self::OPTIONAL_FIELDS)
                        ->update(['required' => false]);
                }
            }, 'id');

        FormTemplate::query()
            ->select(['id', 'definition'])
            ->where(function ($query): void {
                $query
                    ->where('definition->settings->template_key', 'student_profile_completion')
                    ->orWhere(function ($query): void {
                        $query
                            ->where('name', 'Student Profile Completion')
                            ->where('model_key', 'student');
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function (Collection $templates): void {
                foreach ($templates as $template) {
                    $definition = is_array($template->definition) ? $template->definition : [];
                    $fields = $definition['fields'] ?? [];
                    if (! is_array($fields)) {
                        continue;
                    }

                    $changed = false;
                    foreach ($fields as $index => $field) {
                        if (! is_array($field)) {
                            continue;
                        }

                        $key = (string) ($field['field_key'] ?? '');
                        if (in_array($key, self::OPTIONAL_FIELDS, true) && ($field['required'] ?? false) !== false) {
                            $fields[$index]['required'] = false;
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        $definition['fields'] = $fields;
                        $template->update(['definition' => $definition]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        throw new RuntimeException('This forward-only data migration cannot safely reverse field requirement changes.');
    }
};
