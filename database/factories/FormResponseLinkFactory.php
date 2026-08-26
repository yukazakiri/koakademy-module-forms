<?php

declare(strict_types=1);

namespace Modules\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Models\FormResponseLink;

final class FormResponseLinkFactory extends Factory
{
    protected $model = FormResponseLink::class;

    public function definition(): array
    {
        return [
            'form_response_id' => FormResponse::factory(),
            'model_key' => 'student',
            'model_type' => null,
            'model_id' => null,
            'match_method' => null,
            'status' => 'pending',
            'applied_by' => null,
            'applied_at' => null,
            'error_message' => null,
        ];
    }
}
