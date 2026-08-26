<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use Illuminate\Support\Facades\Auth;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormAuditEvent;
use Modules\Forms\Models\FormResponse;

final class FormsAuditService
{
    /** @param array<string, mixed> $metadata */
    public function record(Form $form, string $action, ?FormResponse $response = null, array $metadata = []): void
    {
        FormAuditEvent::query()->create([
            'form_id' => $form->getKey(),
            'form_response_id' => $response?->getKey(),
            'actor_id' => Auth::id() === null ? null : (string) Auth::id(),
            'action' => $action,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
