<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Models\Form;

final class ResolveGuestIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $form = $this->route('form');

        return $form instanceof Form
            && $form->isOpen()
            && $form->access_mode === FormAccessMode::GuestIdentifier
            && $form->identity_type === 'student_id';
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'respondent_identifier' => ['required', 'string', 'max:100'],
            'respondent_email' => ['required', 'email', 'max:255'],
        ];
    }
}
