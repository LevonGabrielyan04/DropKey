<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\Interfaces\UserIdentityKeyRepositoryInterface;
use Closure;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordForIdentityKeyOverwrite
{
    public function __construct(
        protected UserIdentityKeyRepositoryInterface $identityKeys,
        protected RequirePassword $requirePassword,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->id;

        if ($userId === null || $this->identityKeys->findForUser($userId) === null) {
            return $next($request);
        }

        return $this->requirePassword->handle($request, $next);
    }
}
