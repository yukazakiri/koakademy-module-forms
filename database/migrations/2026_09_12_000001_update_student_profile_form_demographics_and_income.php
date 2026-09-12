<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormField;
use Modules\Forms\Models\FormTemplate;
use Modules\Forms\Services\FormTemplateService;

return new class extends Migration
{
    /** @var list<string> */
    private const PROFILE_FIELDS = [
        'gender',
        'ethnicity',
        'region_of_origin',
        'province_of_origin',
        'city_of_origin',
        'is_indigenous_person',
        'indigenous_group',
        'is_pwd',
        'pwd_type',
        'is_solo_parent',
        'is_solo_parent_dependent',
        'is_senior_citizen',
        'is_magna_carta',
        'is_underprivileged',
        'is_first_generation',
        'family_income_bracket',
    ];

    /** @var list<string> */
    private const RETIRED_INCOME_FIELDS = [
        'father_income_bracket',
        'mother_income_bracket',
    ];

    /** @var list<string> */
    private const REMOVED_TEMPLATE_FIELDS = [
        'facebook_contact',
        'twitter',
        'instagram',
        'linkedin',
        ...self::RETIRED_INCOME_FIELDS,
    ];

    public function up(): void
    {
        $definition = app(FormTemplateService::class)->definition('student_profile_completion');
        $defaults = collect($definition['fields'] ?? [])->keyBy('field_key')->all();

        if ($defaults === []) {
            return;
        }

        Form::query()
            ->with(['fields' => fn ($query) => $query->orderBy('position')])
            ->where(function ($query): void {
                $query
                    ->where('settings->template_key', 'student_profile_completion')
                    ->orWhere(function ($query): void {
                        $query->where('title', 'Student Profile Completion')
                            ->where('access_mode', 'invitation')
                            ->where('settings->mapping_mode', 'auto_fill_empty');
                    });
            })
            ->chunkById(100, function (Collection $forms) use ($defaults, $definition): void {
                foreach ($forms as $form) {
                    $this->updateForm($form, $defaults, $definition);
                }
            }, 'id');

        FormTemplate::query()
            ->chunkById(100, function (Collection $templates) use ($defaults, $definition): void {
                foreach ($templates as $template) {
                    $templateDefinition = is_array($template->definition) ? $template->definition : [];
                    if (data_get($templateDefinition, 'settings.template_key') !== 'student_profile_completion') {
                        continue;
                    }

                    $templateDefinition['settings'] = $this->profileSettings(
                        is_array($templateDefinition['settings'] ?? null) ? $templateDefinition['settings'] : [],
                        $definition,
                    );
                    $templateDefinition['fields'] = $this->updatedDefinitionFields(
                        is_array($templateDefinition['fields'] ?? null) ? $templateDefinition['fields'] : [],
                        $defaults,
                    );

                    $template->update(['definition' => $templateDefinition]);
                }
            }, 'id');
    }

    public function down(): void
    {
        throw new RuntimeException('This forward-only data migration preserves existing responses and cannot be reversed safely.');
    }

    /** @param array<string, array<string, mixed>> $defaults
     *  @param array<string, mixed> $definition */
    private function updateForm(Form $form, array $defaults, array $definition): void
    {
        $settings = $this->profileSettings(is_array($form->settings) ? $form->settings : [], $definition);
        if ($settings !== $form->settings) {
            $form->settings = $settings;
            $form->save();
        }

        $fields = $form->fields->keyBy('field_key');
        $nextPosition = ((int) $form->fields->max('position')) + 1;
        foreach (self::PROFILE_FIELDS as $key) {
            $default = $defaults[$key] ?? null;
            if (! is_array($default)) {
                continue;
            }

            $field = $fields->get($key);
            if ($field instanceof FormField) {
                $this->updateField($field, $default);

                continue;
            }

            $form->fields()->create([
                ...$default,
                'position' => $nextPosition++,
            ]);
        }

        foreach (self::RETIRED_INCOME_FIELDS as $key) {
            $field = $fields->get($key);
            if (! $field instanceof FormField) {
                continue;
            }

            $behavior = is_array($field->behavior) ? $field->behavior : [];
            $field->update([
                'required' => false,
                'behavior' => [...$behavior, 'retired' => true],
            ]);
        }
    }

    /** @param array<string, mixed> $default */
    private function updateField(FormField $field, array $default): void
    {
        $defaultPresentation = is_array($default['presentation'] ?? null) ? $default['presentation'] : [];
        $presentation = [
            ...$defaultPresentation,
            ...(is_array($field->presentation) ? $field->presentation : []),
        ];
        $presentation['control'] = $defaultPresentation['control'] ?? $presentation['control'] ?? 'input';
        $presentation['suggestion_source'] = $defaultPresentation['suggestion_source'] ?? $presentation['suggestion_source'] ?? 'none';

        $behavior = [
            ...(is_array($default['behavior'] ?? null) ? $default['behavior'] : []),
            ...(is_array($field->behavior) ? $field->behavior : []),
        ];
        unset($behavior['retired']);

        $options = is_array($field->options) ? $field->options : [];
        if ($field->field_key === 'gender') {
            $options = FormTemplateService::GENDER_OPTIONS;
        } elseif ($field->field_key === 'family_income_bracket') {
            $options = is_array($default['options'] ?? null) ? $default['options'] : $options;
        }

        $field->update([
            'type' => $default['type'] ?? $field->type,
            'description' => $field->description ?: ($default['description'] ?? null),
            'section' => $field->section ?: ($default['section'] ?? null),
            'options' => $options,
            'validation' => [
                ...(is_array($default['validation'] ?? null) ? $default['validation'] : []),
                ...(is_array($field->validation) ? $field->validation : []),
            ],
            'presentation' => $presentation,
            'behavior' => $behavior,
            'visibility' => $this->mergedVisibility($field->visibility, $default['visibility'] ?? null),
            'mapping' => $field->mapping ?: ($default['mapping'] ?? null),
            'is_sensitive' => $field->is_sensitive || (bool) ($default['is_sensitive'] ?? false),
        ]);
    }

    /** @param list<array<string, mixed>> $fields
     *  @param array<string, array<string, mixed>> $defaults
     *  @return list<array<string, mixed>> */
    private function updatedDefinitionFields(array $fields, array $defaults): array
    {
        $updated = collect($fields)
            ->filter(fn (mixed $field): bool => is_array($field))
            ->reject(fn (array $field): bool => in_array((string) ($field['field_key'] ?? ''), self::REMOVED_TEMPLATE_FIELDS, true))
            ->map(function (array $field) use ($defaults): array {
                $key = (string) ($field['field_key'] ?? '');
                if (! in_array($key, self::PROFILE_FIELDS, true) || ! isset($defaults[$key])) {
                    return $field;
                }

                return $this->updatedDefinitionField($field, $defaults[$key]);
            })
            ->values();

        $existingKeys = $updated->pluck('field_key')->all();
        foreach (self::PROFILE_FIELDS as $key) {
            if (in_array($key, $existingKeys, true) || ! isset($defaults[$key])) {
                continue;
            }

            $updated->push($defaults[$key]);
        }

        return $updated->all();
    }

    /** @param array<string, mixed> $field
     *  @param array<string, mixed> $default
     *  @return array<string, mixed> */
    private function updatedDefinitionField(array $field, array $default): array
    {
        $updated = [
            ...$default,
            ...$field,
        ];

        $updated['type'] = $default['type'] ?? ($field['type'] ?? 'text');
        if (($field['options'] ?? []) === [] && ($default['options'] ?? []) !== []) {
            $updated['options'] = $default['options'];
        }

        if (($field['label'] ?? '') === '') {
            $updated['label'] = $default['label'] ?? $field['label'] ?? '';
        }
        if (($field['description'] ?? null) === null) {
            $updated['description'] = $default['description'] ?? null;
        }
        if (($field['section'] ?? '') === '') {
            $updated['section'] = $default['section'] ?? null;
        }
        if (($field['mapping'] ?? null) === null) {
            $updated['mapping'] = $default['mapping'] ?? null;
        }

        $updated['behavior'] = [
            ...(is_array($default['behavior'] ?? null) ? $default['behavior'] : []),
            ...(is_array($field['behavior'] ?? null) ? $field['behavior'] : []),
        ];
        unset($updated['behavior']['retired']);

        $updated['presentation'] = [
            ...(is_array($field['presentation'] ?? null) ? $field['presentation'] : []),
            ...(is_array($default['presentation'] ?? null) ? $default['presentation'] : []),
        ];

        $key = (string) ($field['field_key'] ?? '');
        if ($key !== '') {
            if ($key === 'gender') {
                $updated['type'] = 'select';
                $updated['options'] = FormTemplateService::GENDER_OPTIONS;
                $updated['presentation']['control'] = $default['presentation']['control'] ?? 'radio_cards';
            } elseif ($key === 'family_income_bracket') {
                $updated['type'] = 'select';
                $updated['options'] = $default['options'] ?? $field['options'] ?? [];
                $updated['presentation']['control'] = 'select';
            }
        }

        $updated['visibility'] = $this->mergedVisibility($field['visibility'] ?? null, $default['visibility'] ?? null);
        $updated['is_sensitive'] = (bool) ($field['is_sensitive'] ?? false) || (bool) ($default['is_sensitive'] ?? false);

        return $updated;
    }

    /** @return array<string, mixed>|null */
    private function mergedVisibility(mixed $existing, mixed $default): ?array
    {
        if (! is_array($existing) && ! is_array($default)) {
            return null;
        }

        return [
            ...(is_array($default) ? $default : []),
            ...(is_array($existing) ? $existing : []),
        ];
    }

    /** @param array<string, mixed> $settings
     *  @param array<string, mixed> $definition
     *  @return array<string, mixed> */
    private function profileSettings(array $settings, array $definition): array
    {
        return [
            ...(is_array($definition['settings'] ?? null) ? $definition['settings'] : []),
            ...$settings,
            'template_key' => 'student_profile_completion',
        ];
    }
};
