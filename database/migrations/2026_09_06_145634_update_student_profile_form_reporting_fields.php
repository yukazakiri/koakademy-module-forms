<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Arr;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormTemplate;
use Modules\Forms\Services\FormTemplateService;

return new class extends Migration
{
    public function up(): void
    {
        $definition = app(FormTemplateService::class)->definition('student_profile_completion');
        $defaults = collect($definition['fields'] ?? [])->keyBy('field_key')->all();

        Form::query()->where('settings->template_key', 'student_profile_completion')
            ->eachById(function (Form $form) use ($defaults): void {
                foreach ($form->fields as $field) {
                    $field->fill($this->changes($field->field_key, $defaults, $field->presentation ?? []))->save();
                }

                if (isset($defaults['is_solo_parent_dependent']) && ! $form->fields->contains('field_key', 'is_solo_parent_dependent')) {
                    $form->fields()->create([
                        ...$defaults['is_solo_parent_dependent'],
                        'position' => ((int) $form->fields->max('position')) + 1,
                    ]);
                }
            });

        FormTemplate::query()->eachById(function (FormTemplate $template) use ($defaults): void {
            $definition = $template->definition ?? [];
            if (data_get($definition, 'settings.template_key') !== 'student_profile_completion') {
                return;
            }

            $fields = collect($definition['fields'] ?? [])->map(fn (array $field): array => [
                ...$field,
                ...$this->changes((string) ($field['field_key'] ?? ''), $defaults, $field['presentation'] ?? []),
            ]);
            if (isset($defaults['is_solo_parent_dependent']) && ! $fields->contains('field_key', 'is_solo_parent_dependent')) {
                $fields->push($defaults['is_solo_parent_dependent']);
            }
            $definition['fields'] = $fields->values()->all();
            $template->update(['definition' => $definition]);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('This data migration is forward-only; existing answers are preserved.');
    }

    /** @param array<string, array<string, mixed>> $defaults
     *  @param array<string, mixed> $presentation
     *  @return array<string, mixed> */
    private function changes(string $key, array $defaults, array $presentation): array
    {
        $default = $defaults[$key] ?? [];
        if (in_array($key, ['father_name', 'father_occupation', 'father_contact', 'father_email', 'mother_name', 'mother_occupation', 'mother_contact', 'mother_email', 'guardian_name', 'guardian_relationship', 'guardian_contact', 'guardian_email', 'family_address'], true)) {
            return ['required' => false];
        }

        if (! in_array($key, ['family_income_bracket', 'father_income_bracket', 'mother_income_bracket', 'pwd_type'], true) || $default === []) {
            return [];
        }

        return [
            ...Arr::only($default, ['type', 'options', 'required', 'description', 'visibility']),
            'presentation' => [...$presentation, ...Arr::only($default['presentation'], ['control', 'placeholder'])],
        ];
    }
};
