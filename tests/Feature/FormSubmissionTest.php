<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Enums\FormResponseStatus;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormLifecycleService;
use Modules\Forms\Services\FormResponseService;

it('stores an encrypted response and prevents duplicate anonymous identities', function (): void {
    $form = Form::factory()->create([
        'access_mode' => FormAccessMode::GuestIdentifier,
        'identity_type' => 'email',
    ]);
    $form->fields()->create([
        'field_key' => 'origin',
        'label' => 'Origin',
        'type' => 'text',
        'required' => true,
        'position' => 1,
    ]);
    $form->load('fields');

    $service = app(FormResponseService::class);
    $response = $service->submit($form, [
        'respondent_email' => ' STUDENT@EXAMPLE.COM ',
        'answers' => ['origin' => 'Cebu'],
    ]);

    expect($response->status)->toBe(FormResponseStatus::Submitted)
        ->and($response->respondent_email)->toBe('student@example.com')
        ->and($service->latestAnswers($response))->toBe(['origin' => 'Cebu']);

    expect(fn (): mixed => $service->submit($form, [
        'respondent_email' => 'student@example.com',
        'answers' => ['origin' => 'Davao'],
    ]))->toThrow(ValidationException::class);

    expect($form->responses()->count())->toBe(1);
    $this->assertDatabaseCount('form_response_revisions', 1);
});

it('allows a configured identity to submit a new revision', function (): void {
    $form = Form::factory()->create([
        'access_mode' => FormAccessMode::GuestIdentifier,
        'identity_type' => 'email',
        'settings' => ['allow_resubmit' => true],
    ]);
    $form->fields()->create([
        'field_key' => 'answer',
        'label' => 'Answer',
        'type' => 'text',
        'required' => true,
        'position' => 1,
    ]);
    $form->load('fields');

    $service = app(FormResponseService::class);
    $first = $service->submit($form, [
        'respondent_email' => 'student@example.com',
        'answers' => ['answer' => 'First'],
    ]);
    $second = $service->submit($form, [
        'respondent_email' => 'student@example.com',
        'answers' => ['answer' => 'Second'],
    ]);

    expect($second->getKey())->toBe($first->getKey())
        ->and($second->latest_revision)->toBe(2)
        ->and($service->latestAnswers($second))->toBe(['answer' => 'Second']);

    $this->assertDatabaseCount('form_responses', 1);
    $this->assertDatabaseCount('form_response_revisions', 2);
});

it('only persists mappings exposed by the host model registry', function (): void {
    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->with('student')->andReturn([
        ['write_paths' => ['student.first_name']],
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $form = app(FormLifecycleService::class)->create([
        'title' => 'Student update',
        'slug' => 'student-update',
        'access_mode' => FormAccessMode::Authenticated->value,
        'fields' => [[
            'field_key' => 'name',
            'label' => 'Name',
            'type' => 'text',
            'mapping' => ['model' => 'student', 'path' => 'student.password'],
        ]],
    ], null);

    expect($form->fields->first()->mapping)->toBeNull();
});

it('registers the public form routes', function (): void {
    expect(route('forms.show', ['form' => 'student-update']))->toEndWith('/forms/student-update')
        ->and(route('forms.submit', ['form' => 'student-update']))->toEndWith('/forms/student-update/responses');
});
