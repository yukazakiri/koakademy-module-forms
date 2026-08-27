<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Forms\Contracts\FormsTenantResolver;
use Modules\Forms\Models\Form;
use Modules\Forms\Models\FormTemplate;
use Modules\Forms\Services\FormLifecycleService;
use Modules\Forms\Services\FormsAuditService;
use Modules\Forms\Services\FormsAuthorization;
use Modules\Forms\Services\FormTemplateService;

final class FormTemplateController
{
    public function __construct(
        private readonly FormsAuthorization $authorization,
        private readonly FormsTenantResolver $tenantResolver,
        private readonly FormTemplateService $templates,
        private readonly FormLifecycleService $lifecycle,
        private readonly FormsAuditService $audit,
    ) {}

    public function index(Request $request): InertiaResponse
    {
        $this->authorize($request, 'templates.view');

        return Inertia::render('Forms/Templates', [
            'user' => $this->userPayload($request->user()),
            'templates' => $this->templates->catalog(),
            'permissions' => $this->permissions($request->user()),
        ]);
    }

    public function use(Request $request, string $template): RedirectResponse
    {
        $this->authorize($request, 'create');
        $definition = $this->templates->definition($template);
        abort_if($definition === null, 404);

        $definition['template_key'] = $template;
        $definition['template_id'] = $this->templates->templateId($template);
        $definition['slug'] = Str::slug((string) ($definition['title'] ?? 'form')).'-'.Str::lower(Str::random(6));
        $form = $this->lifecycle->create($definition, $request->user());
        $this->audit->record($form, 'form_created_from_template', metadata: ['template' => $template]);

        return redirect()->route('administrators.forms.edit', $form)->with('success', 'Form created from template.');
    }

    public function saveFromForm(Request $request, Form $form): RedirectResponse
    {
        $this->authorize($request, 'templates.create');
        $this->ensureTenant($form);
        $name = $request->string('name')->trim()->toString();
        abort_if($name === '', 422, 'A template name is required.');

        $template = $this->templates->createFromForm($form->load('fields'), $name, $request->user());

        return redirect()->route('administrators.forms.templates.index')->with('success', 'Template saved as '.$template->name.'.');
    }

    public function duplicate(Request $request, FormTemplate $template): RedirectResponse
    {
        $this->authorize($request, 'templates.create');
        $this->ensureTenant($template);
        $name = $request->string('name')->trim()->toString();
        abort_if($name === '', 422, 'A template name is required.');

        $copy = $this->templates->duplicate($template, $name, $request->user());

        return back()->with('success', 'Template duplicated as '.$copy->name.'.');
    }

    public function update(Request $request, FormTemplate $template): RedirectResponse
    {
        $this->authorize($request, 'templates.update');
        $this->ensureTenant($template);
        $name = $request->string('name')->trim()->toString();
        abort_if($name === '', 422, 'A template name is required.');
        $definition = $request->input('definition', $template->definition);
        abort_unless(is_array($definition), 422, 'A valid template definition is required.');
        $template->update([
            'name' => $name,
            'description' => $request->string('description')->trim()->toString() ?: null,
            'model_key' => is_string($definition['model_key'] ?? null) ? $definition['model_key'] : null,
            'definition' => $definition,
        ]);

        return back()->with('success', 'Template updated successfully.');
    }

    public function destroy(Request $request, FormTemplate $template): RedirectResponse
    {
        $this->authorize($request, 'templates.delete');
        $this->ensureTenant($template);
        $template->delete();

        return back()->with('success', 'Template deleted successfully.');
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

    /** @return array<string, bool> */
    private function permissions(mixed $user): array
    {
        return collect(['templates.view', 'templates.create', 'templates.update', 'templates.delete'])
            ->mapWithKeys(fn (string $ability): array => [$ability => $this->authorization->allows($user, $ability)])
            ->all();
    }

    private function authorize(Request $request, string $ability): void
    {
        abort_unless($this->authorization->allows($request->user(), $ability), 403);
    }

    private function ensureTenant(Form|FormTemplate $record): void
    {
        $tenantKey = $this->tenantResolver->key();
        abort_if($tenantKey !== null && (string) $record->tenant_key !== (string) $tenantKey, 404);
    }
}
