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

it('rejects future dates when a date field is configured to require today or earlier', function (): void {
    $form = Form::factory()->create(['status' => FormStatus::Published]);
    $form->fields()->create([
        'field_key' => 'birth_date',
        'label' => 'Birth date',
        'type' => 'date',
        'required' => true,
        'position' => 1,
        'validation' => ['before_or_equal' => 'today'],
    ]);
    $form->load('fields');

    $validator = Validator::make([
        'answers' => ['birth_date' => now()->addDay()->toDateString()],
    ], app(FormDefinitionService::class)->validationRules($form));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('answers.birth_date'))->toBeTrue();
});

it('keeps sectioned fields in their saved order for page-based clients', function (): void {
    $form = Form::factory()->create(['status' => FormStatus::Published]);
    $form->fields()->createMany([
        ['field_key' => 'first_name', 'label' => 'First name', 'type' => 'text', 'section' => 'Identity', 'position' => 1],
        ['field_key' => 'email', 'label' => 'Email', 'type' => 'email', 'section' => 'Contact', 'position' => 2],
        ['field_key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'section' => 'Contact', 'position' => 3],
    ]);
    $form->load('fields');

    $fields = app(FormDefinitionService::class)->publicPayload($form)['fields'];

    expect(array_column($fields, 'key'))->toBe(['first_name', 'email', 'phone'])
        ->and(array_column($fields, 'section'))->toBe(['Identity', 'Contact', 'Contact']);
});

it('rejects invalid select option values', function (): void {
    $form = Form::factory()->create(['status' => FormStatus::Published]);
    $form->fields()->create([
        'field_key' => 'family_income_bracket',
        'label' => 'Family income bracket',
        'type' => 'select',
        'required' => true,
        'position' => 1,
        'options' => [
            'below_250k' => '₱250,000 and below',
            'above_8m' => 'Above ₱8,000,000',
        ],
        'presentation' => ['control' => 'select'],
    ]);
    $form->load('fields');

    $validator = Validator::make([
        'answers' => ['family_income_bracket' => 'not_configured'],
    ], app(FormDefinitionService::class)->validationRules($form));

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('answers.family_income_bracket'))->toBeTrue();
});
