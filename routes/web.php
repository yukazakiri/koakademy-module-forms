<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Forms\Http\Controllers\FormAdminController;
use Modules\Forms\Http\Controllers\PublicFormController;
use Modules\Forms\Http\Middleware\FormsAdminMiddleware;

Route::prefix((string) config('forms.admin_prefix', 'administrators/forms'))->name('administrators.forms.')->middleware(FormsAdminMiddleware::class)->group(function (): void {
    Route::get('/', [FormAdminController::class, 'index'])->name('index');
    Route::get('/create', [FormAdminController::class, 'create'])->name('create');
    Route::post('/', [FormAdminController::class, 'store'])->name('store');
    Route::get('/{form}/edit', [FormAdminController::class, 'edit'])->name('edit');
    Route::put('/{form}', [FormAdminController::class, 'update'])->name('update');
    Route::post('/{form}/publish', [FormAdminController::class, 'publish'])->name('publish');
    Route::post('/{form}/close', [FormAdminController::class, 'close'])->name('close');
    Route::get('/{form}/responses', [FormAdminController::class, 'responses'])->name('responses.index');
    Route::get('/{form}/responses/export', [FormAdminController::class, 'export'])->name('responses.export');
    Route::post('/{form}/responses/{response}/apply', [FormAdminController::class, 'apply'])->name('responses.apply');
});

Route::prefix((string) config('forms.public_prefix', 'forms'))->name('forms.')->middleware('throttle:'.config('forms.submission_throttle', 'forms'))->group(function (): void {
    Route::get('/{form:slug}', [PublicFormController::class, 'show'])->name('show');
    Route::post('/{form:slug}/responses', [PublicFormController::class, 'submit'])->name('submit');
    Route::get('/{form:slug}/thanks', [PublicFormController::class, 'thanks'])->name('thanks');
});
