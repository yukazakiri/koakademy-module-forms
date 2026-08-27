<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Http\Requests\SendFormInvitationRequest;
use Modules\Forms\Http\Requests\SubmitInvitationFormRequest;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormDefinitionService;
use Modules\Forms\Services\FormInvitationService;
use Modules\Forms\Services\FormResponseService;
use Modules\Forms\Services\FormsAuthorization;

final class FormInvitationController
{
    public function __construct(
        private readonly FormsAuthorization $authorization,
        private readonly FormInvitationService $invitations,
        private readonly FormsInvitationTargetProvider $targets,
        private readonly FormDefinitionService $definitions,
        private readonly FormResponseService $responses,
    ) {}

    public function index(Request $request, Form $form): Response
    {
        abort_unless($this->authorization->allows($request->user(), 'invitations.view'), 403);
        $this->invitations->ensureTenant($form);

        return Inertia::render('Forms/Invitations', [
            'form' => [
                'id' => $form->getKey(),
                'title' => $form->title,
                'status' => $form->status->value,
                'access_mode' => $form->access_mode->value,
            ],
            'candidates' => $this->invitations->preview($form->load('fields')),
            'permissions' => [
                'send' => $this->authorization->allows($request->user(), 'invitations.create'),
            ],
        ]);
    }

    public function send(SendFormInvitationRequest $request, Form $form): RedirectResponse
    {
        $this->invitations->ensureTenant($form);
        $result = $this->invitations->send($form->load('fields'), $request->validated('model_ids'), $request->user());

        return back()->with('success', $result['created'].' invitation(s) queued for delivery.');
    }

    public function show(Form $form, string $token): Response
    {
        $invitation = $this->invitations->resolve($form, $token);
        $record = $this->targets->resolve($invitation);
        abort_unless($record !== null, 404);

        return Inertia::render('Forms/PublicShow', [
            'form' => $this->definitions->publicPayload($form->load('fields'), $record),
            'authenticated' => false,
            'user' => null,
            'invitation_token' => $token,
            'invitation' => [
                'expires_at' => $invitation->expires_at?->toIso8601String(),
                'student_name' => data_get($record, 'full_name', data_get($record, 'name')),
            ],
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function submit(SubmitInvitationFormRequest $request, Form $form, string $token): RedirectResponse
    {
        $invitation = $this->invitations->resolve($form, $token);
        $this->responses->submit($form->load('fields'), $request->validated(), null, $invitation);

        return redirect()->route('forms.thanks', ['form' => $form->slug]);
    }
}
