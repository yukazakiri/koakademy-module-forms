<?php

declare(strict_types=1);

namespace Modules\Forms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Modules\Forms\Mail\FormInvitationMail;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormInvitation;
use Throwable;

final class SendFormInvitation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private string $encryptedToken;

    public function __construct(
        public readonly string $invitationId,
        string $token,
    ) {
        $this->encryptedToken = Crypt::encryptString($token);
    }

    public function handle(): void
    {
        $invitation = FormInvitation::query()->with('form')->find($this->invitationId);
        if (! $invitation instanceof FormInvitation
            || ! $invitation->form instanceof Form
            || ! $invitation->form->isOpen()
            || $invitation->status !== 'pending'
            || ! $invitation->isUsable()) {
            return;
        }

        try {
            Mail::mailer((string) config('mail.default'))->to($invitation->recipient_email)->send(
                new FormInvitationMail($invitation, Crypt::decryptString($this->encryptedToken)),
            );
            $invitation->update([
                'status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);
            $invitation->update([
                'status' => 'failed',
                'error_message' => 'The invitation email could not be delivered.',
            ]);

            throw $exception;
        }
    }
}
