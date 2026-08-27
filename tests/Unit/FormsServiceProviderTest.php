<?php

declare(strict_types=1);

use Modules\Forms\Contracts\FormsFieldSuggestionProvider;
use Modules\Forms\Contracts\FormsInvitationTargetProvider;
use Modules\Forms\Providers\FormsServiceProvider;
use Modules\Forms\Services\KoAkademyFormsFieldSuggestionProvider;
use Modules\Forms\Services\KoAkademyFormsInvitationTargetProvider;

beforeEach(function (): void {
    if (! class_exists('App\Models\Student')) {
        eval('namespace App\Models; class Student {}');
    }

    if (! class_exists('App\Support\RegistrarStudentProfileWorkbook')) {
        eval('namespace App\Support; class RegistrarStudentProfileWorkbook {}');
    }

    if (! class_exists('App\Services\TenantContext')) {
        eval('namespace App\Services; class TenantContext {}');
    }
});

it('injects dependencies when constructing the KoAkademy providers', function (): void {
    (new FormsServiceProvider(app()))->register();

    expect(app(FormsFieldSuggestionProvider::class))
        ->toBeInstanceOf(KoAkademyFormsFieldSuggestionProvider::class)
        ->and(app(FormsInvitationTargetProvider::class))
        ->toBeInstanceOf(KoAkademyFormsInvitationTargetProvider::class);
});
