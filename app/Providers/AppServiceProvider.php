<?php

namespace App\Providers;

use App\Actions\DeleteExpiredChatMessagesAction;
use App\Actions\DeleteExpiredSendsAction;
use App\Actions\DeleteExpiredUploadedFilesAction;
use App\Actions\Interfaces\PreparesSendPivotData;
use App\Actions\PrepareSendPivotDataAction;
use App\Gates\MarkChatMessageAsViewed;
use App\Models\User;
use App\Repositories\Eloquent\CachedChatMessageRepository;
use App\Repositories\Eloquent\CachedSendsRepository;
use App\Repositories\Eloquent\ChatMessageRepository;
use App\Repositories\Eloquent\ConversationRepository;
use App\Repositories\Eloquent\SendRepository;
use App\Repositories\Eloquent\UserIdentityKeyRepository;
use App\Repositories\Interfaces\ChatMessageRepositoryInterface;
use App\Repositories\Interfaces\ConversationRepositoryInterface;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Repositories\Interfaces\UserIdentityKeyRepositoryInterface;
use App\Services\ChatMessageService;
use App\Services\Interfaces\ChatMessageServiceInterface;
use App\Services\Interfaces\R2UploadServiceInterface;
use App\Services\Interfaces\SendReadServiceInterface;
use App\Services\Interfaces\SendWriteServiceInterface;
use App\Services\Interfaces\UserUploadStorageTrackerInterface;
use App\Services\R2UploadService;
use App\Services\SendReadService;
use App\Services\SendWriteService;
use App\Services\UserUploadStorageTracker;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChatMessageRepositoryInterface::class, ChatMessageRepository::class);
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(UserIdentityKeyRepositoryInterface::class, UserIdentityKeyRepository::class);
        $this->app->bind(ChatMessageServiceInterface::class, ChatMessageService::class);
        $this->app->bind(UserUploadStorageTrackerInterface::class, UserUploadStorageTracker::class);
        $this->app->bind(R2UploadServiceInterface::class, R2UploadService::class);
        $this->app->bind(SendRepositoryInterface::class, SendRepository::class);
        $this->app->bind(SendWriteServiceInterface::class, SendWriteService::class);
        $this->app->bind(SendReadServiceInterface::class, SendReadService::class);

        $this->app->extend(ChatMessageRepositoryInterface::class, function (ChatMessageRepositoryInterface $repository, Application $app) {
            return new CachedChatMessageRepository(
                $repository,
                $app->make('cache.store')
            );
        });

        $this->app->extend(SendRepositoryInterface::class, function (SendRepositoryInterface $repository, Application $app) {
            return new CachedSendsRepository(
                $repository,
                $app->make('cache.store')
            );
        });

        $this->app->bind(PreparesSendPivotData::class, PrepareSendPivotDataAction::class);

        $this->app->tag([
            DeleteExpiredSendsAction::class,
            DeleteExpiredChatMessagesAction::class,
            DeleteExpiredUploadedFilesAction::class,
        ], 'expirable-cleanup');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureAuthenticatedWebRoutes();

        RateLimiter::for('sends-write', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()->id);
        });

        RateLimiter::for('sends-index', function (Request $request) {
            return Limit::perMinute(15)->by($request->user()->id);
        });

        RateLimiter::for('chat-write', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()->id);
        });

        RateLimiter::for('chat-poll', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()->id);
        });

        RateLimiter::for('chat-identity', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()->id);
        });

        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()->id);
        });

        Gate::define('viewPulse', function (User $user) {
            return ! is_null($user->email_verified_at)
                && $user->email === config('pulse.admin_email');
        });

        Gate::define('markChatMessageAsViewed', MarkChatMessageAsViewed::class);
    }

    /**
     * Require authentication for all web routes.
     *
     * Opt out in route files with withoutMiddleware(['auth']), or via Fortify's guest middleware.
     */
    protected function configureAuthenticatedWebRoutes(): void
    {
        Route::pushMiddlewareToGroup('web', 'auth');

        $this->app->booted(function (): void {
            foreach (Route::getRoutes()->getRoutes() as $route) {
                $usesGuestMiddleware = collect($route->middleware())
                    ->contains(fn (string $middleware): bool => str_starts_with($middleware, 'guest'));

                if ($usesGuestMiddleware) {
                    $route->withoutMiddleware(['auth', Authenticate::class]);
                }
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        TrustProxies::at(config('app.trusted_proxies', ''));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(function (): Password {
            $user = auth()->user();

            if (! $user && request()->filled('email')) {
                $user = User::query()
                    ->where('email', request()->input('email'))
                    ->first();
            }

            $rule = $user?->hasEnabledTwoFactorAuthentication()
                ? Password::min(8)->uncompromised()
                : Password::min(15)->uncompromised();

            return $rule->rules([
                function (string $attribute, mixed $value, Closure $fail): void {
                    $appName = (string) config('app.name');

                    if ($appName !== '' && mb_stripos((string) $value, $appName) !== false) {
                        $fail('The :attribute must not contain the application name.');
                    }
                },
            ]);
        });
    }
}
