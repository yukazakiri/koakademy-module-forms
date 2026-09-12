<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Modules\Forms\Enums\FormStatus;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormDefinitionService;
use Modules\Forms\Services\KoAkademyFormsModelRegistry;

it('maps yes and no answers to booleans and records shared or separate income semantics', function (): void {
    $record = new class extends Model
    {
        protected function casts(): array
        {
            return ['is_solo_parent_dependent' => 'boolean', 'use_same_parent_income' => 'boolean'];
        }
    };
    $registry = new KoAkademyFormsModelRegistry;
    $registry->write($record, 'student.is_solo_parent_dependent', 'no');
    expect($record->is_solo_parent_dependent)->toBeFalse();
    $registry->write($record, 'student.is_solo_parent_dependent', 'yes');
    expect($record->is_solo_parent_dependent)->toBeTrue();
    config()->set('income_brackets.default_mode', 'annual');
    $registry->write($record, 'student.family_income_bracket', 'below_250k');
    expect($record->income_bracket_mode)->toBe('annual')
        ->and($record->use_same_parent_income)->toBeTrue()
        ->and($record->father_income_bracket)->toBeNull()
        ->and($record->mother_income_bracket)->toBeNull();
    $registry->write($record, 'student.father_income_bracket', 'below_250k');
    expect($record->use_same_parent_income)->toBeFalse();
    $registry->write($record, 'student.family_income_bracket', null);
    expect($record->use_same_parent_income)->toBeFalse();
});

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

it('prefers exact option keys before matching option labels during normalization', function (): void {
    $form = Form::factory()->create(['status' => FormStatus::Published]);
    $form->fields()->create([
        'field_key' => 'choice_field',
        'label' => 'Choice Field',
        'type' => 'select',
        'position' => 1,
        'options' => [
            'first' => 'second',
            'second' => 'Second choice',
        ],
        'presentation' => ['control' => 'select'],
    ]);
    $form->load('fields');

    $normalized = app(FormDefinitionService::class)->normalizeAnswers($form, [
        'choice_field' => 'second',
    ]);

    expect($normalized['choice_field'])->toBe('second');
});
