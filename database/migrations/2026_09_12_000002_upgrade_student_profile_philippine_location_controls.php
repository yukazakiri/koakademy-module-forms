<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormField;
use Modules\Forms\Models\FormTemplate;

return new class extends Migration
{
    /** @var list<string> */
    private const LOCATION_FIELDS = [
        'ethnicity',
        'region_of_origin',
        'province_of_origin',
        'city_of_origin',
    ];

    private const ETHNICITY_OPTIONS = [
        'Aeta' => 'Aeta',
        'Agta' => 'Agta',
        'Alangan Mangyan' => 'Alangan Mangyan',
        'Ati' => 'Ati',
        'Badjao / Sama-Bajau' => 'Badjao / Sama-Bajau',
        'Bikolano' => 'Bikolano',
        'Blaan' => 'Blaan',
        'Bontoc' => 'Bontoc',
        'Bukidnon' => 'Bukidnon',
        'Butuanon' => 'Butuanon',
        'Cagayanon' => 'Cagayanon',
        'Calamian Tagbanwa' => 'Calamian Tagbanwa',
        'Cebuano' => 'Cebuano',
        'Chavacano' => 'Chavacano',
        'Hiligaynon / Ilonggo' => 'Hiligaynon / Ilonggo',
        'Ibanag' => 'Ibanag',
        'Ifugao' => 'Ifugao',
        'Igorot' => 'Igorot',
        'Ilocano' => 'Ilocano',
        'Iranun' => 'Iranun',
        'Isnag' => 'Isnag',
        'Itawis' => 'Itawis',
        'Ivatan' => 'Ivatan',
        'Kalinga' => 'Kalinga',
        'Kamayo' => 'Kamayo',
        'Kankanaey' => 'Kankanaey',
        'Kapampangan' => 'Kapampangan',
        'Kinaray-a' => 'Kinaray-a',
        'Maguindanaon' => 'Maguindanaon',
        'Manobo' => 'Manobo',
        'Maranao' => 'Maranao',
        'Masbateño' => 'Masbateño',
        'Palawano' => 'Palawano',
        'Pangasinan' => 'Pangasinan',
        'Sama' => 'Sama',
        'Subanen' => 'Subanen',
        'Surigaonon' => 'Surigaonon',
        'Tagalog' => 'Tagalog',
        'Tagbanwa' => 'Tagbanwa',
        'Tausug' => 'Tausug',
        "T'boli" => "T'boli",
        'Teduray' => 'Teduray',
        'Waray' => 'Waray',
        'Yakan' => 'Yakan',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('schools')) {
            return;
        }

        $philippineTenantKeys = DB::table('schools')
            ->whereRaw("UPPER(TRIM(COALESCE(country_code, ''))) = 'PH'")
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        if ($philippineTenantKeys === []) {
            return;
        }

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
            ->whereIn('tenant_key', $philippineTenantKeys)
            ->chunkById(100, function (Collection $forms): void {
                foreach ($forms as $form) {
                    $settings = is_array($form->settings) ? $form->settings : [];
                    if (($settings['template_key'] ?? null) !== 'student_profile_completion') {
                        $form->update([
                            'settings' => [...$settings, 'template_key' => 'student_profile_completion'],
                        ]);
                    }

                    foreach ($form->fields as $field) {
                        $this->upgradeField($field);
                    }
                }
            }, 'id');

        FormTemplate::query()
            ->select(['id', 'name', 'model_key', 'definition'])
            ->whereIn('tenant_key', $philippineTenantKeys)
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
                    $settings = is_array($definition['settings'] ?? null) ? $definition['settings'] : [];
                    $definition['settings'] = [
                        ...$settings,
                        'template_key' => 'student_profile_completion',
                    ];

                    $fields = $definition['fields'] ?? [];
                    if (! is_array($fields)) {
                        continue;
                    }

                    $changed = $definition['settings'] !== $settings;
                    foreach ($fields as $index => $field) {
                        if (! is_array($field)) {
                            continue;
                        }

                        $updated = $this->upgradedDefinitionField($field);
                        if ($updated === $field) {
                            continue;
                        }

                        $fields[$index] = $updated;
                        $changed = true;
                    }

                    if (! $changed) {
                        continue;
                    }

                    $definition['fields'] = $fields;
                    $template->update(['definition' => $definition]);
                }
            }, 'id');
    }

    public function down(): void
    {
        throw new RuntimeException('This forward-only data migration cannot safely restore prior saved form definitions.');
    }

    private function upgradeField(FormField $field): void
    {
        if (! in_array($field->field_key, self::LOCATION_FIELDS, true)) {
            return;
        }

        $presentation = is_array($field->presentation) ? $field->presentation : [];
        $options = is_array($field->options) ? $field->options : [];

        if ($field->field_key === 'ethnicity') {
            $options = self::ETHNICITY_OPTIONS;
            $presentation = [
                ...$presentation,
                'control' => 'combobox',
                'allow_custom' => true,
                'suggestion_source' => 'none',
            ];
        } else {
            $presentation = [
                ...$presentation,
                'control' => 'philippine_location',
                'suggestion_source' => 'none',
            ];
        }

        $field->update([
            'type' => $field->field_key === 'ethnicity' ? 'select' : 'text',
            'options' => $options,
            'presentation' => $presentation,
        ]);
    }

    /** @param array<string, mixed> $field
     *  @return array<string, mixed> */
    private function upgradedDefinitionField(array $field): array
    {
        $key = (string) ($field['field_key'] ?? '');
        if (! in_array($key, self::LOCATION_FIELDS, true)) {
            return $field;
        }

        $presentation = is_array($field['presentation'] ?? null) ? $field['presentation'] : [];
        if ($key === 'ethnicity') {
            return [
                ...$field,
                'type' => 'select',
                'options' => self::ETHNICITY_OPTIONS,
                'presentation' => [
                    ...$presentation,
                    'control' => 'combobox',
                    'allow_custom' => true,
                    'suggestion_source' => 'none',
                ],
            ];
        }

        return [
            ...$field,
            'type' => 'text',
            'presentation' => [
                ...$presentation,
                'control' => 'philippine_location',
                'suggestion_source' => 'none',
            ],
        ];
    }
};
