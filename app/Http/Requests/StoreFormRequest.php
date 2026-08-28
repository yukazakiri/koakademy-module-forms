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
            'access_mode' => ['required', Rule::in(['authenticated', 'guest_identifier', 'anonymous', 'invitation'])],
            'identity_type' => ['nullable', 'required_if:access_mode,guest_identifier', Rule::in(['email', 'student_id'])],
            'closes_at' => ['nullable', 'date', 'after:now'],
            'settings' => ['nullable', 'array'],
            'settings.allow_resubmit' => ['nullable', 'boolean'],
            'settings.allow_unverified_guest_response' => ['nullable', 'boolean'],
            'settings.mapping_mode' => ['nullable', Rule::in(['review', 'auto_fill_empty'])],
            'settings.invitation_expiry_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'fields' => ['required', 'array', 'min:1', 'max:100'],
            'fields.*.field_key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/', 'distinct'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => [
                'required',
                Rule::in(app(FormDefinitionService::class)->supportedTypes()),
            ],
            'fields.*.description' => ['nullable', 'string', 'max:1000'],
            'fields.*.section' => ['nullable', 'string', 'max:100'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:100'],
            'fields.*.options.*' => ['nullable', 'string', 'max:255'],
            'fields.*.validation' => ['nullable', 'array'],
            'fields.*.validation.min' => ['nullable', 'numeric'],
            'fields.*.validation.max' => ['nullable', 'numeric'],
            'fields.*.validation.mimes' => ['nullable', 'array', 'max:20'],
            'fields.*.validation.mimes.*' => ['string', 'max:20'],
            'fields.*.presentation' => ['nullable', 'array'],
            'fields.*.presentation.control' => ['nullable', Rule::in(['auto', 'input', 'select', 'radio_cards', 'combobox'])],
            'fields.*.presentation.placeholder' => ['nullable', 'string', 'max:255'],
            'fields.*.presentation.input_mode' => ['nullable', Rule::in(['text', 'tel', 'numeric', 'decimal', 'email', 'none'])],
            'fields.*.presentation.suggestion_source' => ['nullable', Rule::in(['none', 'static', 'record_values'])],
            'fields.*.presentation.suggestion_limit' => ['nullable', 'integer', 'min:2', 'max:50'],
            'fields.*.presentation.unit' => ['nullable', 'string', 'max:20'],
            'fields.*.behavior' => ['nullable', 'array'],
            'fields.*.behavior.missing_only' => ['nullable', 'boolean'],
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
