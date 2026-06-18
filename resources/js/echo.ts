import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Laravel Echo wired to Laravel Reverb (Pusher protocol) for real-time
 * graduation-status updates. Reverb is self-hosted; credentials come from
 * Vite env (public, non-secret app key only — never the app secret).
 */
declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo: Echo<'reverb'>;
    }
}

export function createEcho(): Echo<'reverb'> {
    window.Pusher = Pusher;

    const echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    window.Echo = echo;
    return echo;
}
