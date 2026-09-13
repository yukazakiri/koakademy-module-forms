<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Forms\Contracts\FormsModelRegistry;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Http\Requests\ResolveGuestIdentityRequest;
use Modules\Forms\Http\Requests\SubmitFormRequest;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormDefinitionService;
use Modules\Forms\Services\FormGuestIdentityService;
use Modules\Forms\Services\FormResponseService;

final class PublicFormController
{
    public function __construct(
        private readonly FormDefinitionService $definitions,
        private readonly FormResponseService $responses,
    ) {}

    public function show(Request $request, Form $form): Response
    {
        abort_unless($form->isOpen() && $form->access_mode !== FormAccessMode::Invitation, 404);

        return Inertia::render('Forms/PublicShow', [
            'form' => $this->definitions->publicPayload($form->load('fields')),
            'authenticated' => Auth::check(),
            'authenticated_guest_profile' => $this->authenticatedGuestProfile($form, $request),
            'hideMobileNavigation' => true,
            'user' => Auth::user() ? [
                'name' => data_get(Auth::user(), 'name'),
                'email' => data_get(Auth::user(), 'email'),
            ] : null,
        ]);
    }

    /** @return array<string, mixed>|null */
    private function authenticatedGuestProfile(Form $form, Request $request): ?array
    {
        if (! $request->user()
            || $form->access_mode !== FormAccessMode::GuestIdentifier
            || $form->identity_type !== 'student_id'
            || data_get($form->settings, 'allow_authenticated_guest_prefill') !== true) {
            return null;
        }

        $record = app(FormsModelRegistry::class)->resolveForUser('student', $request->user());
        if ($record === null) {
            return null;
        }

        return [
            'name' => data_get($record, 'full_name')
                ?? trim(implode(' ', array_filter([
                    data_get($record, 'first_name'),
                    data_get($record, 'middle_name'),
                    data_get($record, 'last_name'),
                ]))),
            'student_id' => (string) (data_get($record, 'student_id') ?? ''),
            'email' => (string) (data_get($record, 'email') ?? data_get($request->user(), 'email') ?? ''),
            'answers' => $this->definitions->prefillAnswers($form->load('fields'), $record),
        ];
    }

    public function submit(SubmitFormRequest $request, Form $form): RedirectResponse
    {
        $this->responses->submit($form->load('fields'), $request->validated(), $request->user());

        return redirect()->route('forms.thanks', ['form' => $form->slug]);
    }

    public function identify(
        ResolveGuestIdentityRequest $request,
        Form $form,
        FormGuestIdentityService $identities,
    ): JsonResponse {
        $record = $identities->resolve(
            $form,
            $request->validated('respondent_identifier'),
            $request->validated('respondent_email'),
        );

        return response()->json([
            'matched' => true,
            'answers' => $this->definitions->prefillAnswers($form->load('fields'), $record),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function thanks(Form $form): Response
    {
        return Inertia::render('Forms/Thanks', [
            'title' => $form->title,
            'message' => data_get($form->settings, 'confirmation_message', 'Your response has been recorded.'),
            'form_url' => route('forms.show', ['form' => $form->slug]),
            'hideMobileNavigation' => true,
        ]);
    }
}
