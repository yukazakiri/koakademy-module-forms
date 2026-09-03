<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'key' => 'instagram',
            'label' => 'Instagram',
            'type' => 'string',
            'group' => 'Contact',
            'write_paths' => ['details.instagram'],
        ],
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
        ->and(collect($definition['fields'])->pluck('field_key')->all())->not->toContain('instagram')
        ->and($definition['fields'][0]['mapping'])->toBe(['model' => 'student', 'path' => 'details.birthplace'])
        ->and($definition['fields'][0]['description'])->not->toBeNull()
        ->and($definition['fields'][0]['presentation']['placeholder'])->toBe('e.g. Quezon City, Metro Manila')
        ->and($definition['fields'][0]['presentation']['control'])->toBe('combobox');
});

it('uses smart field types for built-in student profile fields', function (): void {
    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->with('student')->andReturn([
        [
            'key' => 'birth_date',
            'label' => 'Birth Date',
            'type' => 'date',
            'group' => 'Identity',
            'write_paths' => ['student.birth_date'],
        ],
        [
            'key' => 'current_address',
            'label' => 'Current Address',
            'type' => 'string',
            'group' => 'Address',
            'write_paths' => ['student.address'],
        ],
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $fields = app(FormTemplateService::class)->definition('student_profile_completion')['fields'];

    expect($fields[0]['type'])->toBe('date')
        ->and($fields[0]['validation'])->toBe(['before_or_equal' => 'today'])
        ->and($fields[1]['type'])->toBe('textarea');
});

it('generates income bracket fields as selects from configured brackets', function (): void {
    config()->set('income_brackets', [
        'default_mode' => 'annual',
        'modes' => [
            'annual' => [
                'brackets' => [
                    'below_250k' => ['label' => '{symbol}250,000 and below'],
                    '250001_to_400k' => ['label' => '{symbol}250,001 - {symbol}400,000'],
                ],
            ],
        ],
    ]);

    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->with('student')->andReturn([
        [
            'key' => 'family_income_bracket',
            'label' => 'Family income bracket',
            'type' => 'string',
            'group' => 'Family',
            'write_paths' => ['details.family_income_bracket'],
        ],
        [
            'key' => 'father_income_bracket',
            'label' => 'Father income bracket',
            'type' => 'string',
            'group' => 'Family',
            'write_paths' => ['details.father_income_bracket'],
        ],
        [
            'key' => 'mother_income_bracket',
            'label' => 'Mother income bracket',
            'type' => 'string',
            'group' => 'Family',
            'write_paths' => ['details.mother_income_bracket'],
        ],
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $definition = app(FormTemplateService::class)->definition('student_profile_completion');

    expect(collect($definition['fields'])->pluck('type', 'field_key')->all())->toBe([
        'family_income_bracket' => 'select',
        'father_income_bracket' => 'select',
        'mother_income_bracket' => 'select',
    ])->and(collect($definition['fields'])->pluck('options', 'field_key')->all())->each->toBe([
        'below_250k' => '₱250,000 and below',
        '250001_to_400k' => '₱250,001 - ₱400,000',
    ])->and(collect($definition['fields'])->pluck('presentation.control', 'field_key')->all())->toBe([
        'family_income_bracket' => 'select',
        'father_income_bracket' => 'select',
        'mother_income_bracket' => 'select',
    ])->and($definition['fields'][0]['mapping'])->toBe(['model' => 'student', 'path' => 'details.family_income_bracket']);
});

it('keeps provided income options when bracket config is unavailable', function (): void {
    config()->set('income_brackets', []);

    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->with('student')->andReturn([
        [
            'key' => 'family_income_bracket',
            'label' => 'Family income bracket',
            'type' => 'string',
            'options' => ['existing_key' => 'Existing bracket'],
            'write_paths' => ['details.family_income_bracket'],
        ],
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $definition = app(FormTemplateService::class)->definition('student_profile_completion');

    expect($definition['fields'][0]['type'])->toBe('select')
        ->and($definition['fields'][0]['options'])->toBe(['existing_key' => 'Existing bracket'])
        ->and($definition['fields'][0]['presentation']['control'])->toBe('select');
});

it('upgrades saved student profile income bracket field definitions only', function (): void {
    config()->set('income_brackets', [
        'default_mode' => 'annual',
        'modes' => [
            'annual' => [
                'brackets' => [
                    'below_250k' => ['label' => '{symbol}250,000 and below'],
                    'above_8m' => ['label' => 'Above {symbol}8,000,000'],
                ],
            ],
        ],
    ]);

    $form = Form::factory()->create(['settings' => ['template_key' => 'student_profile_completion']]);
    $otherForm = Form::factory()->create(['settings' => ['template_key' => 'custom']]);
    $form->fields()->createMany([
        ['field_key' => 'family_income_bracket', 'label' => 'Family income bracket', 'type' => 'text', 'options' => [], 'presentation' => ['control' => 'input'], 'mapping' => ['model' => 'student', 'path' => 'details.family_income_bracket'], 'position' => 1],
        ['field_key' => 'father_income_bracket', 'label' => 'Father income bracket', 'type' => 'text', 'options' => [], 'presentation' => ['control' => 'input'], 'mapping' => ['model' => 'student', 'path' => 'details.father_income_bracket'], 'position' => 2],
        ['field_key' => 'mother_income_bracket', 'label' => 'Mother income bracket', 'type' => 'text', 'options' => [], 'presentation' => ['control' => 'input'], 'mapping' => ['model' => 'student', 'path' => 'details.mother_income_bracket'], 'position' => 3],
        ['field_key' => 'first_name', 'label' => 'First name', 'type' => 'text', 'options' => [], 'presentation' => ['control' => 'input'], 'position' => 4],
    ]);
    $otherForm->fields()->create([
        'field_key' => 'family_income_bracket',
        'label' => 'Family income bracket',
        'type' => 'text',
        'options' => [],
        'presentation' => ['control' => 'input'],
        'position' => 1,
    ]);
    $response = FormResponse::query()->create([
        'form_id' => $form->getKey(),
        'status' => FormResponseStatus::Submitted,
        'latest_revision' => 1,
    ]);
    $revision = $response->revisions()->create([
        'revision' => 1,
        'answer_payload' => app(FormAnswerService::class)->encrypt(['family_income_bracket' => 'below_250k']),
        'field_snapshot' => '[]',
        'created_at' => now(),
    ]);
    $answerPayload = $revision->answer_payload;

    $migration = include dirname(__DIR__, 2).'/database/migrations/2026_09_03_000000_upgrade_student_profile_income_bracket_fields.php';
    $migration->up();

    $incomeFields = $form->fields()->whereIn('field_key', [
        'family_income_bracket',
        'father_income_bracket',
        'mother_income_bracket',
    ])->get()->keyBy('field_key');
    $unrelatedField = $form->fields()->where('field_key', 'first_name')->firstOrFail();
    $otherIncomeField = $otherForm->fields()->where('field_key', 'family_income_bracket')->firstOrFail();

    expect($incomeFields)->toHaveCount(3)
        ->and($incomeFields->pluck('type')->all())->toBe([
            'select',
            'select',
            'select',
        ])
        ->and($incomeFields->pluck('options')->all())->each->toBe([
            'below_250k' => '₱250,000 and below',
            'above_8m' => 'Above ₱8,000,000',
        ])
        ->and($incomeFields->pluck('presentation.control')->all())->toBe([
            'select',
            'select',
            'select',
        ])
        ->and($incomeFields['family_income_bracket']->mapping)->toBe(['model' => 'student', 'path' => 'details.family_income_bracket'])
        ->and($incomeFields['father_income_bracket']->mapping)->toBe(['model' => 'student', 'path' => 'details.father_income_bracket'])
        ->and($incomeFields['mother_income_bracket']->mapping)->toBe(['model' => 'student', 'path' => 'details.mother_income_bracket'])
        ->and($unrelatedField->type)->toBe('text')
        ->and($otherIncomeField->type)->toBe('text')
        ->and($revision->refresh()->answer_payload)->toBe($answerPayload)
        ->and(app(FormAnswerService::class)->latestAnswers($response->refresh()))->toBe(['family_income_bracket' => 'below_250k'])
        ->and(DB::table('form_response_revisions')->count())->toBe(1);
});

it('hydrates built-in profile help text for the editor when old forms have empty values', function (): void {
    $form = Form::factory()->create([
        'settings' => ['template_key' => 'student_profile_completion'],
    ]);
    $form->fields()->create([
        'field_key' => 'birthplace',
        'label' => 'Birthplace',
        'type' => 'text',
        'description' => null,
        'presentation' => [],
        'position' => 1,
    ]);

    $defaults = app(FormTemplateService::class)->studentProfileFieldDefaults('birthplace', 'text');

    expect($defaults['description'])->toContain('city or municipality')
        ->and($defaults['placeholder'])->toBe('e.g. Quezon City, Metro Manila');
});

it('shows fallback profile help text in the edit payload for existing forms', function (): void {
    $form = Form::factory()->create([
        'settings' => ['template_key' => 'student_profile_completion'],
    ]);
    $form->fields()->create([
        'field_key' => 'birthplace',
        'label' => 'Birthplace',
        'type' => 'text',
        'description' => null,
        'presentation' => [],
        'position' => 1,
    ]);

    $request = Request::create(route('administrators.forms.edit', ['form' => $form]), 'GET');
    $request->headers->set('X-Inertia', 'true');
    $request->setUserResolver(fn (): object => (object) [
        'id' => 7,
        'name' => 'Administrator',
        'email' => 'admin@example.test',
        'is_super_admin' => true,
    ]);

    $httpResponse = app(FormAdminController::class)->edit($request, $form)->toResponse($request);
    $payload = json_decode($httpResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);
    $field = $payload['props']['form']['fields'][0];

    expect($httpResponse->getStatusCode())->toBe(200)
        ->and($field['description'])->toContain('city or municipality')
        ->and($field['presentation']['placeholder'])->toBe('e.g. Quezon City, Metro Manila');
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
