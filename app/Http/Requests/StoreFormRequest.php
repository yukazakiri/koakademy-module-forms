<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Forms\Services\FormDefinitionService;
use Modules\Forms\Services\FormsAuthorization;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(FormsAuthorization::class)->allows($this->user(), 'create');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', Rule::unique('forms', 'slug')->ignore($this->route('form')?->getKey())],
            'description' => ['nullable', 'string', 'max:5000'],
            'access_mode' => ['required', Rule::in(['authenticated', 'guest_identifier', 'anonymous'])],
            'identity_type' => ['nullable', 'required_if:access_mode,guest_identifier', Rule::in(['email', 'student_id'])],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'settings' => ['nullable', 'array'],
            'settings.allow_resubmit' => ['nullable', 'boolean'],
            'fields' => ['required', 'array', 'min:1', 'max:100'],
            'fields.*.field_key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => [
                'required',
                Rule::in(app(FormDefinitionService::class)->supportedTypes()),
            ],
            'fields.*.description' => ['nullable', 'string', 'max:1000'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:100'],
            'fields.*.options.*' => ['nullable', 'string', 'max:255'],
            'fields.*.validation' => ['nullable', 'array'],
            'fields.*.visibility' => ['nullable', 'array'],
            'fields.*.visibility.field' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'fields.*.visibility.operator' => ['nullable', Rule::in(['equals', 'not_equals', 'contains'])],
            'fields.*.mapping' => ['nullable', 'array'],
            'fields.*.mapping.model' => ['nullable', 'required_with:fields.*.mapping.path', 'string', 'max:100'],
            'fields.*.mapping.path' => ['nullable', 'required_with:fields.*.mapping.model', 'string', 'max:255'],
            'fields.*.is_sensitive' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => $this->filled('slug') ? str($this->input('slug'))->slug()->toString() : null,
            'fields' => collect($this->input('fields', []))->map(function (mixed $field): mixed {
                if (! is_array($field)) {
                    return $field;
                }

                $field['field_key'] = str((string) ($field['field_key'] ?? ''))->snake()->toString();

                return $field;
            })->values()->all(),
        ]);
    }
}
