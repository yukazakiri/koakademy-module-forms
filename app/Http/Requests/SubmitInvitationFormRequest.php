<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormDefinitionService;
use Modules\Forms\Services\FormInvitationService;

final class SubmitInvitationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        $form = $this->route('form');
        if (! $form instanceof Form || $form->access_mode !== FormAccessMode::Invitation) {
            return false;
        }

        try {
            return $form->isOpen() && app(FormInvitationService::class)->resolve($form, (string) $this->route('token'))->isUsable();
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $form = $this->route('form');
        if (! $form instanceof Form) {
            return [];
        }

        $invitation = app(FormInvitationService::class)->resolve($form, (string) $this->route('token'));
        $record = app(FormsInvitationTargetProvider::class)->resolve($invitation);

        return app(FormDefinitionService::class)->validationRules($form->loadMissing('fields'), $record);
    }
}
