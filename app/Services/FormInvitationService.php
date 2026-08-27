<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Jobs\SendFormInvitation;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormInvitation;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FormInvitationService
{
    public function __construct(
        private readonly FormsInvitationTargetProvider $targets,
        private readonly FormsTenantResolver $tenantResolver,
        private readonly FormsAuditService $audit,
    ) {}

    /** @return list<array<string, mixed>> */
    public function preview(Form $form): array
    {
        $this->ensureTenant($form);
        if (! $form->isOpen() || $form->access_mode !== FormAccessMode::Invitation) {
            throw ValidationException::withMessages(['form' => 'Publish an invitation form before sending links.']);
        }

        $preview = [];
        foreach ($this->targets->candidates($form) as $candidate) {
            $record = $this->targets->resolve(new FormInvitation([
                'model_key' => $candidate['model_key'],
                'model_id' => (string) $candidate['model_id'],
            ]));

            $preview[] = [
                'model_key' => (string) $candidate['model_key'],
                'model_type' => $candidate['model_type'] ?? null,
                'model_id' => (string) $candidate['model_id'],
                'email' => (string) $candidate['email'],
                'name' => $record === null ? null : data_get($record, 'full_name', data_get($record, 'name')),
                'student_number' => $record === null ? null : data_get($record, 'student_id'),
            ];
        }

        return $preview;
    }

    /** @param list<string> $modelIds
     *  @return array{created: int, skipped: int} */
    public function send(Form $form, array $modelIds, object $actor): array
    {
        $this->ensureTenant($form);
        if (! $form->isOpen() || $form->access_mode !== FormAccessMode::Invitation) {
            throw ValidationException::withMessages(['form' => 'Publish an invitation form before sending links.']);
        }

        $selected = array_fill_keys(array_map(static fn (string|int $id): string => (string) $id, $modelIds), true);
        $created = 0;
        $expiryDays = max(1, min((int) data_get($form->settings, 'invitation_expiry_days', 30), 90));

        DB::transaction(function () use ($form, $selected, $expiryDays, &$created): void {
            foreach ($this->targets->candidates($form) as $candidate) {
                $modelId = (string) $candidate['model_id'];
                if (! isset($selected[$modelId])) {
                    continue;
                }

                $existing = FormInvitation::query()
                    ->where('form_id', $form->getKey())
                    ->where('model_key', (string) $candidate['model_key'])
                    ->where('model_id', $modelId)
                    ->whereIn('status', ['pending', 'sent'])
                    ->get();

                foreach ($existing as $invitation) {
                    $invitation->update(['status' => 'revoked']);
                }

                $token = FormInvitation::newToken();
                $invitation = FormInvitation::query()->create([
                    'form_id' => $form->getKey(),
                    'model_key' => (string) $candidate['model_key'],
                    'model_type' => $candidate['model_type'] ?? null,
                    'model_id' => $modelId,
                    'token_hash' => FormInvitation::tokenHash($token),
                    'recipient_email' => Str::lower(trim((string) $candidate['email'])),
                    'status' => 'pending',
                    'expires_at' => now()->addDays($expiryDays),
                ]);

                SendFormInvitation::dispatch($invitation->getKey(), $token)->afterCommit();
                $created++;
            }
        });

        $skipped = max(0, count($selected) - $created);

        $this->audit->record($form, 'invitations_queued', metadata: [
            'created' => $created,
            'requested' => count($modelIds),
            'expires_in_days' => $expiryDays,
        ]);

        return compact('created', 'skipped');
    }

    public function resolve(Form $form, string $token): FormInvitation
    {
        $this->ensureTenant($form);
        if (! $form->isOpen() || $form->access_mode !== FormAccessMode::Invitation) {
            throw new NotFoundHttpException;
        }

        $invitation = FormInvitation::query()
            ->where('form_id', $form->getKey())
            ->where('token_hash', FormInvitation::tokenHash($token))
            ->first();

        if (! $invitation instanceof FormInvitation || ! $invitation->isUsable()) {
            throw new NotFoundHttpException;
        }

        if ($invitation->opened_at === null) {
            $invitation->update(['opened_at' => now()]);
        }

        return $invitation->loadMissing('form.fields');
    }

    public function ensureTenant(Form $form): void
    {
        $tenantKey = $this->tenantResolver->key();
        if ($tenantKey !== null && (string) $form->tenant_key !== (string) $tenantKey) {
            throw new NotFoundHttpException;
        }
    }
}
