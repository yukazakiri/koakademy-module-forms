<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormInvitation;

final class NullFormsInvitationTargetProvider implements FormsInvitationTargetProvider
{
    public function candidates(Form $form): iterable
    {
        return [];
    }

    public function resolve(FormInvitation $invitation): ?object
    {
        return null;
    }
}
