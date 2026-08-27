<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Forms\Enums\FormAccessMode;
use Modules\Forms\Http\Requests\SubmitFormRequest;
use Modules\Forms\Models\Form;
use Modules\Forms\Services\FormDefinitionService;
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
            'user' => Auth::user() ? [
                'name' => data_get(Auth::user(), 'name'),
                'email' => data_get(Auth::user(), 'email'),
            ] : null,
        ]);
    }

    public function submit(SubmitFormRequest $request, Form $form): RedirectResponse
    {
        $this->responses->submit($form->load('fields'), $request->validated(), $request->user());

        return redirect()->route('forms.thanks', ['form' => $form->slug]);
    }

    public function thanks(Form $form): Response
    {
        return Inertia::render('Forms/Thanks', [
            'title' => $form->title,
            'message' => data_get($form->settings, 'confirmation_message', 'Your response has been recorded.'),
            'form_url' => route('forms.show', ['form' => $form->slug]),
        ]);
    }
}
