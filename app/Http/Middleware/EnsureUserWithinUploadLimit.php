<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Interfaces\R2UploadServiceInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserWithinUploadLimit
{
    public function __construct(
        private readonly R2UploadServiceInterface $uploads,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $this->uploads->ensureWithinUploadLimit($user);

        return $next($request);
    }
}
