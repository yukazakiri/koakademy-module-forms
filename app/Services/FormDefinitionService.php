<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Modules\Forms\Contracts\FormsFieldSuggestionProvider;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormField;

final class FormDefinitionService
{
    public function __construct(
        private readonly FormsModelRegistry $models,
        private readonly FormsFieldSuggestionProvider $suggestions,
    ) {}

    /** @return list<string> */
    public function supportedTypes(): array
    {
        return ['text', 'textarea', 'email', 'phone', 'number', 'year', 'date', 'select', 'radio', 'checkbox', 'yes_no', 'file', 'rating'];
    }

    /** @return array<string, mixed> */
    public function publicPayload(Form $form, ?object $record = null): array
    {
        $fields = $form->fields
            ->filter(fn (FormField $field): bool => $record === null || $this->shouldRenderField($field, $record))
            ->map(fn (FormField $field): array => $this->fieldPayload($field, $record))
            ->values()
            ->all();

        return [
            'id' => $form->getKey(),
            'slug' => $form->slug,
            'title' => $form->title,
            'description' => $form->description,
            'access_mode' => $form->access_mode->value,
            'identity_type' => $form->identity_type,
            'settings' => $form->settings ?? [],
            'fields' => $fields,
        ];
    }

    /** @return array<string, mixed> */
    public function fieldPayload(FormField $field, ?object $record = null): array
    {
        $options = $field->options ?? [];
        if ($field->type === 'yes_no' && $options === []) {
            $options = ['yes' => 'Yes', 'no' => 'No'];
        }

        return [
            'key' => $field->field_key,
            'label' => $field->label,
            'type' => $field->type,
            'description' => $field->description,
            'required' => $field->required,
            'options' => $options,
            'visibility' => $field->visibility,
            'section' => $field->section,
            'presentation' => $field->presentation ?? [],
            'behavior' => $field->behavior ?? [],
            'suggestions' => $this->suggestions->suggestions($field),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function validationRules(Form $form, ?object $record = null): array
    {
        $rules = ['answers' => ['array']];

        foreach ($form->fields as $field) {
            if ($record !== null && ! $this->shouldRenderField($field, $record)) {
                continue;
            }
            $key = 'answers.'.$field->field_key;
            $fieldRules = [$field->required ? 'required' : 'nullable'];

            $fieldRules = [...$fieldRules, ...$this->typeRules($field)];
            $rules[$key] = $fieldRules;

            if ($field->type === 'checkbox') {
                $rules[$key.'.*'] = [Rule::in(array_keys($field->options ?? []))];
            }
        }

        return $rules;
    }

    /** @return array<int, mixed> */
    private function typeRules(FormField $field): array
    {
        $validation = $field->validation ?? [];

        $rules = match ($field->type) {
            'text', 'textarea' => ['string'],
            'email' => ['email'],
            'number', 'rating' => ['numeric'],
            'year' => ['integer', 'between:1900,'.now()->year],
            'phone' => ['string', 'regex:/^\+?[0-9 ()-]{7,30}$/'],
            'date' => ['date'],
            'select', 'radio' => [Rule::in(array_keys($field->options ?? []))],
            'yes_no' => [Rule::in(array_keys($field->options ?: ['yes' => 'Yes', 'no' => 'No']))],
            'checkbox' => ['array', 'min:1', 'max:'.((int) ($validation['max_selections'] ?? 50))],
            'file' => [File::types($validation['mimes'] ?? ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'])->max((int) config('forms.max_upload_kilobytes', 10240))],
            default => ['string'],
        };

        if (isset($validation['min']) && is_numeric($validation['min'])) {
            $rules[] = $field->type === 'number' || $field->type === 'rating' ? 'min:'.$validation['min'] : 'min:'.$validation['min'];
        }

        if (isset($validation['max']) && is_numeric($validation['max'])) {
            $rules[] = 'max:'.$validation['max'];
        }

        return $rules;
    }

    /** @param array<string, mixed> $answers
     *  @return array<string, mixed> */
    public function normalizeAnswers(Form $form, array $answers): array
    {
        $normalized = [];
        $fieldLookup = $form->fields->keyBy('field_key');

        foreach ($answers as $key => $value) {
            $field = $fieldLookup->get((string) $key);
            if (! $field instanceof FormField || ! $this->isVisible($field, $answers)) {
                continue;
            }

            if ($value instanceof UploadedFile) {
                $path = Storage::disk((string) config('forms.uploads_disk', 'local'))
                    ->putFile('forms/'.$form->getKey(), $value);

                $normalized[$key] = [
                    'path' => $path,
                    'name' => $value->getClientOriginalName(),
                    'mime' => $value->getClientMimeType(),
                    'size' => $value->getSize(),
                ];

                continue;
            }

            $normalized[$key] = is_array($value)
                ? array_values(array_map(static fn (mixed $item): string => Str::of((string) $item)->trim()->toString(), $value))
                : ($value === null ? null : Str::of((string) $value)->trim()->toString());
        }

        return $normalized;
    }

    /** @param array<string, mixed> $answers */
    public function isVisible(FormField $field, array $answers): bool
    {
        $visibility = $field->visibility;
        if (! is_array($visibility) || ($visibility['field'] ?? '') === '') {
            return true;
        }

        $actual = Arr::get($answers, (string) $visibility['field']);
        $expected = $visibility['value'] ?? null;

        return match ($visibility['operator'] ?? 'equals') {
            'not_equals' => $actual != $expected,
            'contains' => is_array($actual) && in_array($expected, $actual, true),
            default => $actual == $expected,
        };
    }

    private function shouldRenderField(FormField $field, object $record): bool
    {
        $behavior = is_array($field->behavior) ? $field->behavior : [];
        if (($behavior['missing_only'] ?? false) !== true) {
            return true;
        }

        $mapping = $field->mapping;
        if (! is_array($mapping) || ! is_string($mapping['model'] ?? null) || ! is_string($mapping['path'] ?? null)) {
            return true;
        }

        return ! $this->isFilled($this->models->read($record, $mapping['path']));
    }

    private function isFilled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    /** @return list<array<string, mixed>> */
    public function snapshot(Form $form): array
    {
        return $form->fields->map(fn (FormField $field): array => [
            'key' => $field->field_key,
            'label' => $field->label,
            'type' => $field->type,
            'mapping' => $field->mapping,
            'is_sensitive' => $field->is_sensitive,
        ])->values()->all();
    }
}
