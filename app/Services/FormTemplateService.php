<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Str;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormTemplate;

final class FormTemplateService
{
    public function __construct(
        private readonly FormsModelRegistry $models,
        private readonly FormsTenantResolver $tenantResolver,
    ) {}

    /** @return list<array<string, mixed>> */
    public function catalog(): array
    {
        $templates = collect([
            $this->studentProfileTemplate(),
        ])->filter()->map(function (array $definition): array {
            return [
                'key' => (string) data_get($definition, 'settings.template_key'),
                'name' => (string) ($definition['title'] ?? 'Untitled template'),
                'description' => $definition['description'] ?? null,
                'model_key' => $definition['model_key'] ?? null,
                'field_count' => count($definition['fields'] ?? []),
                'system' => true,
            ];
        });

        $tenantKey = $this->tenantResolver->key();
        $custom = FormTemplate::query()
            ->forTenant($tenantKey)
            ->latest()
            ->get()
            ->map(fn (FormTemplate $template): array => [
                'id' => $template->getKey(),
                'key' => 'template:'.$template->getKey(),
                'name' => $template->name,
                'description' => $template->description,
                'model_key' => $template->model_key,
                'field_count' => count(data_get($template->definition, 'fields', [])),
                'system' => false,
            ]);

        return $templates->merge($custom)->values()->all();
    }

    /** @return array<string, mixed>|null */
    public function definition(string $key): ?array
    {
        if ($key === 'student_profile_completion') {
            return $this->studentProfileTemplate();
        }

        if (! Str::startsWith($key, 'template:')) {
            return null;
        }

        $template = FormTemplate::query()
            ->forTenant($this->tenantResolver->key())
            ->find(Str::after($key, 'template:'));

        return $template?->definition;
    }

    public function templateId(string $key): ?string
    {
        if (! Str::startsWith($key, 'template:')) {
            return null;
        }

        return FormTemplate::query()
            ->forTenant($this->tenantResolver->key())
            ->whereKey(Str::after($key, 'template:'))
            ->value('id');
    }

    public function createFromForm(Form $form, string $name, mixed $actor): FormTemplate
    {
        $definition = [
            'title' => $form->title,
            'description' => $form->description,
            'access_mode' => $form->access_mode->value,
            'identity_type' => $form->identity_type,
            'settings' => $form->settings ?? [],
            'model_key' => collect($form->fields)->pluck('mapping')->filter()->map(fn (array $mapping): ?string => $mapping['model'] ?? null)->filter()->first(),
            'fields' => $form->fields->map(fn ($field): array => [
                'field_key' => $field->field_key,
                'label' => $field->label,
                'type' => $field->type,
                'description' => $field->description,
                'section' => $field->section,
                'required' => $field->required,
                'options' => $field->options ?? [],
                'validation' => $field->validation ?? [],
                'presentation' => $field->presentation ?? [],
                'behavior' => $field->behavior ?? [],
                'visibility' => $field->visibility,
                'mapping' => $field->mapping,
                'is_sensitive' => $field->is_sensitive,
            ])->values()->all(),
        ];

        return FormTemplate::query()->create([
            'tenant_key' => $this->tenantResolver->key() === null ? null : (string) $this->tenantResolver->key(),
            'created_by' => data_get($actor, 'id'),
            'name' => $name,
            'description' => $form->description,
            'model_key' => $definition['model_key'],
            'definition' => $definition,
        ]);
    }

    public function duplicate(FormTemplate $template, string $name, mixed $actor): FormTemplate
    {
        return FormTemplate::query()->create([
            'tenant_key' => $this->tenantResolver->key() === null ? null : (string) $this->tenantResolver->key(),
            'created_by' => data_get($actor, 'id'),
            'name' => $name,
            'description' => $template->description,
            'model_key' => $template->model_key,
            'definition' => $template->definition,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function studentProfileTemplate(): ?array
    {
        $fields = $this->models->fields('student');
        if ($fields === []) {
            return null;
        }

        return [
            'title' => 'Student Profile Completion',
            'description' => 'Complete the missing information in your student profile. Your answers update only blank fields on your record.',
            'access_mode' => 'invitation',
            'identity_type' => null,
            'model_key' => 'student',
            'settings' => [
                'template_key' => 'student_profile_completion',
                'mapping_mode' => 'auto_fill_empty',
                'invitation_expiry_days' => 30,
                'allow_resubmit' => false,
                'allow_unverified_guest_response' => true,
                'missing_only' => true,
                'confirmation_message' => 'Your profile information has been received and the missing fields were updated.',
            ],
            'fields' => collect($fields)->map(fn (array $field): array => $this->profileField($field))->values()->all(),
        ];
    }

    /** @param array<string, mixed> $field
     *  @return array<string, mixed> */
    private function profileField(array $field): array
    {
        $key = (string) ($field['key'] ?? Str::snake((string) ($field['label'] ?? 'field')));
        $type = match ($field['type'] ?? 'string') {
            'choice' => 'select',
            'boolean' => 'yes_no',
            'email' => 'email',
            'number' => 'number',
            'year' => 'year',
            default => in_array($key, ['phone', 'father_contact', 'mother_contact', 'guardian_contact', 'emergency_contact_phone'], true)
                ? 'phone'
                : 'text',
        };
        $options = $field['options'] ?? [];
        $recordSuggestions = in_array($key, [
            'birthplace',
            'region_of_origin',
            'province_of_origin',
            'city_of_origin',
            'religion',
            'nationality',
            'civil_status',
        ], true);
        $control = match (true) {
            $recordSuggestions => 'combobox',
            $type === 'yes_no' || ($type === 'select' && count($options) <= 4) => 'radio_cards',
            $type === 'select' => 'select',
            default => 'input',
        };

        $fieldData = [
            'field_key' => $key,
            'label' => (string) ($field['label'] ?? Str::headline($key)),
            'type' => $type,
            'description' => null,
            'section' => (string) ($field['group'] ?? 'Profile'),
            'required' => $this->isRequiredProfileField($key),
            'options' => $options,
            'validation' => [],
            'presentation' => [
                'control' => $control,
                'input_mode' => $type === 'phone' ? 'tel' : ($type === 'number' || $type === 'year' ? 'numeric' : 'text'),
                'suggestion_source' => $recordSuggestions ? 'record_values' : 'none',
                'suggestion_limit' => 10,
                'unit' => match ($key) {
                    'height' => 'cm',
                    'weight' => 'kg',
                    default => null,
                },
            ],
            'behavior' => [
                'missing_only' => true,
            ],
            'visibility' => $this->visibilityForProfileField($key),
            'mapping' => isset($field['write_paths'][0]) ? ['model' => 'student', 'path' => $field['write_paths'][0]] : null,
            'is_sensitive' => true,
        ];

        if (isset($field['max']) && is_numeric($field['max'])) {
            $fieldData['validation']['max'] = (int) $field['max'];
        }

        if ($type === 'number') {
            $fieldData['validation']['min'] = 0;
        }

        if ($type === 'year') {
            $fieldData['validation']['min'] = 1900;
            $fieldData['validation']['max'] = now()->year;
        }

        return $fieldData;
    }

    private function isRequiredProfileField(string $key): bool
    {
        return ! in_array($key, [
            'suffix',
            'ethnicity',
            'indigenous_group',
            'pwd_type',
            'family_income_bracket',
            'father_income_bracket',
            'mother_income_bracket',
            'facebook_contact',
            'twitter',
            'instagram',
            'linkedin',
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
        ], true);
    }

    /** @return array<string, string>|null */
    private function visibilityForProfileField(string $key): ?array
    {
        return match ($key) {
            'indigenous_group' => ['field' => 'is_indigenous_person', 'operator' => 'equals', 'value' => 'yes'],
            'pwd_type' => ['field' => 'is_pwd', 'operator' => 'equals', 'value' => 'yes'],
            default => null,
        };
    }
}
