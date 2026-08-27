<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Forms\Http\Controllers\FormAdminController;
use Modules\Forms\Http\Controllers\FormInvitationController;
use Modules\Forms\Http\Controllers\FormTemplateController;
use Modules\Forms\Http\Controllers\PublicFormController;
use Modules\Forms\Http\Middleware\FormsAdminMiddleware;

Route::prefix((string) config('forms.admin_prefix', 'administrators/forms'))->name('administrators.forms.')->middleware(FormsAdminMiddleware::class)->group(function (): void {
    Route::get('/', [FormAdminController::class, 'index'])->name('index');
    Route::get('/create', [FormAdminController::class, 'create'])->name('create');
    Route::get('/templates', [FormTemplateController::class, 'index'])->name('templates.index');
    Route::post('/templates/{template}/use', [FormTemplateController::class, 'use'])->name('templates.use');
    Route::post('/templates/{template}/duplicate', [FormTemplateController::class, 'duplicate'])->name('templates.duplicate');
    Route::post('/{form}/save-template', [FormTemplateController::class, 'saveFromForm'])->name('templates.save');
    Route::put('/templates/{template}', [FormTemplateController::class, 'update'])->name('templates.update');
    Route::delete('/templates/{template}', [FormTemplateController::class, 'destroy'])->name('templates.delete');
    Route::get('/{form}/invitations', [FormInvitationController::class, 'index'])->name('invitations.index');
    Route::post('/{form}/invitations/send', [FormInvitationController::class, 'send'])->name('invitations.send');
    Route::post('/', [FormAdminController::class, 'store'])->name('store');
    Route::get('/{form}/edit', [FormAdminController::class, 'edit'])->name('edit');
    Route::get('/{form}/preview', [FormAdminController::class, 'preview'])->name('preview');
    Route::put('/{form}', [FormAdminController::class, 'update'])->name('update');
    Route::post('/{form}/publish', [FormAdminController::class, 'publish'])->name('publish');
    Route::post('/{form}/close', [FormAdminController::class, 'close'])->name('close');
    Route::get('/{form}/responses', [FormAdminController::class, 'responses'])->name('responses.index');
    Route::get('/{form}/responses/export', [FormAdminController::class, 'export'])->name('responses.export');
    Route::post('/{form}/responses/{response}/apply', [FormAdminController::class, 'apply'])->name('responses.apply');
});

Route::prefix((string) config('forms.public_prefix', 'forms'))->name('forms.')->middleware('throttle:'.config('forms.submission_throttle', 'forms'))->group(function (): void {
    Route::get('/{form:slug}/invite/{token}', [FormInvitationController::class, 'show'])->name('invitation.show');
    Route::post('/{form:slug}/invite/{token}/responses', [FormInvitationController::class, 'submit'])->name('invitation.submit');
    Route::post('/{form:slug}/identify', [PublicFormController::class, 'identify'])->name('identify');
    Route::get('/{form:slug}', [PublicFormController::class, 'show'])->name('show');
    Route::post('/{form:slug}/responses', [PublicFormController::class, 'submit'])->name('submit');
    Route::get('/{form:slug}/thanks', [PublicFormController::class, 'thanks'])->name('thanks');
});
