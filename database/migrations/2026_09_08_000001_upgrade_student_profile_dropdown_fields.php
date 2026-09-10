<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Forms\Services\FormTemplateService;

return new class extends Migration
{
    private array $dropdownFields = [
        'civil_status',
        'nationality',
        'region_of_origin',
        'religion',
        'pwd_type',
        'emergency_contact_relationship',
        'guardian_relationship',
    ];

    public function up(): void
    {
        $templateService = app(FormTemplateService::class);

        $formIds = DB::table('forms')
            ->select(['id', 'settings'])
            ->get()
            ->filter(fn (object $form): bool => data_get($this->decodeJson($form->settings), 'template_key') === 'student_profile_completion')
            ->pluck('id')
            ->values()
            ->all();

        if ($formIds === []) {
            return;
        }

        foreach ($this->dropdownFields as $fieldKey) {
            $defaultOptions = $templateService->defaultOptionsForProfileField($fieldKey);
            if ($defaultOptions === []) {
                continue;
            }

            $fields = DB::table('form_fields')
                ->whereIn('form_id', $formIds)
                ->where('field_key', $fieldKey)
                ->get(['id', 'options', 'presentation']);

            foreach ($fields as $field) {
                $existingOptions = $this->decodeJson($field->options);
                $optionsToSave = $existingOptions !== [] ? $existingOptions : $defaultOptions;
                $existingPresentation = $this->decodeJson($field->presentation);

                DB::table('form_fields')
                    ->where('id', $field->id)
                    ->update([
                        'type' => 'select',
                        'options' => json_encode($optionsToSave, JSON_THROW_ON_ERROR),
                        'presentation' => json_encode([
                            ...$existingPresentation,
                            'control' => 'select',
                            'input_mode' => $existingPresentation['input_mode'] ?? 'text',
                            'suggestion_source' => 'none',
                            'suggestion_limit' => $existingPresentation['suggestion_limit'] ?? 10,
                            'placeholder' => $existingPresentation['placeholder'] ?? 'Select an option',
                            'unit' => $existingPresentation['unit'] ?? null,
                        ], JSON_THROW_ON_ERROR),
                    ]);
            }
        }
    }

    public function down(): void
    {
        throw new RuntimeException('This migration is forward-only.');
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
