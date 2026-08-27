<?php

declare(strict_types=1);

namespace Modules\Forms\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Forms\Models\FormInvitation;

final class FormInvitationMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly FormInvitation $invitation,
        public readonly string $token,
    ) {}

    public function build(): static
    {
        $form = $this->invitation->form;
        $url = route('forms.invitation.show', ['form' => $form->slug, 'token' => $this->token]);

        return $this
            ->subject('Complete your '.($form->title ?? 'profile form'))
            ->view('forms::emails.invitation', [
                'form' => $form,
                'url' => $url,
                'expiresAt' => $this->invitation->expires_at,
            ]);
    }
}
