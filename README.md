# DropKey

DropKey is a **free and open-source** end-to-end encrypted application for sharing passwords and other secrets with specific people, and for private messaging between registered users. Secrets and chat messages are encrypted in the browser before they ever reach the server. The server stores only ciphertext and never sees decryption keys or passwords.

**Live:** [https://dropkey.site](https://dropkey.site)

---

## How it works

### One-time secret sharing (Sends)

1. **Create a Send** — Give it a name, add up to 100 registered viewer names, write the secret, and choose when it expires (1 hour to 30 days).
2. **Password protection** — If you set a password, the message is encrypted client-side with **AES-256-GCM**. The key is derived with **Argon2id** in a Web Worker. The password is stripped from the form before submission and is never sent to the server.
3. **Share with viewers** — Only registered users whose names you listed can open the Send. Owners can also view their own Sends.
4. **Decrypt in the browser** — Authorized viewers enter the shared password locally. Decryption runs off the main thread in a Web Worker.
5. **Automatic expiry** — Sends are permanently deleted after their expiry time. A scheduled task removes expired records every 30 minutes.

### Encrypted messenger

1. **Start a conversation** — Open **Messages**, enter a registered user's name, and open a pairwise encrypted channel.
2. **Identity keys** — Each user gets an **ECDH (P-256)** key pair in the browser. The public key is registered with the server; the private key stays local (optionally encrypted in IndexedDB for the session).
3. **Derived conversation keys** — Messages are encrypted with **AES-256-GCM** using a per-conversation key derived via **ECDH + HKDF**. Both parties derive the same key independently.
4. **Server relay only** — The server stores and relays opaque ciphertext. It cannot decrypt message content.
5. **Realtime delivery** — New messages are broadcast over private WebSocket channels via **Laravel Reverb** (Laravel Echo on the client). History is loaded once when the conversation opens; there is no HTTP polling.
6. **Verify your partner** — A fingerprint of the recipient's public key is shown so you can confirm you are talking to the right person.
7. **Auto-delete** — Each conversation can be configured to delete messages after 1 hour to 30 days. A scheduled task purges expired messages every 30 minutes.

---

### Security model

| Layer | What it protects |
| --- | --- |
| **Client-side E2E encryption** | Send and chat content from the server operator and database |
| **Laravel `encrypted` cast** | Send payloads at rest on the server |
| **ECDH + HKDF conversation keys** | Per-conversation chat encryption between two users |
| **Identity key fingerprints** | Wrong recipient or key substitution in chat |
| **Opaque chat relay storage** | Chat ciphertext stored as-is; the server never decrypts it |
| **Private Reverb channels** | Realtime chat events limited to conversation participants |
| **Passkeys + 2FA + email verification** | Account access |
| **Per-Send viewer ACL** | Who can open a given Send |
| **Per-conversation auto-delete** | Chat message retention limits |
| **Short-lived Valkey sessions** | Session hijacking surface |
| **Strict Content Security Policy** | XSS and injection |

> **Note:** The application cannot decrypt password-protected Sends or chat messages. If you lose a Send password or your local identity key, the content cannot be recovered.

---

## Requirements

* PHP 8.5+
* [Composer](https://getcomposer.org/)
* Node.js 24+ (LTS) and npm
* MariaDB 12+ (LTS)
* Valkey 8+ or Redis 8+ (Redis-compatible)

---

## Local development

### 1. Clone and install dependencies

```bash
git clone <repository-url> passshare
cd passshare

composer install
npm install

```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate

```

Generate a separate secret for passkey user handles (same `base64:` format as `APP_KEY`):

```bash
php artisan tinker --execute 'echo "base64:".base64_encode(random_bytes(32));'

```

Set the output as `PASSKEYS_USER_HANDLE_SECRET` in `.env`.

Configure database and Valkey (or Redis) credentials in `.env`. The app expects MariaDB and a Redis-compatible store for sessions, cache, and queues.

Set Reverb credentials (`REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`) and host/port (`REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME`). Ensure `BROADCAST_CONNECTION=reverb` and `QUEUE_CONNECTION=redis`.

### 3. Database

Create the MariaDB database, then run migrations:

```bash
php artisan migrate

```

### 4. Frontend assets

```bash
npm run build

```

### 5. Run the app (HTTP, Reverb, queue, Vite)

Chat broadcasting requires **Laravel Reverb** and a **queue worker**. For local development, keep these running:

```bash
php artisan reverb:start
php artisan queue:work --queue=broadcasts,default

```

For development with hot reload, Vite, and a queue listener:

```bash
composer run dev

```

`composer run dev` starts the HTTP server, queue listener (`broadcasts` then `default`), and Vite — but **not** Reverb. Still run `php artisan reverb:start` in a separate terminal.

Or run processes separately:

```bash
php artisan serve
php artisan reverb:start
php artisan queue:work --queue=broadcasts,default
npm run dev

```

### 6. Scheduled tasks

Expired Sends, chat messages, and uploaded files are removed by `system:cleanup-expired`, scheduled **every 30 minutes** in `bootstrap/app.php`.

* **Local development:** keep the scheduler running in a separate terminal:
```bash
php artisan schedule:work

```


* **Production (cron):** add a single cron entry on the server:
```cron
* * * * * cd /path/to/passshare && php artisan schedule:run >> /dev/null 2>&1

```



You can also run the cleanup manually:

```bash
php artisan system:cleanup-expired

```

---

## Configuration

Key environment variables (see `.env.example` for the full list):

| Variable | Description |
| --- | --- |
| `APP_URL` | Public URL of the app (required for passkeys) |
| `PASSKEYS_USER_HANDLE_SECRET` | Secret for WebAuthn user handle derivation |
| `BROADCAST_CONNECTION` | Broadcasting driver (`reverb` for realtime chat) |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | Reverb application credentials |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | Public WebSocket endpoint for Laravel Echo |
| `REVERB_BROADCAST_HOST` / `REVERB_BROADCAST_PORT` / `REVERB_BROADCAST_SCHEME` | Internal HTTP API Laravel uses to publish to Reverb (Docker/production) |
| `SESSION_LIFETIME` | Session lifetime in minutes (default: 5) |
| `MAX_SENDS_PER_USER` | Maximum active Sends per user (default: 15) |
| `MAX_MESSAGE_LENGTH` | Plaintext message limit before encryption (default: 1000) |
| `ENCRYPTED_MAX_MESSAGE_LENGTH` | Stored ciphertext limit (default: 5372) |
| `SEND_CACHE_TTL` | Send list cache TTL in minutes (default: 60) |
| `CHAT_ENCRYPTED_MAX_LENGTH` | Stored ciphertext limit per chat message (default: 8192) |
| `CHAT_POLL_BATCH_SIZE` | Messages fetched per history request when opening a conversation (default: 100) |
| `TRUSTED_PROXIES` | Proxy IPs when behind nginx or a tunnel (e.g. `127.0.0.1`) |

Password-protected Sends require a password of at least **15 characters** (`config/send.php`).

---

## Docker deployment

The repository includes a production-oriented Docker Compose stack (app, queue worker, scheduler, MariaDB, Valkey, optional Cloudflare Tunnel). The Compose service is named `redis`; Laravel connects via the usual `REDIS_*` environment variables.

Reverb runs inside the **app** container (Supervisor). The **queue** service runs `php artisan queue:work redis --queue=broadcasts,default` so chat broadcasts are processed ahead of other jobs. Configure `REVERB_*` and `VITE_REVERB_*` (or equivalent build-time values) as in `.env.docker.example`.

```bash
cp .env.docker.example .env
# Set APP_KEY, DB passwords, PASSKEYS_USER_HANDLE_SECRET, REVERB_*, and optionally TUNNEL_TOKEN

# Local Docker (localhost port)
./docker/bin/compose.sh up

# Production (Cloudflare Tunnel only, no published host port)
./docker/bin/compose.sh up --tunnel

```

Compose reads port mapping from `.env.compose` and application secrets from `.env` via `env_file`, so special characters in passwords are not mangled by variable interpolation.

The **scheduler** service runs `php artisan schedule:work` automatically. No separate cron setup is needed inside Docker.

Optional build-time secrets for the Livewire Flux license:

```bash
FLUX_USERNAME=...
FLUX_LICENSE_KEY=...

```

### Automated deployment (GitHub Actions)

Pushes to `main` run tests first; when they pass, the **deploy** workflow builds the Docker image, pushes it to [GitHub Container Registry](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry), and restarts the stack on your server over SSH.

**One-time server setup**

1. Install [Docker Engine](https://docs.docker.com/engine/install/) and the Compose plugin.
2. Clone the repo (e.g. `/opt/passshare`), then `cp .env.docker.example .env` and configure secrets.
3. Set `GHCR_USERNAME` (your GitHub account) and create a [fine-grained PAT](https://github.com/settings/tokens) with **read:packages** as `GHCR_TOKEN` in `.env`.
4. Add the deploy user's SSH public key (matching `DEPLOY_SSH_KEY` in GitHub).

**GitHub repository secrets** (Settings → Secrets and variables → Actions):

| Secret | Purpose |
| --- | --- |
| `DEPLOY_HOST` | Server hostname or IP |
| `DEPLOY_USER` | SSH user |
| `DEPLOY_SSH_KEY` | Private SSH key (PEM) |
| `DEPLOY_PATH` | Repo path on the server (e.g. `/opt/passshare`) |
| `FLUX_USERNAME` / `FLUX_LICENSE_KEY` | Flux license (build-time; same as CI) |

Create a **production** environment in GitHub (optional) for deployment approval gates.

Manual deploy: Actions → **deploy** → **Run workflow**.

---

## Testing

* **PHP (Pest):**
```bash
php artisan test --compact

```


* **JavaScript (Vitest):**
```bash
npm test

```


* **Full CI-style check:**
```bash
composer test

```



---

## Tech stack

* [Laravel 13](https://laravel.com/)
* [Livewire 4](https://livewire.laravel.com/) + [Flux UI](https://fluxui.dev/)
* [Laravel Fortify](https://laravel.com/docs/fortify) — registration, 2FA, passkeys
* [Laravel Reverb](https://laravel.com/docs/reverb) — WebSocket broadcasting for realtime chat
* [Spatie CSP](https://github.com/spatie/laravel-csp) — strict Content Security Policy
* Client crypto — Web Crypto API, ECDH (P-256) + HKDF + AES-GCM (messenger), Argon2id (`hash-wasm`) + AES-GCM (Sends), Web Workers
