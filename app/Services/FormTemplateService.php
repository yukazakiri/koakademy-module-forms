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
    /** @var list<string> */
    private const EXCLUDED_PROFILE_FIELDS = [
        'facebook_contact',
        'twitter',
        'instagram',
        'linkedin',
        'father_income_bracket',
        'mother_income_bracket',
    ];

    public const array CIVIL_STATUS_OPTIONS = [
        'single' => 'Single',
        'married' => 'Married',
        'widowed' => 'Widowed',
        'separated' => 'Separated',
        'annulled' => 'Annulled',
    ];

    public const array GENDER_OPTIONS = [
        'male' => 'Male',
        'female' => 'Female',
        'other' => 'Other',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    public const array NATIONALITY_OPTIONS = [
        'filipino' => 'Filipino',
        'american' => 'American',
        'australian' => 'Australian',
        'british' => 'British',
        'canadian' => 'Canadian',
        'chinese' => 'Chinese',
        'indian' => 'Indian',
        'indonesian' => 'Indonesian',
        'japanese' => 'Japanese',
        'korean' => 'Korean',
        'malaysian' => 'Malaysian',
        'singaporean' => 'Singaporean',
        'thai' => 'Thai',
        'vietnamese' => 'Vietnamese',
        'other' => 'Other',
    ];

    public const array REGION_OPTIONS = [
        'NCR' => 'National Capital Region (NCR)',
        'CAR' => 'Cordillera Administrative Region (CAR)',
        'Region I' => 'Region I - Ilocos Region',
        'Region II' => 'Region II - Cagayan Valley',
        'Region III' => 'Region III - Central Luzon',
        'Region IV-A' => 'Region IV-A - CALABARZON',
        'Region IV-B' => 'Region IV-B - MIMAROPA',
        'Region V' => 'Region V - Bicol Region',
        'Region VI' => 'Region VI - Western Visayas',
        'Region VII' => 'Region VII - Central Visayas',
        'Region VIII' => 'Region VIII - Eastern Visayas',
        'Region IX' => 'Region IX - Zamboanga Peninsula',
        'Region X' => 'Region X - Northern Mindanao',
        'Region XI' => 'Region XI - Davao Region',
        'Region XII' => 'Region XII - SOCCSKSARGEN',
        'Region XIII' => 'Region XIII - Caraga',
        'BARMM' => 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)',
    ];

    public const array RELATIONSHIP_OPTIONS = [
        'mother' => 'Mother',
        'father' => 'Father',
        'sibling' => 'Sibling',
        'spouse' => 'Spouse',
        'grandparent' => 'Grandparent',
        'aunt' => 'Aunt',
        'uncle' => 'Uncle',
        'cousin' => 'Cousin',
        'legal_guardian' => 'Legal Guardian',
        'other' => 'Other',
    ];

    public const array RELIGION_OPTIONS = [
        'roman_catholic' => 'Roman Catholic',
        'islam' => 'Islam',
        'iglesia_ni_cristo' => 'Iglesia ni Cristo',
        'born_again_christian' => 'Born Again Christian',
        'seventh_day_adventist' => 'Seventh-day Adventist',
        'protestant' => 'Protestant',
        'evangelical_christian' => 'Evangelical Christian',
        'buddhist' => 'Buddhist',
        'hindu' => 'Hindu',
        'none' => 'None',
        'prefer_not_to_say' => 'Prefer not to say',
        'other' => 'Other',
    ];

    public const array PWD_TYPE_OPTIONS = [
        'visual' => 'Visual Disability',
        'hearing' => 'Hearing Disability',
        'speech_and_language' => 'Speech and Language Impairment',
        'physical_orthopedic' => 'Physical / Orthopedic Disability',
        'intellectual' => 'Intellectual Disability',
        'learning' => 'Learning Disability',
        'psychosocial_mental' => 'Psychosocial / Mental Health Disability',
        'chronic_illness' => 'Disability Due to Chronic Illness',
        'multiple' => 'Multiple Disabilities',
        'other' => 'Other',
    ];

    /** @var array<string, string> */
    private const PROFILE_DESCRIPTIONS = [
        'first_name' => 'Use your official first name as it appears in your school records.',
        'middle_name' => 'Enter your complete middle name, or leave this blank if you do not have one.',
        'last_name' => 'Use your official family name as it appears in your school records.',
        'suffix' => 'Add a suffix such as Jr. or III only if it is part of your legal name.',
        'gender' => 'Choose the option that best represents your student record or preference.',
        'birth_date' => 'Enter the date shown on your birth certificate or school record.',
        'email' => 'Use an email address that you check regularly for school communication.',
        'phone' => 'Enter a phone number where the school can reach you. Include the country code when possible.',
        'civil_status' => 'Choose your current civil status.',
        'nationality' => 'Enter your citizenship or nationality, for example Filipino.',
        'religion' => 'Enter the religion you identify with, if you wish to provide it.',
        'current_address' => 'Include your house or unit, street, barangay, city, and province.',
        'permanent_address' => 'Enter your permanent home address if it is different from your current address.',
        'birthplace' => 'Enter the city or municipality and province where you were born.',
        'weight' => 'Enter your current weight in kilograms.',
        'height' => 'Enter your current height in centimeters.',
        'ethnicity' => 'Enter the ethnic group you identify with, if applicable.',
        'region_of_origin' => 'Enter the region where your family originates.',
        'province_of_origin' => 'Enter the province where your family originates.',
        'city_of_origin' => 'Enter the city or municipality where your family originates.',
        'is_indigenous_person' => 'Choose Yes only if you identify as an Indigenous person.',
        'indigenous_group' => 'If applicable, enter the name of your Indigenous group.',
        'is_pwd' => 'Choose Yes if you are a person with disability.',
        'pwd_type' => 'If applicable, choose the disability category used in the school report.',
        'is_solo_parent' => 'Choose Yes if you are a solo parent.',
        'is_solo_parent_dependent' => 'Choose Yes if you are a dependent of a solo parent.',
        'is_senior_citizen' => 'Choose Yes if you are a senior citizen.',
        'is_magna_carta' => 'Choose Yes if you are a Magna Carta beneficiary.',
        'is_underprivileged' => 'Choose Yes if you are classified as underprivileged.',
        'is_first_generation' => 'Choose Yes if you are the first person in your family to attend college.',
        'family_income_bracket' => 'Optional: choose the annual income range that best represents your parents\' or household income.',
        'father_income_bracket' => 'Legacy parent-specific income range retained for existing responses.',
        'mother_income_bracket' => 'Legacy parent-specific income range retained for existing responses.',
        'emergency_contact_name' => 'Enter the name of someone the school may contact in an emergency.',
        'emergency_contact_phone' => 'Enter the emergency contact’s active phone number.',
        'emergency_contact_address' => 'Enter the emergency contact’s complete address.',
        'emergency_contact_relationship' => 'Describe how this person is related to you.',
        'father_name' => 'Enter your father’s complete name as it should appear in school records.',
        'father_occupation' => 'Enter your father’s current occupation, if applicable.',
        'father_contact' => 'Enter a phone number where your father can be reached.',
        'father_email' => 'Enter your father’s active email address, if available.',
        'mother_name' => 'Enter your mother’s complete name as it should appear in school records.',
        'mother_occupation' => 'Enter your mother’s current occupation, if applicable.',
        'mother_contact' => 'Enter a phone number where your mother can be reached.',
        'mother_email' => 'Enter your mother’s active email address, if available.',
        'guardian_name' => 'Optional: enter a guardian if different from the emergency contact already provided.',
        'guardian_relationship' => 'Describe how your guardian is related to you.',
        'guardian_contact' => 'Enter a phone number where your guardian can be reached.',
        'guardian_email' => 'Enter your guardian’s active email address, if available.',
        'family_address' => 'Enter the complete address where your family lives.',
        'elementary_school' => 'Enter the full name of the elementary school you attended.',
        'elementary_graduate_year' => 'Enter the year you graduated from elementary school.',
        'elementary_school_address' => 'Enter the complete address of your elementary school.',
        'junior_high_school_name' => 'Enter the full name of the junior high school you attended.',
        'junior_high_graduation_year' => 'Enter the year you graduated from junior high school.',
        'junior_high_school_address' => 'Enter the complete address of your junior high school.',
        'senior_high_name' => 'Enter the full name of the senior high school you attended.',
        'senior_high_graduate_year' => 'Enter the year you graduated from senior high school.',
        'senior_high_address' => 'Enter the complete address of your senior high school.',
        'scholarship_type' => 'Choose the scholarship or financial assistance that applies to you.',
        'scholarship_details' => 'Add the scholarship name or details that will help the registrar verify it.',
        'employment_status' => 'Choose the option that best describes your current work or study status.',
        'employer_name' => 'Enter the name of your current employer, if applicable.',
        'job_position' => 'Enter your current job title or position, if applicable.',
        'employment_date' => 'Enter the date you started your current employment.',
        'employed_by_institution' => 'Choose Yes if you are employed by the school or institution.',
    ];

    /** @var array<string, string> */
    private const PROFILE_PLACEHOLDERS = [
        'first_name' => 'e.g. Juan',
        'middle_name' => 'e.g. Santos',
        'last_name' => 'e.g. Dela Cruz',
        'suffix' => 'e.g. Jr.',
        'email' => 'name@example.com',
        'phone' => '+63 912 345 6789',
        'nationality' => 'Select nationality or citizenship',
        'current_address' => 'House no., street, barangay, city, province',
        'permanent_address' => 'House no., street, barangay, city, province',
        'birthplace' => 'e.g. Quezon City, Metro Manila',
        'civil_status' => 'Select civil status',
        'religion' => 'Select religion',
        'weight' => 'e.g. 60',
        'height' => 'e.g. 170',
        'ethnicity' => 'e.g. Tagalog',
        'region_of_origin' => 'Select region of origin',
        'province_of_origin' => 'e.g. Laguna',
        'city_of_origin' => 'e.g. Calamba City',
        'family_income_bracket' => 'Select annual parent or household income range',
        'indigenous_group' => 'Enter group name',
        'pwd_type' => 'Select disability type',
        'emergency_contact_name' => 'e.g. Maria Dela Cruz',
        'emergency_contact_phone' => 'e.g. 0912 345 6789',
        'emergency_contact_address' => 'Complete home address',
        'emergency_contact_relationship' => 'Select relationship',
        'father_name' => 'e.g. Juan Dela Cruz Sr.',
        'father_occupation' => 'e.g. Engineer',
        'father_contact' => 'e.g. 0912 345 6789',
        'father_email' => 'father@example.com',
        'mother_name' => 'e.g. Maria Dela Cruz',
        'mother_occupation' => 'e.g. Teacher',
        'mother_contact' => 'e.g. 0912 345 6789',
        'mother_email' => 'mother@example.com',
        'guardian_name' => 'e.g. Maria Dela Cruz',
        'guardian_relationship' => 'Select relationship',
        'guardian_contact' => 'e.g. 0912 345 6789',
        'guardian_email' => 'guardian@example.com',
        'family_address' => 'Complete family home address',
        'elementary_school' => 'Full school name',
        'junior_high_school_name' => 'Full school name',
        'senior_high_name' => 'Full school name',
        'scholarship_details' => 'Scholarship name or details',
        'employer_name' => 'Company or organization name',
        'job_position' => 'e.g. Part-time assistant',
    ];

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

    /** @return array{description: string, placeholder: string} */
    public function studentProfileFieldDefaults(string $key, string $type): array
    {
        $description = self::PROFILE_DESCRIPTIONS[$key] ?? 'Enter the information as it should appear in your school record.';
        if ($this->isIncomeProfileField($key)) {
            $description .= ' Income basis: '.config('income_brackets.modes.annual.label', 'Annual Income').'.';
        }

        return [
            'description' => $description,
            'placeholder' => $this->isIncomeProfileField($key) ? 'Choose an income range (optional)' : (self::PROFILE_PLACEHOLDERS[$key] ?? $this->defaultProfilePlaceholder($key, $type)),
        ];
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
            'fields' => collect($fields)
                ->reject(fn (array $field): bool => in_array((string) ($field['key'] ?? ''), self::EXCLUDED_PROFILE_FIELDS, true))
                ->map(fn (array $field): array => $this->profileField($field))
                ->values()
                ->all(),
        ];
    }

    /** @param array<string, mixed> $field
     *  @return array<string, mixed> */
    private function profileField(array $field): array
    {
        $key = (string) ($field['key'] ?? Str::snake((string) ($field['label'] ?? 'field')));
        $options = $this->optionsForProfileField($key, is_array($field['options'] ?? null) ? $field['options'] : []);
        $isIncome = $this->isIncomeProfileField($key);
        $isDropdownRecommended = $this->isDropdownRecommendedProfileField($key);

        $type = str_contains($key, 'address')
            ? 'textarea'
            : match ($field['type'] ?? 'string') {
                'choice' => 'select',
                'boolean' => 'yes_no',
                'date' => 'date',
                'email' => 'email',
                'number' => 'number',
                'year' => 'year',
                default => in_array($key, ['phone', 'father_contact', 'mother_contact', 'guardian_contact', 'emergency_contact_phone'], true)
                    ? 'phone'
                    : (($isIncome || $isDropdownRecommended) && $options !== [] ? 'select' : 'text'),
            };

        if (($isIncome || $isDropdownRecommended) && $options !== []) {
            $type = 'select';
        }

        $recordSuggestions = in_array($key, [
            'birthplace',
            'province_of_origin',
            'city_of_origin',
        ], true);
        $control = match (true) {
            $isIncome && $options !== [] => 'select',
            $isDropdownRecommended && $options !== [] => 'select',
            $recordSuggestions => 'combobox',
            $type === 'yes_no' || ($type === 'select' && count($options) <= 4) => 'radio_cards',
            $type === 'select' => 'select',
            default => 'input',
        };
        $defaults = $this->studentProfileFieldDefaults($key, $type);

        $fieldData = [
            'field_key' => $key,
            'label' => (string) ($field['label'] ?? Str::headline($key)),
            'type' => $type,
            'description' => $defaults['description'],
            'section' => (string) ($field['group'] ?? 'Profile'),
            'required' => $this->isRequiredProfileField($key),
            'options' => $options,
            'validation' => [],
            'presentation' => [
                'control' => $control,
                'input_mode' => $type === 'phone' ? 'tel' : ($type === 'number' || $type === 'year' ? 'numeric' : 'text'),
                'suggestion_source' => $recordSuggestions ? 'record_values' : 'none',
                'suggestion_limit' => 10,
                'placeholder' => $defaults['placeholder'],
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

        if ($key === 'birth_date') {
            $fieldData['validation']['before_or_equal'] = 'today';
        }

        return $fieldData;
    }

    private function defaultProfilePlaceholder(string $key, string $type): string
    {
        return match ($type) {
            'date' => 'YYYY-MM-DD',
            'email' => 'name@example.com',
            'number', 'year' => 'Enter a number',
            'phone' => 'e.g. 0912 345 6789',
            'select' => 'Select an option',
            default => 'Enter '.Str::headline($key),
        };
    }

    public function defaultOptionsForProfileField(string $key): array
    {
        return match ($key) {
            'gender' => self::GENDER_OPTIONS,
            'civil_status' => self::CIVIL_STATUS_OPTIONS,
            'nationality' => self::NATIONALITY_OPTIONS,
            'region_of_origin' => self::REGION_OPTIONS,
            'religion' => self::RELIGION_OPTIONS,
            'pwd_type' => self::PWD_TYPE_OPTIONS,
            'emergency_contact_relationship', 'guardian_relationship' => self::RELATIONSHIP_OPTIONS,
            default => [],
        };
    }

    public function isDropdownRecommendedProfileField(string $key): bool
    {
        return in_array($key, [
            'gender',
            'civil_status',
            'nationality',
            'region_of_origin',
            'religion',
            'pwd_type',
            'emergency_contact_relationship',
            'guardian_relationship',
        ], true);
    }

    private function optionsForProfileField(string $key, array $options): array
    {
        if ($key === 'gender') {
            return self::GENDER_OPTIONS;
        }

        if ($this->isIncomeProfileField($key)) {
            return $this->incomeBracketOptions() ?: $options;
        }

        if ($this->isDropdownRecommendedProfileField($key)) {
            return $options !== [] ? $options : $this->defaultOptionsForProfileField($key);
        }

        return $options;
    }

    private function incomeBracketOptions(): array
    {
        $brackets = config('income_brackets.modes.annual.brackets', []);
        if (! is_array($brackets)) {
            return [];
        }

        $symbol = $this->currencySymbol();

        return collect($brackets)
            ->mapWithKeys(fn (mixed $bracket, string $key): array => [
                $key => str_replace('{symbol}', $symbol, (string) data_get($bracket, 'label', '')),
            ])
            ->filter(fn (string $label): bool => $label !== '')
            ->all();
    }

    private function currencySymbol(): string
    {
        if (class_exists('App\\Settings\\SiteSettings')) {
            $currency = app('App\\Settings\\SiteSettings')->getCurrency();
            if ($currency instanceof \BackedEnum) {
                $currency = $currency->value;
            } elseif ($currency instanceof \UnitEnum) {
                $currency = $currency->name;
            }

            return match ((string) $currency) {
                'PHP' => '₱',
                'USD' => '$',
                default => (string) $currency,
            };
        }

        return '₱';
    }

    private function isIncomeProfileField(string $key): bool
    {
        return in_array($key, [
            'family_income_bracket',
            'father_income_bracket',
            'mother_income_bracket',
        ], true);
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
            'father_income_bracket', 'mother_income_bracket' => ['field' => 'family_income_bracket', 'operator' => 'is_empty'],
            'indigenous_group' => ['field' => 'is_indigenous_person', 'operator' => 'equals', 'value' => 'yes'],
            'pwd_type' => ['field' => 'is_pwd', 'operator' => 'equals', 'value' => 'yes'],
            default => null,
        };
    }
}
