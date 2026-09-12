<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Enums\FormResponseStatus;
use Modules\Forms\Enums\FormStatus;
use Modules\Forms\Http\Controllers\FormAdminController;
use Modules\Forms\Http\Controllers\PublicFormController;
use Modules\Forms\Jobs\SendFormInvitation;
use Modules\Forms\Mail\FormInvitationMail;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormAuditEvent;
use Modules\Forms\Models\FormInvitation;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Models\FormTemplate;
use Modules\Forms\Services\FormAnswerService;
use Modules\Forms\Services\FormDefinitionService;
use Modules\Forms\Services\FormInvitationService;
use Modules\Forms\Services\FormLifecycleService;
use Modules\Forms\Services\FormMappingService;
use Modules\Forms\Services\FormResponseService;
use Modules\Forms\Services\FormTemplateService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function reportingProfileDefinition(array $additionalKeys = []): array
{
    config()->set('income_brackets', [
        'default_mode' => 'annual',
        'modes' => ['annual' => ['label' => 'Annual Income', 'brackets' => [
            'below_250k' => ['label' => '{symbol}250,000 and below'],
            '250001_to_400k' => ['label' => '{symbol}250,001 - {symbol}400,000'],
        ]]],
    ]);
    $keys = array_values(array_unique([
        'father_name', 'father_occupation', 'father_contact', 'father_email', 'mother_name', 'mother_occupation', 'mother_contact', 'mother_email', 'guardian_name', 'guardian_relationship', 'guardian_contact', 'guardian_email', 'family_address', 'family_income_bracket', 'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_address', 'emergency_contact_relationship', 'is_solo_parent_dependent',
        ...$additionalKeys,
    ]));
    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->with('student')->andReturn(array_map(fn (string $key): array => [
        'key' => $key,
        'label' => $key,
        'type' => in_array($key, ['is_indigenous_person', 'is_pwd', 'is_solo_parent', 'is_solo_parent_dependent', 'is_senior_citizen', 'is_magna_carta', 'is_underprivileged', 'is_first_generation'], true)
            ? 'boolean'
            : ($key === 'gender' ? 'choice' : (str_ends_with($key, '_email') ? 'email' : 'string')),
        'options' => $key === 'gender' ? ['male' => 'Male'] : [],
        'write_paths' => ['student.'.$key],
    ], $keys));
    app()->instance(FormsModelRegistry::class, $registry);

    return app(FormTemplateService::class)->definition('student_profile_completion');
}

it('accepts a student profile with emergency contacts and no parent guardian or income details', function (): void {
    $definition = reportingProfileDefinition();
    $form = Form::factory()->create();
    foreach ($definition['fields'] as $position => $field) {
        $form->fields()->create([...$field, 'position' => $position]);
    }
    $answers = [
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_phone' => '09123456789',
        'emergency_contact_address' => 'Example City',
        'emergency_contact_relationship' => 'Sibling',
        'is_solo_parent_dependent' => 'no',
    ];
    $rules = app(FormDefinitionService::class)->validationRules($form->load('fields'));
    expect(Validator::make(['answers' => $answers], $rules)->passes())->toBeTrue();
    unset($answers['emergency_contact_phone']);
    expect(Validator::make(['answers' => $answers], $rules)->errors()->has('answers.emergency_contact_phone'))->toBeTrue();
});

it('keeps only the shared annual income answer', function (array $answers, array $expected): void {
    $definition = reportingProfileDefinition();
    $form = Form::factory()->create();
    foreach ($definition['fields'] as $position => $field) {
        if (str_ends_with($field['field_key'], '_income_bracket')) {
            $form->fields()->create([...$field, 'position' => $position]);
        }
    }
    $service = app(FormDefinitionService::class);
    $form->load('fields');
    expect(Validator::make(['answers' => $answers], $service->validationRules($form, answers: $answers))->passes())->toBeTrue()
        ->and($service->normalizeAnswers($form, $answers))->toBe($expected);
    $response = app(FormResponseService::class)->submit($form, ['answers' => $answers]);
    expect(app(FormAnswerService::class)->latestAnswers($response))->toBe($expected);
})->with([
    'shared' => [['family_income_bracket' => 'below_250k'], ['family_income_bracket' => 'below_250k']],
    'unanswered' => [[], []],
]);

it('rejects arbitrary income text in the student template', function (): void {
    $definition = reportingProfileDefinition();
    $field = collect($definition['fields'])->firstWhere('field_key', 'family_income_bracket');
    $form = Form::factory()->create();
    $form->fields()->create([...$field, 'position' => 1]);
    $validator = Validator::make(['answers' => ['family_income_bracket' => 'some income']], app(FormDefinitionService::class)->validationRules($form->load('fields')));
    expect($validator->errors()->has('answers.family_income_bracket'))->toBeTrue();
});

it('upgrades existing profile forms and saved templates without changing responses or unrelated forms', function (): void {
    reportingProfileDefinition();
    $form = Form::factory()->create(['settings' => ['template_key' => 'student_profile_completion']]);
    $fields = [
        ['field_key' => 'guardian_name', 'label' => 'Custom guardian label', 'type' => 'text', 'required' => true, 'position' => 1],
        ['field_key' => 'family_income_bracket', 'label' => 'Family income', 'type' => 'text', 'position' => 2, 'presentation' => ['unit' => 'custom'], 'mapping' => ['model' => 'student', 'path' => 'student.family_income_bracket']],
    ];
    $form->fields()->createMany($fields);
    $other = Form::factory()->create();
    $other->fields()->createMany($fields);
    $template = FormTemplate::query()->create(['name' => 'Saved profile', 'model_key' => 'student', 'definition' => ['settings' => $form->settings, 'fields' => $fields]]);
    $response = app(FormResponseService::class)->submit($form, ['answers' => ['family_income_bracket' => 'legacy text']]);
    $migration = include dirname(__DIR__, 2).'/database/migrations/2026_09_06_145634_update_student_profile_form_reporting_fields.php';
    $migration->up();
    $migration->up();
    $updated = $form->fresh('fields')->fields->keyBy('field_key');
    expect($updated['guardian_name']->required)->toBeFalse()
        ->and($updated['guardian_name']->label)->toBe('Custom guardian label')
        ->and($updated['family_income_bracket']->type)->toBe('select')
        ->and($updated['family_income_bracket']->presentation['unit'])->toBe('custom')
        ->and($updated['family_income_bracket']->mapping['path'])->toBe('student.family_income_bracket')
        ->and($updated->has('is_solo_parent_dependent'))->toBeTrue()
        ->and($updated)->toHaveCount(3)
        ->and($other->fields()->where('field_key', 'guardian_name')->first()->required)->toBeTrue()
        ->and($other->fields()->where('field_key', 'family_income_bracket')->first()->type)->toBe('text')
        ->and(collect($template->fresh()->definition['fields'])->firstWhere('field_key', 'guardian_name')['required'])->toBeFalse()
        ->and(app(FormAnswerService::class)->latestAnswers($response->fresh()))->toBe(['family_income_bracket' => 'legacy text']);
});

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
        ->and($payload['props']['hideMobileNavigation'])->toBeTrue()
        ->and($payload['props']['preview'])->toBeTrue();
});

it('marks public form pages to hide the app mobile navigation', function (): void {
    $form = Form::factory()->create([
        'access_mode' => FormAccessMode::Anonymous,
    ]);

    $request = Request::create(route('forms.show', ['form' => $form]), 'GET');
    $request->headers->set('X-Inertia', 'true');

    $httpResponse = app(PublicFormController::class)->show($request, $form)->toResponse($request);
    $payload = json_decode($httpResponse->getContent(), true, flags: JSON_THROW_ON_ERROR);

    expect($httpResponse->getStatusCode())->toBe(200)
        ->and($payload['component'])->toBe('Forms/PublicShow')
        ->and($payload['props']['hideMobileNavigation'])->toBeTrue();
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

it('generates one annual income bracket field as a select from configured brackets', function (): void {
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
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $definition = app(FormTemplateService::class)->definition('student_profile_completion');

    expect(collect($definition['fields'])->pluck('type', 'field_key')->all())->toBe([
        'family_income_bracket' => 'select',
    ])->and(collect($definition['fields'])->pluck('options', 'field_key')->all())->each->toBe([
        'below_250k' => '₱250,000 and below',
        '250001_to_400k' => '₱250,001 - ₱400,000',
    ])->and(collect($definition['fields'])->pluck('presentation.control', 'field_key')->all())->toBe([
        'family_income_bracket' => 'select',
    ])->and($definition['fields'][0]['mapping'])->toBe(['model' => 'student', 'path' => 'details.family_income_bracket']);
});

it('generates the four gender choices and all approved equity fields', function (): void {
    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->with('student')->andReturn([
        ['key' => 'gender', 'label' => 'Gender', 'type' => 'choice', 'group' => 'Identity', 'options' => ['male' => 'Male'], 'write_paths' => ['student.gender']],
        ...array_map(fn (string $key): array => [
            'key' => $key,
            'label' => $key,
            'type' => in_array($key, ['is_indigenous_person', 'is_pwd', 'is_solo_parent', 'is_solo_parent_dependent', 'is_senior_citizen', 'is_magna_carta', 'is_underprivileged', 'is_first_generation'], true) ? 'boolean' : 'string',
            'group' => 'Origin and Equity',
            'write_paths' => ['student.'.$key],
        ], ['ethnicity', 'region_of_origin', 'province_of_origin', 'city_of_origin', 'is_indigenous_person', 'indigenous_group', 'is_pwd', 'pwd_type', 'is_solo_parent', 'is_solo_parent_dependent', 'is_senior_citizen', 'is_magna_carta', 'is_underprivileged', 'is_first_generation']),
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $fields = collect(app(FormTemplateService::class)->definition('student_profile_completion')['fields'])->keyBy('field_key');

    expect($fields['gender']['options'])->toBe(FormTemplateService::GENDER_OPTIONS)
        ->and($fields['gender']['type'])->toBe('select')
        ->and($fields->keys()->intersect([
            'ethnicity', 'region_of_origin', 'province_of_origin', 'city_of_origin',
            'is_indigenous_person', 'indigenous_group', 'is_pwd', 'pwd_type',
            'is_solo_parent', 'is_solo_parent_dependent', 'is_senior_citizen',
            'is_magna_carta', 'is_underprivileged', 'is_first_generation',
        ])->count())->toBe(14);
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

it('recommends dropdown select controls with standard options for supported student profile fields', function (): void {
    $registry = Mockery::mock(FormsModelRegistry::class);
    $registry->shouldReceive('fields')->with('student')->andReturn([
        [
            'key' => 'civil_status',
            'label' => 'Civil Status',
            'type' => 'string',
            'group' => 'Personal',
            'write_paths' => ['student.civil_status'],
        ],
        [
            'key' => 'nationality',
            'label' => 'Nationality / Citizenship',
            'type' => 'string',
            'group' => 'Personal',
            'write_paths' => ['student.nationality'],
        ],
        [
            'key' => 'region_of_origin',
            'label' => 'Region of Origin',
            'type' => 'string',
            'group' => 'Origin and Equity',
            'write_paths' => ['student.region_of_origin'],
        ],
        [
            'key' => 'religion',
            'label' => 'Religion',
            'type' => 'string',
            'group' => 'Personal',
            'write_paths' => ['student.religion'],
        ],
        [
            'key' => 'pwd_type',
            'label' => 'Disability Type',
            'type' => 'string',
            'group' => 'Origin and Equity',
            'write_paths' => ['student.pwd_type'],
        ],
        [
            'key' => 'emergency_contact_relationship',
            'label' => 'Emergency Contact Relationship',
            'type' => 'string',
            'group' => 'Emergency Contact',
            'write_paths' => ['contact.emergency_contact_relationship'],
        ],
        [
            'key' => 'guardian_relationship',
            'label' => 'Guardian Relationship',
            'type' => 'string',
            'group' => 'Parent and Guardian',
            'write_paths' => ['parent.guardian_relationship'],
        ],
    ]);
    app()->instance(FormsModelRegistry::class, $registry);

    $fields = collect(app(FormTemplateService::class)->definition('student_profile_completion')['fields'])->keyBy('field_key');

    expect($fields['civil_status']['type'])->toBe('select')
        ->and($fields['civil_status']['presentation']['control'])->toBe('select')
        ->and($fields['civil_status']['options'])->toHaveKey('single')
        ->and($fields['nationality']['type'])->toBe('select')
        ->and($fields['nationality']['presentation']['control'])->toBe('select')
        ->and($fields['nationality']['options'])->toHaveKey('filipino')
        ->and($fields['region_of_origin']['type'])->toBe('select')
        ->and($fields['region_of_origin']['presentation']['control'])->toBe('select')
        ->and($fields['region_of_origin']['options'])->toHaveKey('NCR')
        ->and($fields['religion']['type'])->toBe('select')
        ->and($fields['religion']['presentation']['control'])->toBe('select')
        ->and($fields['religion']['options'])->toHaveKey('roman_catholic')
        ->and($fields['pwd_type']['type'])->toBe('select')
        ->and($fields['pwd_type']['presentation']['control'])->toBe('select')
        ->and($fields['pwd_type']['options'])->toHaveKey('visual')
        ->and($fields['emergency_contact_relationship']['type'])->toBe('select')
        ->and($fields['emergency_contact_relationship']['presentation']['control'])->toBe('select')
        ->and($fields['emergency_contact_relationship']['options'])->toHaveKey('mother')
        ->and($fields['guardian_relationship']['type'])->toBe('select')
        ->and($fields['guardian_relationship']['presentation']['control'])->toBe('select')
        ->and($fields['guardian_relationship']['options'])->toHaveKey('legal_guardian');
});

it('upgrades saved student profile dropdown field definitions', function (): void {
    $form = Form::factory()->create(['settings' => ['template_key' => 'student_profile_completion']]);
    $otherForm = Form::factory()->create(['settings' => ['template_key' => 'custom']]);
    $form->fields()->createMany([
        ['field_key' => 'civil_status', 'label' => 'Civil status', 'type' => 'text', 'options' => [], 'presentation' => ['control' => 'input'], 'position' => 1],
        ['field_key' => 'nationality', 'label' => 'Nationality', 'type' => 'text', 'options' => [], 'presentation' => ['control' => 'input'], 'position' => 2],
        ['field_key' => 'region_of_origin', 'label' => 'Region of Origin', 'type' => 'text', 'options' => [], 'presentation' => ['control' => 'input'], 'position' => 3],
        ['field_key' => 'birthplace', 'label' => 'Birthplace', 'type' => 'text', 'options' => [], 'presentation' => ['control' => 'combobox'], 'position' => 4],
    ]);
    $otherForm->fields()->create([
        'field_key' => 'civil_status',
        'label' => 'Civil status',
        'type' => 'text',
        'options' => [],
        'presentation' => ['control' => 'input'],
        'position' => 1,
    ]);

    $migration = include dirname(__DIR__, 2).'/database/migrations/2026_09_08_000001_upgrade_student_profile_dropdown_fields.php';
    $migration->up();

    $upgradedFields = $form->fields()->whereIn('field_key', ['civil_status', 'nationality', 'region_of_origin'])->get()->keyBy('field_key');
    $unrelatedField = $form->fields()->where('field_key', 'birthplace')->firstOrFail();
    $otherField = $otherForm->fields()->where('field_key', 'civil_status')->firstOrFail();

    expect($upgradedFields['civil_status']->type)->toBe('select')
        ->and($upgradedFields['civil_status']->options)->toHaveKey('single')
        ->and($upgradedFields['civil_status']->presentation['control'])->toBe('select')
        ->and($upgradedFields['nationality']->type)->toBe('select')
        ->and($upgradedFields['nationality']->options)->toHaveKey('filipino')
        ->and($upgradedFields['region_of_origin']->type)->toBe('select')
        ->and($upgradedFields['region_of_origin']->options)->toHaveKey('NCR')
        ->and($unrelatedField->type)->toBe('text')
        ->and($otherField->type)->toBe('text');
});

it('preserves customized choices during the dropdown migration', function (): void {
    $form = Form::factory()->create(['settings' => ['template_key' => 'student_profile_completion']]);
    $form->fields()->create([
        'field_key' => 'nationality',
        'label' => 'Custom Nationality',
        'type' => 'text',
        'options' => ['custom_country' => 'Custom Country'],
        'presentation' => ['control' => 'input', 'placeholder' => 'Custom placeholder'],
        'position' => 1,
    ]);

    $migration = include dirname(__DIR__, 2).'/database/migrations/2026_09_08_000001_upgrade_student_profile_dropdown_fields.php';
    $migration->up();

    $field = $form->fields()->where('field_key', 'nationality')->firstOrFail();
    expect($field->type)->toBe('select')
        ->and($field->options)->toBe(['custom_country' => 'Custom Country'])
        ->and($field->presentation['control'])->toBe('select')
        ->and($field->presentation['placeholder'])->toBe('Custom placeholder');
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

it('updates existing profile forms and templates without changing responses', function (): void {
    $definition = reportingProfileDefinition([
        'gender', 'ethnicity', 'region_of_origin', 'province_of_origin', 'city_of_origin',
        'is_indigenous_person', 'indigenous_group', 'is_pwd', 'pwd_type', 'is_solo_parent',
        'is_senior_citizen', 'is_magna_carta', 'is_underprivileged', 'is_first_generation',
    ]);
    $form = Form::factory()->create([
        'title' => 'Student Profile Completion',
        'access_mode' => FormAccessMode::Invitation,
        'settings' => [
            'template_key' => 'student_profile_completion',
            'mapping_mode' => 'review',
        ],
    ]);
    $form->fields()->createMany([
        ['field_key' => 'gender', 'label' => 'Custom gender', 'type' => 'text', 'options' => ['male' => 'Male'], 'position' => 1, 'mapping' => ['model' => 'student', 'path' => 'student.gender'], 'presentation' => ['unit' => 'custom']],
        ['field_key' => 'family_income_bracket', 'label' => 'Custom income', 'type' => 'text', 'position' => 2, 'mapping' => ['model' => 'student', 'path' => 'student.family_income_bracket']],
        ['field_key' => 'father_income_bracket', 'label' => 'Father income', 'type' => 'text', 'position' => 3, 'mapping' => ['model' => 'student', 'path' => 'student.father_income_bracket']],
    ]);
    $template = FormTemplate::query()->create([
        'name' => 'Saved profile',
        'model_key' => 'student',
        'definition' => [
            'settings' => ['template_key' => 'student_profile_completion'],
            'fields' => [
                ['field_key' => 'gender', 'label' => 'Gender', 'type' => 'text', 'options' => ['male' => 'Male'], 'mapping' => ['model' => 'student', 'path' => 'student.gender']],
                ['field_key' => 'father_income_bracket', 'label' => 'Father income', 'type' => 'text'],
            ],
        ],
    ]);
    $response = app(FormResponseService::class)->submit($form, ['answers' => ['gender' => 'male']]);
    $payload = app(FormAnswerService::class)->latestAnswers($response);

    $migration = include dirname(__DIR__, 2).'/database/migrations/2026_09_12_000001_update_student_profile_form_demographics_and_income.php';
    $migration->up();
    $firstRun = $form->fresh('fields')->fields->keyBy('field_key');
    $migration->up();
    $secondRun = $form->fresh('fields')->fields->keyBy('field_key');
    expect($firstRun)->toHaveKeys(['gender', 'family_income_bracket', 'father_income_bracket'])
        ->and($firstRun)->toHaveKeys(['is_indigenous_person', 'is_pwd', 'is_solo_parent', 'is_solo_parent_dependent', 'is_senior_citizen', 'is_magna_carta', 'is_underprivileged', 'is_first_generation'])
        ->and($firstRun['gender']->options)->toBe(FormTemplateService::GENDER_OPTIONS)
        ->and($firstRun['gender']->presentation['unit'])->toBe('custom')
        ->and($firstRun['family_income_bracket']->type)->toBe('select')
        ->and($firstRun['family_income_bracket']->presentation['control'])->toBe('select')
        ->and($firstRun['father_income_bracket']->behavior['retired'])->toBeTrue()
        ->and($firstRun)->toHaveCount($secondRun->count())
        ->and($secondRun['gender']->options)->toBe($firstRun['gender']->options)
        ->and(collect($template->fresh()->definition['fields'])->pluck('field_key')->all())->not->toContain('father_income_bracket')
        ->and($payload)->toBe(['gender' => 'male']);
});

it('does not expose retired fields in new submissions while preserving their mappings', function (): void {
    $form = Form::factory()->create();
    $form->fields()->createMany([
        ['field_key' => 'family_income_bracket', 'label' => 'Family income', 'type' => 'select', 'options' => ['below_250k' => 'Below'], 'position' => 1],
        ['field_key' => 'father_income_bracket', 'label' => 'Father income', 'type' => 'select', 'options' => ['below_250k' => 'Below'], 'position' => 2, 'behavior' => ['retired' => true]],
    ]);
    $form->load('fields');
    $definitions = app(FormDefinitionService::class);

    expect(collect($definitions->publicPayload($form)['fields'])->pluck('key')->all())->toBe(['family_income_bracket'])
        ->and($definitions->validationRules($form))->not->toHaveKey('answers.father_income_bracket')
        ->and($definitions->normalizeAnswers($form, ['father_income_bracket' => 'below_250k']))->toBe([])
        ->and($definitions->snapshot($form))->toHaveCount(1);
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
