<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class WebAppManifestController extends Controller
{
    /**
     * Serve the web app manifest used for PWA installability.
     */
    public function __invoke(): Response
    {
        $name = (string) config('app.name');

        return response(
            json_encode([
                'name' => $name,
                'short_name' => $name,
                'description' => $name.' — end-to-end encrypted messaging between registered users.',
                'start_url' => '/chat',
                'scope' => '/',
                'display' => 'standalone',
                'background_color' => '#fafafa',
                'theme_color' => '#09090b',
                'icons' => [
                    [
                        'src' => '/icons/icon-192.png',
                        'sizes' => '192x192',
                        'type' => 'image/png',
                        'purpose' => 'any',
                    ],
                    [
                        'src' => '/icons/icon-512.png',
                        'sizes' => '512x512',
                        'type' => 'image/png',
                        'purpose' => 'any',
                    ],
                    [
                        'src' => '/icons/icon-512-maskable.png',
                        'sizes' => '512x512',
                        'type' => 'image/png',
                        'purpose' => 'maskable',
                    ],
                ],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            200,
            [
                'Content-Type' => 'application/manifest+json',
                'Cache-Control' => 'public, max-age=3600',
            ],
        );
    }
}
