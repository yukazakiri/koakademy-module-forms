<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Enums\FormResponseStatus;
use Modules\Forms\Enums\FormStatus;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormMappingService;
use Modules\Forms\Services\FormResponseService;

it('verifies both guest identity values and returns only approved mapped answers', function (): void {
    $record = (object) ['id' => 'student-1'];
    $models = Mockery::mock(FormsModelRegistry::class);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'student_id', 'student-1')->once()->andReturn($record);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'email', 'student@example.test')->once()->andReturn($record);
    $models->shouldReceive('read')->with($record, 'student.phone')->once()->andReturn('09170000000');
    app()->instance(FormsModelRegistry::class, $models);

    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::GuestIdentifier,
        'identity_type' => 'student_id',
    ]);
    $form->fields()->createMany([
        [
            'field_key' => 'phone',
            'label' => 'Phone',
            'type' => 'phone',
            'mapping' => ['model' => 'student', 'path' => 'student.phone'],
            'position' => 1,
        ],
        [
            'field_key' => 'notes',
            'label' => 'Notes',
            'type' => 'text',
            'position' => 2,
        ],
    ]);

    $response = $this->postJson(route('forms.identify', ['form' => $form->slug]), [
        'respondent_identifier' => ' STUDENT-1 ',
        'respondent_email' => ' STUDENT@EXAMPLE.TEST ',
    ]);

    $response->assertOk()
        ->assertJson([
            'matched' => true,
            'answers' => ['phone' => '09170000000'],
        ]);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('rejects a guest identity when the student ID and email belong to different records', function (): void {
    $student = (object) ['id' => 'student-1'];
    $otherStudent = (object) ['id' => 'student-2'];
    $models = Mockery::mock(FormsModelRegistry::class);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'student_id', 'student-1')->once()->andReturn($student);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'email', 'student@example.test')->once()->andReturn($otherStudent);
    app()->instance(FormsModelRegistry::class, $models);

    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::GuestIdentifier,
        'identity_type' => 'student_id',
    ]);
    $form->fields()->create([
        'field_key' => 'phone',
        'label' => 'Phone',
        'type' => 'phone',
        'position' => 1,
    ]);

    $response = $this->postJson(route('forms.identify', ['form' => $form->slug]), [
        'respondent_identifier' => 'student-1',
        'respondent_email' => 'student@example.test',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('errors.respondent_identifier.0', 'We could not verify that Student ID and registered email combination.');
});

it('rechecks guest identity before storing a linked response', function (): void {
    $student = new class
    {
        public string $id = 'student-1';

        public function getKey(): string
        {
            return $this->id;
        }
    };
    $otherStudent = new class
    {
        public string $id = 'student-2';

        public function getKey(): string
        {
            return $this->id;
        }
    };
    $models = Mockery::mock(FormsModelRegistry::class);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'student_id', 'student-1')->once()->andReturn($student);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'email', 'student@example.test')->once()->andReturn($otherStudent);
    app()->instance(FormsModelRegistry::class, $models);

    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::GuestIdentifier,
        'identity_type' => 'student_id',
    ]);
    $form->fields()->create([
        'field_key' => 'phone',
        'label' => 'Phone',
        'type' => 'phone',
        'mapping' => ['model' => 'student', 'path' => 'student.phone'],
        'position' => 1,
    ]);

    expect(fn (): mixed => app(FormResponseService::class)->submit(
        $form->load('fields'),
        [
            'respondent_identifier' => 'student-1',
            'respondent_email' => 'student@example.test',
            'answers' => ['phone' => '09170000000'],
        ],
    ))->toThrow(ValidationException::class);

    expect($form->responses()->count())->toBe(0);
});

it('stores an unmatched guest response for manual review when enabled', function (): void {
    $models = Mockery::mock(FormsModelRegistry::class);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'student_id', 'unknown-student')->once()->andReturn(null);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'email', 'unknown@example.test')->once()->andReturn(null);
    app()->instance(FormsModelRegistry::class, $models);

    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::GuestIdentifier,
        'identity_type' => 'student_id',
        'settings' => [
            'allow_unverified_guest_response' => true,
            'mapping_mode' => 'auto_fill_empty',
        ],
    ]);
    $form->fields()->create([
        'field_key' => 'phone',
        'label' => 'Phone',
        'type' => 'phone',
        'mapping' => ['model' => 'student', 'path' => 'student.phone'],
        'position' => 1,
    ]);
    $form->load('fields');

    $response = app(FormResponseService::class)->submit($form, [
        'respondent_identifier' => 'unknown-student',
        'respondent_email' => 'unknown@example.test',
        'respondent_identity_unverified' => true,
        'answers' => ['phone' => '09170000000'],
    ]);

    $link = $response->links->first();

    expect($response->status)->toBe(FormResponseStatus::Submitted)
        ->and($link->status)->toBe('unmatched')
        ->and($link->match_method)->toBe('guest_unverified')
        ->and($link->model_id)->toBeNull()
        ->and(app(FormResponseService::class)->latestAnswers($response))->toBe(['phone' => '09170000000']);
    expect(fn (): mixed => app(FormMappingService::class)->apply($response->load('form.fields', 'links')))->toThrow(ValidationException::class);
});

it('rejects the manual review flag when the form does not allow unmatched responses', function (): void {
    $models = Mockery::mock(FormsModelRegistry::class);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'student_id', 'unknown-student')->once()->andReturn(null);
    $models->shouldReceive('resolveByIdentifier')->with('student', 'email', 'unknown@example.test')->once()->andReturn(null);
    app()->instance(FormsModelRegistry::class, $models);

    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::GuestIdentifier,
        'identity_type' => 'student_id',
    ]);
    $form->fields()->create([
        'field_key' => 'phone',
        'label' => 'Phone',
        'type' => 'phone',
        'mapping' => ['model' => 'student', 'path' => 'student.phone'],
        'position' => 1,
    ]);
    $form->load('fields');

    expect(fn (): mixed => app(FormResponseService::class)->submit($form, [
        'respondent_identifier' => 'unknown-student',
        'respondent_email' => 'unknown@example.test',
        'respondent_identity_unverified' => true,
        'answers' => ['phone' => '09170000000'],
    ]))->toThrow(ValidationException::class);

    expect($form->responses()->count())->toBe(0);
});
