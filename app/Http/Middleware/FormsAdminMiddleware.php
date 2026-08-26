<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Forms\Services\FormsAuthorization;
use Symfony\Component\HttpFoundation\Response;

final class FormsAdminMiddleware
{
    public function __construct(private readonly FormsAuthorization $authorization) {}

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->authorization->allows($request->user(), 'view'), 403);

        return $next($request);
    }
}
