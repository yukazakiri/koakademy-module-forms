<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Modules\Forms\Enums\FormStatus;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormDefinitionService;

it('provides safe defaults for yes or no fields and strips hidden answers', function (): void {
    $form = Form::factory()->create(['status' => FormStatus::Published]);
    $form->fields()->createMany([
        [
            'field_key' => 'has_guardian',
            'label' => 'Has guardian',
            'type' => 'yes_no',
            'required' => true,
            'position' => 1,
            'options' => [],
        ],
        [
            'field_key' => 'guardian_name',
            'label' => 'Guardian name',
            'type' => 'text',
            'required' => false,
            'position' => 2,
            'visibility' => ['field' => 'has_guardian', 'operator' => 'equals', 'value' => 'yes'],
        ],
    ]);
    $form->load('fields');

    $service = app(FormDefinitionService::class);
    $payload = $service->publicPayload($form);
    $rules = $service->validationRules($form);
    $validator = Validator::make([
        'answers' => ['has_guardian' => 'yes', 'guardian_name' => 'Maria'],
    ], $rules);

    expect($payload['fields'][0]['options'])->toBe(['yes' => 'Yes', 'no' => 'No'])
        ->and($validator->passes())->toBeTrue()
        ->and($service->normalizeAnswers($form, [
            'has_guardian' => 'no',
            'guardian_name' => 'Should be ignored',
        ]))->toBe(['has_guardian' => 'no']);
});
