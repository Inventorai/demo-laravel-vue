import Echo from 'laravel-echo';
import { usePage } from '@inertiajs/vue3';

let echo: Echo<'reverb'> | null = null;

/**
 * Creates a singleton Echo instance connected to Reverb.
 *
 * Broadcasting auth is proxied through /broadcasting/auth so the
 * Inventorai API token never leaves the server.
 *
 * Returns null if Reverb is not configured.
 */
export function useEcho(): Echo<'reverb'> | null {
    if (echo) {
        return echo;
    }

    const page = usePage();
    const { reverb } = page.props as any;

    if (!reverb?.key) {
        return null;
    }

    echo = new Echo({
        broadcaster: 'reverb',
        key: reverb.key,
        wsHost: reverb.host,
        wsPort: reverb.port,
        wssPort: reverb.port,
        forceTLS: reverb.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
    });

    window.Echo = echo;

    return echo;
}
