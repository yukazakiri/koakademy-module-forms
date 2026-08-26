<?php

declare(strict_types=1);

namespace Modules\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Forms\Enums\FormResponseStatus;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormResponse;

final class FormResponseFactory extends Factory
{
    protected $model = FormResponse::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'tenant_key' => null,
            'respondent_user_id' => null,
            'respondent_email' => null,
            'respondent_identifier' => null,
            'respondent_email_hash' => null,
            'respondent_identifier_hash' => null,
            'status' => FormResponseStatus::Submitted,
            'review_notes' => null,
            'latest_revision' => 0,
            'submitted_at' => now(),
            'reviewed_by' => null,
            'reviewed_at' => null,
            'applied_by' => null,
            'applied_at' => null,
        ];
    }
}
