<?php

declare(strict_types=1);

namespace Modules\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormField;

final class FormFieldFactory extends Factory
{
    protected $model = FormField::class;

    public function definition(): array
    {
        return [
            'form_id' => Form::factory(),
            'field_key' => 'question_'.fake()->unique()->numberBetween(1, 99999),
            'label' => fake()->sentence(4),
            'type' => 'text',
            'description' => null,
            'required' => false,
            'position' => 1,
            'options' => [],
            'validation' => [],
            'visibility' => null,
            'mapping' => null,
            'is_sensitive' => false,
        ];
    }
}
