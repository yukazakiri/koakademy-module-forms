<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Enums\FormResponseStatus;
use Modules\Forms\Enums\FormStatus;
use Modules\Forms\Http\Controllers\FormAdminController;
use Modules\Forms\Jobs\SendFormInvitation;
use Modules\Forms\Mail\FormInvitationMail;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormAuditEvent;
use Modules\Forms\Models\FormInvitation;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Models\FormTemplate;
use Modules\Forms\Services\FormAnswerService;
use Modules\Forms\Services\FormInvitationService;
use Modules\Forms\Services\FormLifecycleService;
use Modules\Forms\Services\FormMappingService;
use Modules\Forms\Services\FormResponseService;
use Modules\Forms\Services\FormTemplateService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

it('renders invitation forms through the authenticated admin preview route', function (): void {
    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::Invitation,
    ]);

    $form->fields()->create([
        'field_key' => 'first_name',
        'label' => 'First name',
        'type' => 'text',
        'required' => true,
        'position' => 1,
    ]);

    $request = Request::create(route('administrators.forms.preview', ['form' => $form]), 'GET');
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn (): object => (object) [
        'id' => 7,
        'name' => 'Administrator',
        'email' => 'admin@example.test',
        'is_super_admin' => true,
    ]);

    $httpResponse = app(FormAdminController::class)->preview($request, $form)->toResponse($request);
    $payload = json_decode($httpResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($httpResponse->getStatusCode())->toBe(200)
        ->and($payload['component'])->toBe('Forms/PublicShow')
        ->and($payload['props']['form']['access_mode'])->toBe('invitation')
        ->and($payload['props']['preview'])->toBeTrue();
});

it('generates the built-in student template from approved host fields', function (): void {
    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->with('student')->andReturn([
        [
            'key' => 'birthplace',
            'label' => 'Birthplace',
            'type' => 'string',
            'group' => 'Personal',
            'write_paths' => ['details.birthplace'],
            'suggestible' => true,
        ],
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $definition = app(FormTemplateService::class)->definition('student_profile_completion');

    expect($definition)
        ->toHaveKey('fields')
        ->and($definition['settings']['mapping_mode'])->toBe('auto_fill_empty')
        ->and($definition['settings']['allow_unverified_guest_response'])->toBeTrue()
        ->and($definition['fields'][0]['mapping'])->toBe(['model' => 'student', 'path' => 'details.birthplace'])
        ->and($definition['fields'][0]['presentation']['control'])->toBe('combobox');
});

it('caches approved mapping paths while creating a form', function (): void {
    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->once()->with('student')->andReturn([
        ['write_paths' => ['details.first_name', 'details.last_name']],
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $form = app(FormLifecycleService::class)->create([
        'title' => 'Student profile completion',
        'slug' => 'student-profile-completion-test',
        'description' => null,
        'access_mode' => FormAccessMode::Invitation->value,
        'identity_type' => null,
        'settings' => [],
        'fields' => [
            [
                'field_key' => 'first_name',
                'label' => 'First name',
                'type' => 'text',
                'mapping' => ['model' => 'student', 'path' => 'details.first_name'],
            ],
            [
                'field_key' => 'last_name',
                'label' => 'Last name',
                'type' => 'text',
                'mapping' => ['model' => 'student', 'path' => 'details.last_name'],
            ],
        ],
    ], (object) ['id' => 7]);

    expect($form->fields)->toHaveCount(2)
        ->and($form->fields->pluck('mapping')->all())->toBe([
            ['model' => 'student', 'path' => 'details.first_name'],
            ['model' => 'student', 'path' => 'details.last_name'],
        ]);
});

it('clones a custom template without sharing its database identity', function (): void {
    $form = Form::factory()->create();
    $form->fields()->create([
        'field_key' => 'favorite_color',
        'label' => 'Favorite color',
        'type' => 'select',
        'options' => ['blue' => 'Blue'],
        'position' => 1,
    ]);

    $template = app(FormTemplateService::class)->createFromForm($form->load('fields'), 'Colors', (object) ['id' => 7]);
    $copy = app(FormTemplateService::class)->duplicate($template, 'Colors copy', (object) ['id' => 8]);

    expect($copy->getKey())->not->toBe($template->getKey())
        ->and($copy->definition)->toEqual($template->definition)
        ->and(FormTemplate::query()->count())->toBe(2);
});

it('queues explicit invitation batches, revokes resends, and rejects expired links', function (): void {
    Queue::fake();
    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::Invitation,
        'settings' => ['invitation_expiry_days' => 30],
    ]);
    $form->fields()->create([
        'field_key' => 'phone',
        'label' => 'Phone',
        'type' => 'phone',
        'required' => true,
        'behavior' => ['missing_only' => true],
        'mapping' => ['model' => 'student', 'path' => 'student.phone'],
        'position' => 1,
    ]);

    $provider = Mockery::mock(FormsInvitationTargetProvider::class);
    $provider->shouldReceive('candidates')->with(Mockery::type(Form::class))->andReturn([
        ['model_key' => 'student', 'model_type' => 'Student', 'model_id' => '42', 'email' => 'student@example.test'],
    ]);
    $provider->shouldReceive('resolve')->andReturn((object) ['full_name' => 'Test Student', 'student_id' => 2026001]);
    app()->instance(FormsInvitationTargetProvider::class, $provider);

    $service = app(FormInvitationService::class);
    $first = $service->send($form->load('fields'), ['42'], (object) ['id' => 99]);
    $firstInvitation = FormInvitation::query()->firstOrFail();
    $oldHash = $firstInvitation->token_hash;

    $second = $service->send($form->fresh('fields'), ['42'], (object) ['id' => 99]);
    $invitations = FormInvitation::query()->orderBy('created_at')->get();

    expect($first['created'])->toBe(1)
        ->and($second['created'])->toBe(1)
        ->and($invitations[0]->status)->toBe('revoked')
        ->and($invitations[1]->status)->toBe('pending')
        ->and($invitations[1]->token_hash)->not->toBe($oldHash);

    Queue::assertPushed(SendFormInvitation::class, 2);

    $expired = FormInvitation::factory()->create([
        'form_id' => $form->getKey(),
        'model_key' => 'student',
        'model_id' => '43',
        'token_hash' => FormInvitation::tokenHash('expired-token'),
        'recipient_email' => 'expired@example.test',
        'expires_at' => now()->subMinute(),
    ]);

    expect(fn (): FormInvitation => $service->resolve($form, 'expired-token'))->toThrow(NotFoundHttpException::class);
    expect($expired->refresh()->status)->toBe('pending');

    $completedToken = FormInvitation::newToken();
    $invitations[1]->update([
        'status' => 'completed',
        'completed_at' => now(),
        'token_hash' => FormInvitation::tokenHash($completedToken),
    ]);
    expect(fn (): FormInvitation => $service->resolve($form->fresh(), $completedToken))->toThrow(NotFoundHttpException::class);
});

it('delivers only pending links and marks the invitation sent', function (): void {
    Mail::fake();
    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::Invitation,
    ]);
    $token = FormInvitation::newToken();
    $invitation = FormInvitation::factory()->create([
        'form_id' => $form->getKey(),
        'token_hash' => FormInvitation::tokenHash($token),
        'status' => 'pending',
        'expires_at' => now()->addDays(30),
    ]);

    (new SendFormInvitation((string) $invitation->getKey(), $token))->handle();

    Mail::assertSent(FormInvitationMail::class);
    expect($invitation->refresh()->status)->toBe('sent')
        ->and($invitation->sent_at)->not->toBeNull();
});

it('binds invitation responses to the invitation record and auto-fills blanks', function (): void {
    $record = new class
    {
        public int $id = 42;

        public function getKey(): int
        {
            return $this->id;
        }
    };
    $models = Mockery::mock(FormsModelRegistry::class);
    $values = ['student.phone' => ''];
    $models->shouldReceive('resolveById')->with('student', '42')->andReturn($record);
    $models->shouldReceive('read')->andReturnUsing(fn (object $record, string $path): mixed => $values[$path] ?? null);
    $models->shouldReceive('write')->andReturnUsing(function (object $record, string $path, mixed $value) use (&$values): void {
        $values[$path] = $value;
    });
    $models->shouldReceive('persist')->once();
    app()->instance(FormsModelRegistry::class, $models);

    $targets = Mockery::mock(FormsInvitationTargetProvider::class);
    $targets->shouldReceive('resolve')->andReturn($record);
    app()->instance(FormsInvitationTargetProvider::class, $targets);

    $form = Form::factory()->create([
        'status' => FormStatus::Published,
        'access_mode' => FormAccessMode::Invitation,
        'settings' => ['mapping_mode' => 'auto_fill_empty'],
    ]);
    $form->fields()->create([
        'field_key' => 'phone',
        'label' => 'Phone',
        'type' => 'phone',
        'required' => true,
        'mapping' => ['model' => 'student', 'path' => 'student.phone'],
        'position' => 1,
    ]);
    $token = FormInvitation::newToken();
    $invitation = FormInvitation::factory()->create([
        'form_id' => $form->getKey(),
        'model_key' => 'student',
        'model_id' => '42',
        'token_hash' => FormInvitation::tokenHash($token),
        'recipient_email' => 'student@example.test',
        'expires_at' => now()->addDays(30),
    ]);

    $response = app(FormResponseService::class)->submit(
        $form->load('fields'),
        ['answers' => ['phone' => '09170000000'], 'respondent_email' => 'attacker@example.test'],
        null,
        $invitation,
    );

    expect($values['student.phone'])->toBe('09170000000')
        ->and($response->respondent_email)->toBe('student@example.test')
        ->and($response->links->first()->model_id)->toBe('42')
        ->and($invitation->refresh()->status)->toBe('completed');
});

it('applies only blank mapped fields and audits skipped populated fields', function (): void {
    $values = ['student.first_name' => '', 'student.phone' => 'already populated'];
    $record = (object) ['id' => 7];
    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('resolveById')->with('student', '7')->andReturn($record);
    $registry->shouldReceive('read')->andReturnUsing(fn (object $record, string $path): mixed => $values[$path] ?? null);
    $registry->shouldReceive('write')->andReturnUsing(function (object $record, string $path, mixed $value) use (&$values): void {
        $values[$path] = $value;
    });
    $registry->shouldReceive('persist')->once();
    app()->instance(FormsModelRegistry::class, $registry);

    $form = Form::factory()->create(['settings' => ['mapping_mode' => 'auto_fill_empty']]);
    $form->fields()->createMany([
        ['field_key' => 'first_name', 'label' => 'First name', 'type' => 'text', 'mapping' => ['model' => 'student', 'path' => 'student.first_name'], 'position' => 1],
        ['field_key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'mapping' => ['model' => 'student', 'path' => 'student.phone'], 'position' => 2],
    ]);
    $response = FormResponse::query()->create([
        'form_id' => $form->getKey(),
        'status' => FormResponseStatus::Submitted,
        'latest_revision' => 1,
    ]);
    $response->revisions()->create([
        'revision' => 1,
        'answer_payload' => app(FormAnswerService::class)->encrypt(['first_name' => 'New name', 'phone' => 'new phone']),
        'field_snapshot' => '[]',
        'created_at' => now(),
    ]);
    $response->links()->create(['model_key' => 'student', 'model_id' => '7', 'status' => 'pending']);

    $result = app(FormMappingService::class)->apply($response->load('form.fields', 'links'), false);
    $audit = FormAuditEvent::query()->where('form_id', $form->getKey())->latest('created_at')->first();

    expect($result->status)->toBe(FormResponseStatus::Applied)
        ->and($values['student.first_name'])->toBe('New name')
        ->and($values['student.phone'])->toBe('already populated')
        ->and($audit?->metadata['fields_skipped'])->toContain('phone');
});
