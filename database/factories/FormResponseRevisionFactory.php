<?php

declare(strict_types=1);

namespace Modules\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Models\FormResponseRevision;

final class FormResponseRevisionFactory extends Factory
{
    protected $model = FormResponseRevision::class;

    public function definition(): array
    {
        return [
            'form_response_id' => FormResponse::factory(),
            'revision' => 1,
            'answer_payload' => '{}',
            'field_snapshot' => '[]',
            'created_by' => null,
            'created_at' => now(),
        ];
    }
}
