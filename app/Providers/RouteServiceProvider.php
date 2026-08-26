<?php

declare(strict_types=1);

namespace Modules\Forms\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

final class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        Route::middleware('web')->group(dirname(__DIR__, 2).'/routes/web.php');
    }
}
