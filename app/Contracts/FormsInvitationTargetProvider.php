<?php

declare(strict_types=1);

namespace Modules\Forms\Contracts;

use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormInvitation;

interface FormsInvitationTargetProvider
{
    /** @return iterable<array{model_key: string, model_type: ?string, model_id: string|int, email: string}> */
    public function candidates(Form $form): iterable;

    public function resolve(FormInvitation $invitation): ?object;
}
