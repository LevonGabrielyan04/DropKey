<?php

use App\Models\ChatMessage;
use App\Models\User;
use App\Support\Csp\StrictPolicyPreset;
use Spatie\Csp\Policy;

it('adds a strict content security policy header to web responses', function () {
    config(['app.url' => 'https://example.test']);

    $response = $this->get('/login');

    $response->assertSuccessful();

    $policy = $response->headers->get('Content-Security-Policy');

    expect($policy)->toBeString()
        ->toContain('default-src https://example.test')
        ->toContain('script-src https://example.test')
        ->toContain("'wasm-unsafe-eval'")
        ->toContain('style-src https://example.test')
        ->toContain("frame-ancestors 'none'")
        ->toContain("object-src 'none'")
        ->not->toContain('127.0.0.1:5173');

    expect($policy)->not->toContain('unsafe-inline');
    expect($policy)->not->toContain("'unsafe-eval'");
    expect($policy)->toMatch('/script-src [^;]*nonce-[A-Za-z0-9+\/=]+/');
    expect($policy)->toMatch('/style-src [^;]*nonce-[A-Za-z0-9+\/=]+/');
});

it('allows the vite dev server origin when running locally', function () {
    config([
        'app.url' => 'http://localhost',
        'app.env' => 'local',
    ]);

    $herdHost = basename(base_path()).'.test';

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)
        ->toContain('http://localhost')
        ->toContain('http://127.0.0.1')
        ->toContain('http://127.0.0.1:5173')
        ->toContain('http://localhost:5173')
        ->toContain("https://{$herdHost}")
        ->toContain("https://vite.{$herdHost}:5173")
        ->toContain("https://{$herdHost}:5173")
        ->toContain("wss://vite.{$herdHost}:5173")
        ->toContain("wss://{$herdHost}:5173")
        ->toContain('ws://127.0.0.1:5173')
        ->toContain('ws://localhost:5173')
        ->toContain('ws://localhost:8080')
        ->toContain('ws://127.0.0.1:8080')
        ->toContain('worker-src http://localhost http://127.0.0.1')
        ->toContain('blob:');
});

it('uses the app url host for vite dev origins when it is not loopback', function () {
    config([
        'app.url' => 'https://passshare.test',
        'app.env' => 'local',
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)
        ->toContain('https://passshare.test')
        ->toContain('https://vite.passshare.test:5173')
        ->toContain('https://passshare.test:5173')
        ->toContain('wss://vite.passshare.test:5173');
});

it('allows both localhost and 127.0.0.1 app origins when running locally', function (string $appUrl, string $alternateOrigin) {
    config([
        'app.url' => $appUrl,
        'app.env' => 'local',
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)
        ->toContain($appUrl)
        ->toContain($alternateOrigin);
})->with([
    'localhost app url' => ['http://localhost:8000', 'http://127.0.0.1:8000'],
    '127.0.0.1 app url' => ['http://127.0.0.1:8000', 'http://localhost:8000'],
]);

it('allows the have i been pwned passwords api for connect sources', function () {
    config(['app.url' => 'https://example.test']);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)
        ->toContain('connect-src')
        ->toContain('https://api.pwnedpasswords.com');
});

it('allows configured cloud upload origins for connect sources', function () {
    config([
        'app.url' => 'https://example.test',
        'filesystems.disks.r2.url' => 'https://files.passshare.test/bucket',
        'filesystems.disks.r2.endpoint' => 'https://accountid.r2.cloudflarestorage.com',
        'filesystems.disks.r2.bucket' => 'dropkey',
        'filesystems.disks.r2.use_path_style_endpoint' => false,
        'filesystems.disks.s3.url' => null,
        'filesystems.disks.s3.endpoint' => null,
        'filesystems.disks.s3.bucket' => null,
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)
        ->toContain('connect-src')
        ->toContain('https://files.passshare.test')
        ->toContain('https://accountid.r2.cloudflarestorage.com')
        ->toContain('https://dropkey.accountid.r2.cloudflarestorage.com');
});

it('does not add virtual-hosted cloud upload origins when path style is enabled', function () {
    config([
        'app.url' => 'https://example.test',
        'filesystems.disks.r2.url' => null,
        'filesystems.disks.r2.endpoint' => 'https://accountid.r2.cloudflarestorage.com',
        'filesystems.disks.r2.bucket' => 'dropkey',
        'filesystems.disks.r2.use_path_style_endpoint' => true,
        'filesystems.disks.s3.url' => null,
        'filesystems.disks.s3.endpoint' => null,
        'filesystems.disks.s3.bucket' => null,
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)
        ->toContain('https://accountid.r2.cloudflarestorage.com')
        ->not->toContain('https://dropkey.accountid.r2.cloudflarestorage.com');
});

it('allows cloudflare turnstile sources when turnstile is enabled', function () {
    config([
        'app.url' => 'https://example.test',
        'turnstile.enabled' => true,
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)
        ->toContain('script-src')
        ->toContain('frame-src https://challenges.cloudflare.com')
        ->toContain('connect-src')
        ->toContain('https://challenges.cloudflare.com');

    expect(substr_count($policy, 'https://challenges.cloudflare.com'))->toBeGreaterThanOrEqual(3);
});

it('allows lan dev hosts when running locally', function () {
    config([
        'app.url' => 'https://example.test',
        'app.env' => 'local',
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)
        ->toContain('http://localhost')
        ->toContain('https://localhost')
        ->toContain('http://localhost:8000')
        ->toContain('https://localhost:8000')
        ->toContain('http://localhost:5173')
        ->toContain('https://localhost:5173')
        ->toContain('https://vite.localhost:5173')
        ->toContain('ws://localhost:5173')
        ->toContain('wss://localhost:5173')
        ->toContain('wss://vite.localhost:5173');
});

it('allows lan dev hosts when running in testing', function () {
    config([
        'app.url' => 'https://example.test',
        'app.env' => 'testing',
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)->toContain('http://localhost:8000');
});

it('does not allow lan dev hosts outside local and testing environments', function () {
    config([
        'app.url' => 'https://example.test',
        'app.env' => 'production',
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)->not->toContain('http://localhost:8000');
});

it('does not allow dedicated loopback vite origins when the app url is not loopback', function () {
    config([
        'app.url' => 'https://example.test',
        'app.env' => 'local',
    ]);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)->not->toContain('127.0.0.1:5173');
});

it('adds upgrade-insecure-requests when the app url uses https', function () {
    config(['app.url' => 'https://example.test']);

    $policy = Policy::create([StrictPolicyPreset::class])->getContents();

    expect($policy)->toContain('upgrade-insecure-requests');
});

it('includes a nonce in the content security policy header', function () {
    config(['app.url' => 'https://example.test']);

    $response = $this->get('/login');

    $policy = $response->headers->get('Content-Security-Policy');

    expect($policy)->toMatch('/nonce-[A-Za-z0-9+\/=]+/');
});

it('adds the csp nonce to livewire inline scripts', function () {
    config(['app.url' => 'https://example.test']);

    $response = $this->get('/login');

    preg_match('/nonce-([A-Za-z0-9+\/=]+)/', $response->headers->get('Content-Security-Policy'), $matches);

    expect($matches)->not->toBeEmpty()
        ->and($response->getContent())->toContain('nonce="'.$matches[1].'"');
});

it('adds the csp nonce to flux appearance inline assets', function () {
    config(['app.url' => 'https://example.test']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('appearance.edit'));

    $response->assertSuccessful();

    preg_match('/nonce-([A-Za-z0-9+\/=]+)/', $response->headers->get('Content-Security-Policy'), $matches);

    expect($matches)->not->toBeEmpty();

    $nonce = $matches[1];

    expect($response->getContent())
        ->toContain('<style nonce="'.$nonce.'">')
        ->toContain('<script nonce="'.$nonce.'">')
        ->toContain('window.Flux.applyAppearance');
});

it('does not add content security policy headers to pulse pages', function () {
    config(['app.url' => 'https://example.test']);

    $user = User::factory()->create([
        'email' => config('pulse.admin_email'),
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/'.trim((string) config('pulse.path', 'pulse'), '/'));

    $response->assertSuccessful();

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

it('does not render inline display styles that violate the content security policy', function () {
    config(['app.url' => 'https://example.test']);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('sends.create'));

    $response->assertSuccessful();

    expect($response->getContent())
        ->not->toContain('style="display: none;"')
        ->not->toMatch('/x-bind:style/');
});

it('does not render csp-unsafe alpine template literals on chat pages', function () {
    config(['app.url' => 'https://example.test']);

    $alice = User::factory()->create();
    $bob = User::factory()->create();
    $conversation = createConversation($alice, $bob);

    ChatMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_id' => $alice->id,
        'payload' => fakeChatPayload(),
    ]);

    $chatShow = $this->actingAs($alice)->get(route('chat.show', $bob));
    $chatIndex = $this->actingAs($alice)->get(route('chat.index'));

    $chatShow->assertSuccessful();
    $chatIndex->assertSuccessful();

    expect($chatShow->getContent())
        ->not->toMatch('/x-(text|show|bind:[^=]+)="[^"]*`[^"]*"/')
        ->toContain("x-text=\"'".__('Partner fingerprint').": ' + partnerFingerprint\"")
        ->toContain(':disabled="!canSendMessage"');

    expect($chatIndex->getContent())
        ->not->toMatch('/x-(text|show|bind:[^=]+)="[^"]*`[^"]*"/')
        ->toContain('data-utc-datetime="')
        ->not->toMatch('/(?<!utc-)data-datetime="/');
});
