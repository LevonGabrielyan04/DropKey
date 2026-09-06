import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { appFetch, ResponseRedirectError } from './http.js';

window.Pusher = Pusher;

const reverbConfig = window.__reverbConfig ?? {
    key: import.meta.env.VITE_REVERB_APP_KEY,
    host: import.meta.env.VITE_REVERB_HOST,
    port: import.meta.env.VITE_REVERB_PORT,
    scheme: import.meta.env.VITE_REVERB_SCHEME ?? 'https',
};

if (reverbConfig.key && reverbConfig.host) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbConfig.key,
        wsHost: reverbConfig.host,
        wsPort: reverbConfig.port ?? 80,
        wssPort: reverbConfig.port ?? 443,
        forceTLS: (reverbConfig.scheme ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                appFetch('/broadcasting/auth', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        socket_id: socketId,
                        channel_name: channel.name,
                    }),
                })
                    .then(async (response) => {
                        if (! response.ok) {
                            callback(true, await response.text());

                            return;
                        }

                        callback(false, await response.json());
                    })
                    .catch((error) => {
                        if (error instanceof ResponseRedirectError) {
                            callback(true, error);

                            return;
                        }

                        callback(true, error);
                    });
            },
        }),
    });
}
