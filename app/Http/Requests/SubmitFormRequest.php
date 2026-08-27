<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormDefinitionService;

final class SubmitFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $form = $this->route('form');

        return $form instanceof Form
            && $form->isOpen()
            && ($form->access_mode !== FormAccessMode::Authenticated || $this->user() !== null);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $form = $this->route('form');
        if (! $form instanceof Form) {
            return [];
        }

        $rules = app(FormDefinitionService::class)->validationRules($form->loadMissing('fields'));

        if ($form->access_mode === FormAccessMode::GuestIdentifier) {
            $key = $form->identity_type === 'student_id' ? 'respondent_identifier' : 'respondent_email';
            $rules[$key] = $form->identity_type === 'student_id'
                ? ['required', 'string', 'max:100']
                : ['required', 'email', 'max:255'];

            if ($form->identity_type === 'student_id') {
                $rules['respondent_email'] = ['required', 'email', 'max:255'];
            }
        }

        return $rules;
    }
}
