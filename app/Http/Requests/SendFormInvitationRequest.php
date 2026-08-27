<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Forms\Services\FormsAuthorization;

final class SendFormInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(FormsAuthorization::class)->allows($this->user(), 'invitations.create');
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'model_ids' => ['required', 'array', 'min:1', 'max:10000'],
            'model_ids.*' => ['required', 'string', 'max:100'],
        ];
    }
}
