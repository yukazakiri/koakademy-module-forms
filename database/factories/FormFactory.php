<?php

declare(strict_types=1);

namespace Modules\Forms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Enums\FormStatus;
use Modules\Forms\Models\Form;

final class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'tenant_key' => null,
            'created_by' => null,
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->optional()->paragraph(),
            'status' => FormStatus::Published,
            'access_mode' => FormAccessMode::Anonymous,
            'identity_type' => null,
            'settings' => [],
            'version' => 1,
            'published_at' => now(),
            'closes_at' => null,
        ];
    }
}
