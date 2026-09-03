<?php

use Tests\TestCase;

uses(TestCase::class);

test('docker nginx config restores real client ip only from cloudflared tunnel peer', function () {
    $cloudflareConfig = file_get_contents(base_path('docker/nginx/cloudflare.conf'));

    expect($cloudflareConfig)
        ->toContain('real_ip_header CF-Connecting-IP;')
        ->toContain('real_ip_recursive on;')
        ->toContain('set_real_ip_from 172.30.200.3/32;')
        ->toContain('geo $realip_remote_addr $is_trusted_tunnel_origin')
        ->toContain('172.30.200.3/32 1;')
        ->not->toContain('set_real_ip_from 10.0.0.0/8;')
        ->not->toContain('set_real_ip_from 172.16.0.0/12;')
        ->not->toContain('set_real_ip_from 192.168.0.0/16;')
        ->not->toContain('10.0.0.0/8 1;')
        ->toContain('map $http_cf_visitor $cf_visitor_scheme');
});

test('docker nginx config passes controlled forwarded headers to php', function () {
    $defaultConfig = file_get_contents(base_path('docker/nginx/default.conf'));
    $proxyConfig = file_get_contents(base_path('docker/nginx/proxy-forwarded.conf'));

    expect($defaultConfig)->toContain('include /etc/nginx/conf.d/proxy-forwarded.conf;');

    expect($defaultConfig)
        ->toContain('listen 127.0.0.1:8080;')
        ->toContain('listen 172.30.200.2:8080;')
        ->not->toContain('listen 8080;')
        ->not->toContain('listen [::]:8080;');

    expect($proxyConfig)
        ->toContain('fastcgi_param HTTP_X_FORWARDED_FOR $remote_addr;')
        ->toContain('fastcgi_param HTTP_X_FORWARDED_PROTO $forwarded_proto_resolved;')
        ->toContain('fastcgi_param HTTP_X_FORWARDED_HOST $http_host;')
        ->toContain('fastcgi_param HTTP_X_FORWARDED_PORT $forwarded_port;')
        ->toContain('fastcgi_param REMOTE_ADDR 127.0.0.1;')
        ->toContain('fastcgi_param HTTP_CF_CONNECTING_IP "";')
        ->toContain('fastcgi_param HTTP_CF_VISITOR "";')
        ->not->toContain('fastcgi_param REMOTE_ADDR $remote_addr;');
});

test('docker nginx websocket upgrade map lives inside the http block', function () {
    $nginxConfig = file_get_contents(base_path('docker/nginx/nginx.conf'));

    expect(preg_match('/^map\s+\$http_upgrade/m', $nginxConfig))->toBe(0);

    expect(preg_match('/http\s*\{[\s\S]*map\s+\$http_upgrade\s+\$connection_upgrade/m', $nginxConfig))
        ->toBe(1);

    expect($nginxConfig)
        ->toContain('map $http_upgrade $connection_upgrade')
        ->toContain("''      close;");
});

test('docker nginx proxies reverb websocket and api paths', function () {
    $defaultConfig = file_get_contents(base_path('docker/nginx/default.conf'));

    expect($defaultConfig)
        ->toContain('location ~ ^/(app|apps) {')
        ->toContain('proxy_pass http://127.0.0.1:8081;')
        ->toContain('proxy_set_header Upgrade $http_upgrade;')
        ->toContain('proxy_set_header Connection $connection_upgrade;');
});

test('docker supervisor runs reverb server in app container', function () {
    $supervisorConfig = file_get_contents(base_path('docker/supervisor/supervisord.conf'));

    expect($supervisorConfig)
        ->toContain('[unix_http_server]')
        ->toContain('file=/dev/shm/supervisor.sock')
        ->toContain('[supervisorctl]')
        ->toContain('serverurl=unix:///dev/shm/supervisor.sock')
        ->toContain('[program:reverb]')
        ->toContain('command=php artisan reverb:start --host=0.0.0.0 --port=8081')
        ->toContain('user=app');
});

test('docker compose sets reverb server bind address for app container', function () {
    $compose = file_get_contents(base_path('docker-compose.yml'));

    expect($compose)
        ->toContain('REVERB_SERVER_HOST: 0.0.0.0')
        ->toContain('REVERB_SERVER_PORT: "8081"');
});

test('docker nginx http block loads cloudflare configuration', function () {
    $nginxConfig = file_get_contents(base_path('docker/nginx/nginx.conf'));

    expect($nginxConfig)->toContain('include /etc/nginx/conf.d/cloudflare.conf;');
});

test('docker env example trusts only nginx as upstream proxy', function () {
    $dockerEnvExample = file_get_contents(base_path('.env.docker.example'));

    expect($dockerEnvExample)
        ->toContain('TRUSTED_PROXIES=127.0.0.1')
        ->toContain('TUNNEL_TOKEN=')
        ->toContain('http://172.30.200.2:8080');
});

test('docker compose does not publish app port in base stack', function () {
    $compose = file_get_contents(base_path('docker-compose.yml'));

    expect($compose)
        ->not->toContain('ports:')
        ->toContain('cloudflare/cloudflared:2026.8.3')
        ->toContain('profiles:')
        ->toContain('- tunnel');
});

test('docker compose isolates tunnel network from queue and scheduler', function () {
    $compose = file_get_contents(base_path('docker-compose.yml'));

    $serviceBlock = function (string $service) use ($compose): string {
        preg_match('/^ {2}'.$service.':\n(.*?)(?=\n {2}\w|\nnetworks:|\nvolumes:)/ms', $compose, $matches);

        return $matches[1] ?? '';
    };

    expect($compose)
        ->toContain('subnet: 172.30.200.0/29')
        ->toContain('ipv4_address: 172.30.200.2')
        ->toContain('ipv4_address: 172.30.200.3')
        ->toContain('internal: true');

    expect($serviceBlock('queue'))
        ->toContain('backend: {}')
        ->not->toContain('tunnel:');

    expect($serviceBlock('scheduler'))
        ->toContain('backend: {}')
        ->not->toContain('tunnel:');
});

test('docker compose dev override binds app port to localhost only', function () {
    $devCompose = file_get_contents(base_path('docker-compose.dev.yml'));

    expect($devCompose)->toContain('127.0.0.1:${APP_PORT:-8080}:8080');
});

test('docker supervisor runs pulse check daemon in app container', function () {
    $supervisorConfig = file_get_contents(base_path('docker/supervisor/supervisord.conf'));

    expect($supervisorConfig)
        ->toContain('[program:pulse-check]')
        ->toContain('command=php artisan pulse:check')
        ->toContain('user=app');
});

test('docker compose sets pulse server name for app container', function () {
    $compose = file_get_contents(base_path('docker-compose.yml'));

    expect($compose)->toContain('PULSE_SERVER_NAME: app');
});

test('docker nginx rate limit config defines auth zones by real client ip', function () {
    $rateLimitConfig = file_get_contents(base_path('docker/nginx/rate-limit.conf'));

    expect($rateLimitConfig)
        ->toContain('map $request_method $auth_limit_key')
        ->toContain('limit_req_zone $auth_limit_key zone=auth:10m rate=5r/m;')
        ->toContain('map $request_method $auth_post_global_limit_key')
        ->toContain('limit_req_zone $auth_post_global_limit_key zone=auth_post_global:10m rate=15r/s;')
        ->toContain('limit_req_status 429;')
        ->not->toContain('auth_login')
        ->not->toContain('auth_register')
        ->not->toContain('r/h');
});

test('docker nginx http block loads rate limit configuration after cloudflare', function () {
    $nginxConfig = file_get_contents(base_path('docker/nginx/nginx.conf'));

    expect($nginxConfig)
        ->toContain('include /etc/nginx/conf.d/cloudflare.conf;')
        ->toContain('include /etc/nginx/conf.d/rate-limit.conf;');

    $cloudflarePos = strpos($nginxConfig, 'include /etc/nginx/conf.d/cloudflare.conf;');
    $rateLimitPos = strpos($nginxConfig, 'include /etc/nginx/conf.d/rate-limit.conf;');

    expect($cloudflarePos)->toBeLessThan($rateLimitPos);
});

test('docker nginx default config rate limits post login and register only', function () {
    $defaultConfig = file_get_contents(base_path('docker/nginx/default.conf'));
    $rateLimitConfig = file_get_contents(base_path('docker/nginx/rate-limit.conf'));

    expect($defaultConfig)
        ->toContain('location = /login {')
        ->toContain('location = /register {')
        ->toContain('limit_req zone=auth_post_global;')
        ->toContain('limit_req zone=auth burst=2 nodelay;')
        ->not->toContain('limit_except')
        ->not->toContain('auth_login')
        ->not->toContain('auth_register')
        ->not->toContain('auth_hourly');

    expect(substr_count($defaultConfig, 'limit_req zone=auth burst=2 nodelay;'))->toBe(2);

    expect($rateLimitConfig)
        ->toContain('GET     "";')
        ->toContain('HEAD    "";');
});

test('dockerfile copies nginx rate limit configuration', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    expect($dockerfile)->toContain('COPY docker/nginx/rate-limit.conf /etc/nginx/conf.d/rate-limit.conf');
});

test('dockerfile installs pcntl so reverb signal handling can start', function () {
    $dockerfile = file_get_contents(base_path('Dockerfile'));

    // reverb:start references SIGINT/SIGTERM/SIGTSTP (pcntl constants).
    expect($dockerfile)->toContain('pcntl');
});

test('php cli has unlimited execution time while fpm is capped', function () {
    $phpIni = file_get_contents(base_path('docker/php/php.ini'));
    $wwwConf = file_get_contents(base_path('docker/php/www.conf'));

    expect($phpIni)->toContain('max_execution_time = 0');
    expect($wwwConf)->toContain('php_admin_value[max_execution_time] = 60');
});
