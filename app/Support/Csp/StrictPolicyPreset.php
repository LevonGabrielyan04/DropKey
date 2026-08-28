<?php

declare(strict_types=1);

namespace App\Support\Csp;

use App\Support\ApplicationOrigins;
use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Scheme;
use Spatie\Csp\Value;

class StrictPolicyPreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $origins = $this->allowedOrigins();

        $policy
            ->add(Directive::DEFAULT, $origins)
            ->add(Directive::BASE, $origins)
            ->add(Directive::CONNECT, $this->connectSources())
            ->add(Directive::FONT, $origins)
            ->add(Directive::FORM_ACTION, $origins)
            ->add(Directive::FRAME, $this->frameSources())
            ->add(Directive::FRAME_ANCESTORS, Keyword::NONE)
            ->add(Directive::IMG, $origins)
            ->add(Directive::MANIFEST, $origins)
            ->add(Directive::MEDIA, $origins)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::SCRIPT, $this->scriptSources($origins))
            ->add(Directive::STYLE, $origins)
            ->add(Directive::WORKER, [...$origins, Scheme::BLOB])
            ->addNonce(Directive::SCRIPT)
            ->addNonce(Directive::STYLE);

        if (str_starts_with(config('app.url'), 'https://')) {
            $policy->add(Directive::UPGRADE_INSECURE_REQUESTS, Value::NO_VALUE);
        }
    }

    /**
     * @return list<string>
     */
    private function allowedOrigins(): array
    {
        $origins = ApplicationOrigins::webAuthn();

        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return array_values(array_unique($origins));
        }

        return array_values(array_unique([
            ...$origins,
            ...$this->localDevAppOrigins(),
            ...$this->viteDevOrigins(),
            ...$this->loopbackViteDevOrigins(),
            ...$this->lanDevOrigins(),
        ]));
    }

    /**
     * @param  list<string>  $origins
     * @return list<string|Keyword>
     */
    private function scriptSources(array $origins): array
    {
        $sources = [...$origins, Keyword::UNSAFE_WEB_ASSEMBLY_EXECUTION];

        if (config('turnstile.enabled')) {
            $sources[] = 'https://challenges.cloudflare.com';
        }

        return $sources;
    }

    /**
     * @return list<string|Keyword>
     */
    private function frameSources(): array
    {
        if (config('turnstile.enabled')) {
            return ['https://challenges.cloudflare.com'];
        }

        return [Keyword::NONE];
    }

    /**
     * @return list<string>
     */
    private function connectSources(): array
    {
        $sources = [
            ...$this->allowedOrigins(),
            ...$this->reverbConnectSources(),
            ...$this->cloudUploadConnectSources(),
            'https://api.pwnedpasswords.com',
        ];

        if (in_array(config('app.env'), ['local', 'testing'], true)) {
            $sources = [
                ...$sources,
                ...$this->viteDevWebSocketOrigins(),
                ...$this->loopbackViteDevWebSocketOrigins(),
                ...$this->lanDevWebSocketOrigins(),
            ];
        }

        if (! config('turnstile.enabled')) {
            return array_values(array_unique($sources));
        }

        $sources[] = 'https://challenges.cloudflare.com';

        return array_values(array_unique($sources));
    }

    /**
     * @return list<string>
     */
    private function localDevAppOrigins(): array
    {
        $devHost = $this->viteDevHost();

        if ($devHost === null || $this->isLoopbackHost($devHost)) {
            return [];
        }

        return ["https://{$devHost}"];
    }

    /**
     * @return list<string>
     */
    private function viteDevOrigins(): array
    {
        $host = $this->viteDevHost();

        if ($host === null) {
            return [];
        }

        $origins = [];

        foreach ($this->viteDevPorts() as $port) {
            $origins[] = "https://vite.{$host}:{$port}";
            $origins[] = "https://{$host}:{$port}";
        }

        return $origins;
    }

    /**
     * @return list<string>
     */
    private function viteDevWebSocketOrigins(): array
    {
        $host = $this->viteDevHost();

        if ($host === null) {
            return [];
        }

        $origins = [];

        foreach ($this->viteDevPorts() as $port) {
            $origins[] = "wss://vite.{$host}:{$port}";
            $origins[] = "wss://{$host}:{$port}";
        }

        return $origins;
    }

    private function viteDevHost(): ?string
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($appHost) || $appHost === '') {
            return null;
        }

        if ($this->isLoopbackHost($appHost)) {
            return basename(base_path()).'.test';
        }

        $requestHost = request()->getHost();

        if ($requestHost !== '' && ! $this->isLoopbackHost($requestHost)) {
            return $requestHost;
        }

        return $appHost;
    }

    private function isLoopbackHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * @return list<int>
     */
    private function viteDevPorts(): array
    {
        return [5173];
    }

    /**
     * @return list<int>
     */
    private function lanDevAppPorts(): array
    {
        $ports = [8000];

        $appPort = parse_url((string) config('app.url'), PHP_URL_PORT);

        if (is_int($appPort)) {
            $ports[] = $appPort;
        }

        return array_values(array_unique($ports));
    }

    /**
     * @return list<string>
     */
    private function lanDevHosts(): array
    {
        return ['localhost'];
    }

    /**
     * @return list<string>
     */
    private function lanDevOrigins(): array
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return [];
        }

        $origins = [];

        foreach ($this->lanDevHosts() as $host) {
            $origins[] = "http://{$host}";
            $origins[] = "https://{$host}";

            foreach ($this->lanDevAppPorts() as $port) {
                $origins[] = "http://{$host}:{$port}";
                $origins[] = "https://{$host}:{$port}";
            }

            foreach ($this->viteDevPorts() as $port) {
                $origins[] = "http://{$host}:{$port}";
                $origins[] = "https://{$host}:{$port}";
                $origins[] = "https://vite.{$host}:{$port}";
            }
        }

        return $origins;
    }

    /**
     * @return list<string>
     */
    private function lanDevWebSocketOrigins(): array
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return [];
        }

        $origins = [];

        foreach ($this->lanDevHosts() as $host) {
            foreach ($this->viteDevPorts() as $port) {
                $origins[] = "ws://{$host}:{$port}";
                $origins[] = "wss://{$host}:{$port}";
                $origins[] = "wss://vite.{$host}:{$port}";
            }
        }

        return $origins;
    }

    /**
     * @return list<string>
     */
    private function loopbackViteDevOrigins(): array
    {
        if (! $this->shouldAllowLoopbackViteOrigins()) {
            return [];
        }

        $origins = [];

        foreach ($this->viteDevPorts() as $port) {
            $origins[] = "http://127.0.0.1:{$port}";
            $origins[] = "http://localhost:{$port}";
        }

        return $origins;
    }

    /**
     * @return list<string>
     */
    private function loopbackViteDevWebSocketOrigins(): array
    {
        if (! $this->shouldAllowLoopbackViteOrigins()) {
            return [];
        }

        $origins = [];

        foreach ($this->viteDevPorts() as $port) {
            $origins[] = "ws://127.0.0.1:{$port}";
            $origins[] = "ws://localhost:{$port}";
        }

        return $origins;
    }

    private function shouldAllowLoopbackViteOrigins(): bool
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $this->isLoopbackHost($appHost)) {
            return true;
        }

        $requestHost = request()->getHost();

        return $requestHost !== '' && $this->isLoopbackHost($requestHost);
    }

    /**
     * Allow browser uploads to S3-compatible disks (R2).
     *
     * With use_path_style_endpoint=false, temporaryUploadUrl() signs
     * virtual-hosted URLs ({bucket}.{endpoint-host}), which must be listed
     * separately from the bare endpoint origin.
     *
     * @return list<string>
     */
    private function cloudUploadConnectSources(): array
    {
        $origins = [];

        foreach (['r2', 's3'] as $disk) {
            foreach ($this->cloudUploadDiskOrigins($disk) as $origin) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
    }

    /**
     * @return list<string>
     */
    private function cloudUploadDiskOrigins(string $disk): array
    {
        $urls = [
            config("filesystems.disks.{$disk}.url"),
            config("filesystems.disks.{$disk}.endpoint"),
        ];

        $origins = [];

        foreach ($urls as $url) {
            $origin = $this->originFromUrl(is_string($url) ? $url : null);

            if ($origin !== null) {
                $origins[] = $origin;
            }
        }

        if (filter_var(config("filesystems.disks.{$disk}.use_path_style_endpoint"), FILTER_VALIDATE_BOOLEAN)) {
            return $origins;
        }

        $bucket = config("filesystems.disks.{$disk}.bucket");
        $endpoint = config("filesystems.disks.{$disk}.endpoint");

        if (! is_string($bucket) || $bucket === '' || ! is_string($endpoint) || $endpoint === '') {
            return $origins;
        }

        $parts = parse_url($endpoint);

        if (! isset($parts['scheme'], $parts['host'])) {
            return $origins;
        }

        $virtualHostedOrigin = $parts['scheme'].'://'.$bucket.'.'.$parts['host'];

        if (isset($parts['port'])) {
            $virtualHostedOrigin .= ':'.$parts['port'];
        }

        $origins[] = $virtualHostedOrigin;

        return $origins;
    }

    private function originFromUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }

    /**
     * @return list<string>
     */
    private function reverbConnectSources(): array
    {
        $host = config('broadcasting.connections.reverb.client.host');
        $port = config('broadcasting.connections.reverb.client.port');
        $scheme = config('broadcasting.connections.reverb.client.scheme', 'https');

        if (! is_string($host) || $host === '' || ! is_numeric($port)) {
            return [];
        }

        $webSocketScheme = $scheme === 'https' ? 'wss' : 'ws';
        $origins = ["{$webSocketScheme}://{$host}:{$port}"];

        if ($host === 'localhost') {
            $origins[] = "{$webSocketScheme}://127.0.0.1:{$port}";
        } elseif ($host === '127.0.0.1') {
            $origins[] = "{$webSocketScheme}://localhost:{$port}";
        }

        return $origins;
    }
}
