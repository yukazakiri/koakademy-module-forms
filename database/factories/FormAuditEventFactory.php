<?php

declare(strict_types=1);

namespace Modules\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormAuditEvent;

final class FormAuditEventFactory extends Factory
{
    protected $model = FormAuditEvent::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'form_response_id' => null,
            'actor_id' => null,
            'action' => 'form_created',
            'metadata' => [],
            'created_at' => now(),
        ];
    }
}
