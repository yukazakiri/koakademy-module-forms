<?php

declare(strict_types=1);

namespace Modules\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormInvitation;

final class FormInvitationFactory extends Factory
{
    protected $model = FormInvitation::class;

    public function definition(): array
    {
        $token = FormInvitation::newToken();

        return [
            'form_id' => Form::factory(['access_mode' => FormAccessMode::Invitation]),
            'model_key' => 'student',
            'model_type' => null,
            'model_id' => (string) fake()->numberBetween(1, 9999),
            'token_hash' => FormInvitation::tokenHash($token),
            'recipient_email' => fake()->safeEmail(),
            'status' => 'pending',
            'expires_at' => now()->addDays(30),
            'sent_at' => null,
            'opened_at' => null,
            'completed_at' => null,
            'response_id' => null,
            'error_message' => null,
        ];
    }
}
