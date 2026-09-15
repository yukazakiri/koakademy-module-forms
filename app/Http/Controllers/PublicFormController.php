<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
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
            'hideMobileNavigation' => true,
        ]);
    }

    public function submit(SubmitFormRequest $request, Form $form): RedirectResponse
    {
        $user = $form->access_mode === FormAccessMode::Authenticated ? $request->user() : null;
        $response = $this->responses->submit($form->load('fields'), $request->validated(), $user);
        Log::info('Public form response submitted', [
            'form_id' => $form->getKey(),
            'form_slug' => $form->slug,
            'response_id' => $response->getKey(),
            'status' => $response->status->value,
        ]);

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
