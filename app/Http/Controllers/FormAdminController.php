<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Http\Requests\StoreFormRequest;
use Modules\Forms\Http\Requests\UpdateFormRequest;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormResponse;
use Modules\Forms\Services\FormDefinitionService;
use Modules\Forms\Services\FormLifecycleService;
use Modules\Forms\Services\FormMappingService;
use Modules\Forms\Services\FormResponseService;
use Modules\Forms\Services\FormsAuditService;
use Modules\Forms\Services\FormsAuthorization;
use Modules\Forms\Services\FormTemplateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class FormAdminController
{
    public function __construct(
        private readonly FormsAuthorization $authorization,
        private readonly FormsTenantResolver $tenantResolver,
        private readonly FormsModelRegistry $models,
        private readonly FormDefinitionService $definitions,
        private readonly FormLifecycleService $lifecycle,
        private readonly FormsAuditService $audit,
        private readonly FormMappingService $mapping,
        private readonly FormResponseService $responses,
        private readonly FormTemplateService $templates,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request, 'view');
        $tenantKey = $this->tenantResolver->key();
        $query = Form::query()->withCount('responses')->latest();

        if ($tenantKey !== null) {
            $query->forTenant($tenantKey);
        }

        return Inertia::render('Forms/Index', [
            'user' => $this->userPayload($request->user()),
            'forms' => $query->get()->map(fn (Form $form): array => $this->formPayload($form))->values()->all(),
            'permissions' => $this->permissions($request->user()),
        ]);
    }

    public function create(Request $request): InertiaResponse
    {
        $this->authorize($request, 'create');

        return Inertia::render('Forms/Builder', [
            'user' => $this->userPayload($request->user()),
            'form' => null,
            'supported_types' => $this->definitions->supportedTypes(),
            'models' => $this->models->models(),
            'model_fields' => $this->modelFields(),
            'templates' => $this->templates->catalog(),
        ]);
    }

    public function store(StoreFormRequest $request): RedirectResponse
    {
        $form = $this->lifecycle->create($request->validated(), $request->user());
        $this->audit->record($form, 'form_created');

        return redirect()->route('administrators.forms.edit', $form)->with('success', 'Form created successfully.');
    }

    public function edit(Request $request, Form $form): InertiaResponse
    {
        $this->authorize($request, 'update');
        $this->ensureTenant($form);

        return Inertia::render('Forms/Builder', [
            'user' => $this->userPayload($request->user()),
            'form' => $this->formPayload($form->load('fields')),
            'supported_types' => $this->definitions->supportedTypes(),
            'models' => $this->models->models(),
            'model_fields' => $this->modelFields(),
            'templates' => $this->templates->catalog(),
        ]);
    }

    public function preview(Request $request, Form $form): InertiaResponse
    {
        $this->authorize($request, 'view');
        $this->ensureTenant($form);
        abort_unless($form->isOpen(), 404);

        return Inertia::render('Forms/PublicShow', [
            'form' => $this->definitions->publicPayload($form->load('fields')),
            'authenticated' => true,
            'user' => [
                'name' => data_get($request->user(), 'name'),
                'email' => data_get($request->user(), 'email'),
            ],
            'preview' => true,
        ]);
    }

    public function update(UpdateFormRequest $request, Form $form): RedirectResponse
    {
        $this->ensureTenant($form);
        $form = $this->lifecycle->update($form, $request->validated());
        $this->audit->record($form, 'form_updated');

        return back()->with('success', 'Form saved successfully.');
    }

    public function publish(Request $request, Form $form): RedirectResponse
    {
        $this->authorize($request, 'publish');
        $this->ensureTenant($form);
        $form = $this->lifecycle->publish($form);
        $this->audit->record($form, 'form_published');

        return back()->with('success', 'Form published successfully.');
    }

    public function close(Request $request, Form $form): RedirectResponse
    {
        $this->authorize($request, 'publish');
        $this->ensureTenant($form);
        $form = $this->lifecycle->close($form);
        $this->audit->record($form, 'form_closed');

        return back()->with('success', 'Form closed successfully.');
    }

    public function responses(Request $request, Form $form): InertiaResponse
    {
        $this->authorize($request, 'responses');
        $this->ensureTenant($form);
        $form->load('fields');

        return Inertia::render('Forms/Responses', [
            'user' => $this->userPayload($request->user()),
            'form' => $this->formPayload($form),
            'responses' => $form->responses()->with(['links', 'revisions'])->latest()->get()
                ->map(fn (FormResponse $response): array => $this->responsePayload($response))->values()->all(),
            'permissions' => $this->permissions($request->user()),
        ]);
    }

    public function apply(Request $request, Form $form, FormResponse $response): RedirectResponse
    {
        $this->authorize($request, 'apply');
        $this->ensureTenant($form);
        abort_unless($response->form_id === $form->getKey(), 404);

        $overwrite = data_get($form->settings, 'mapping_mode', 'review') === 'review'
            && $request->boolean('overwrite');
        $this->mapping->apply($response->load('form.fields', 'links'), $overwrite, $request->user());

        return back()->with('success', 'Approved answers were applied to the linked record.');
    }

    public function export(Request $request, Form $form): StreamedResponse
    {
        $this->authorize($request, 'export');
        $this->ensureTenant($form);
        $form->load('fields');
        $responses = $form->responses()->with('revisions')->latest()->get();
        $this->audit->record($form, 'responses_exported', metadata: ['count' => $responses->count()]);

        return Response::streamDownload(function () use ($form, $responses): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'Response ID',
                'Submitted At',
                'Status',
                'Respondent Email',
                'Respondent Identifier',
                ...$form->fields->pluck('label')->all(),
            ]);

            foreach ($responses as $response) {
                $answers = $this->responses->latestAnswers($response);
                fputcsv($handle, [
                    $response->getKey(),
                    $response->submitted_at?->toIso8601String(),
                    $response->status->value,
                    $response->respondent_email,
                    $response->respondent_identifier,
                    ...$form->fields->map(fn ($field): string => $this->csvValue($answers[$field->field_key] ?? null))->all(),
                ]);
            }

            fclose($handle);
        }, 'form-'.$form->slug.'-responses.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array<string, mixed> */
    private function formPayload(Form $form): array
    {
        return [
            'id' => $form->getKey(),
            'template_id' => $form->template_id,
            'title' => $form->title,
            'slug' => $form->slug,
            'description' => $form->description,
            'status' => $form->status->value,
            'access_mode' => $form->access_mode->value,
            'identity_type' => $form->identity_type,
            'settings' => $form->settings ?? [],
            'closes_at' => $form->closes_at?->toIso8601String(),
            'responses_count' => $form->responses_count ?? $form->responses()->count(),
            'fields' => $form->relationLoaded('fields')
                ? $form->fields->map(fn ($field): array => $this->formFieldPayload($form, $field))->values()->all()
                : [],
        ];
    }

    /** @return array<string, mixed> */
    private function formFieldPayload(Form $form, mixed $field): array
    {
        $presentation = is_array($field->presentation) ? $field->presentation : [];
        $payload = [
            'field_key' => $field->field_key,
            'label' => $field->label,
            'type' => $field->type,
            'description' => $field->description,
            'section' => $field->section,
            'required' => $field->required,
            'options' => $field->options ?? [],
            'validation' => $field->validation ?? [],
            'visibility' => $field->visibility,
            'presentation' => $presentation,
            'behavior' => $field->behavior ?? [],
            'mapping' => $field->mapping,
            'is_sensitive' => $field->is_sensitive,
        ];

        if (data_get($form->settings, 'template_key') === 'student_profile_completion') {
            $defaults = $this->templates->studentProfileFieldDefaults($field->field_key, $field->type);
            $payload['description'] ??= $defaults['description'];
            $presentation['placeholder'] ??= $defaults['placeholder'];
            $payload['presentation'] = $presentation;
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function responsePayload(FormResponse $response): array
    {
        return [
            'id' => $response->getKey(),
            'status' => $response->status->value,
            'respondent_user_id' => $response->respondent_user_id,
            'respondent_email' => $response->respondent_email,
            'respondent_identifier' => $response->respondent_identifier,
            'submitted_at' => $response->submitted_at?->toIso8601String(),
            'latest_revision' => $response->latest_revision,
            'answers' => $this->responses->latestAnswers($response),
            'links' => $response->links->map(fn ($link): array => [
                'model_key' => $link->model_key,
                'model_id' => $link->model_id,
                'status' => $link->status,
                'error_message' => $link->error_message,
            ])->values()->all(),
        ];
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function modelFields(): array
    {
        $fields = [];
        foreach ($this->models->models() as $model) {
            $fields[$model['key']] = $this->models->fields($model['key']);
        }

        return $fields;
    }

    /** @return array<string, bool> */
    private function permissions(mixed $user): array
    {
        return collect(['create', 'update', 'publish', 'responses', 'export', 'apply', 'invitations.view', 'invitations.create'])
            ->mapWithKeys(fn (string $ability): array => [$ability => $this->authorization->allows($user, $ability)])
            ->all();
    }

    private function authorize(Request $request, string $ability): void
    {
        abort_unless($this->authorization->allows($request->user(), $ability), 403);
    }

    private function ensureTenant(Form $form): void
    {
        $tenantKey = $this->tenantResolver->key();
        abort_if($tenantKey !== null && (string) $form->tenant_key !== (string) $tenantKey, 404);
    }

    /** @return array<string, mixed> */
    private function userPayload(mixed $user): array
    {
        return [
            'id' => data_get($user, 'id'),
            'name' => data_get($user, 'name', 'Administrator'),
            'email' => data_get($user, 'email', ''),
            'avatar' => data_get($user, 'avatar_url'),
            'role' => data_get($user, 'role.value', data_get($user, 'role', 'Administrator')),
            'permissions' => method_exists($user, 'getAllPermissions') ? $user->getAllPermissions()->pluck('name')->values()->all() : [],
        ];
    }

    private function csvValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn (mixed $item): string => $this->csvValue($item), $value));
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            return 'Uploaded file';
        }

        return '';
    }
}
