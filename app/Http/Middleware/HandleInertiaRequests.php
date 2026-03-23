<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inventorai\SDK\InventoraiClient;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Shared props available to all Inertia pages.
     *
     * The Inventorai /token endpoint returns the team_id and Reverb
     * websocket config. Cached for 5 minutes to avoid hitting the
     * API on every page load. No secrets are exposed — the API token
     * stays server-side.
     */
    public function share(Request $request): array
    {
        $tokenData = $this->getTokenData();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'ziggy' => fn () => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
            'reverb' => $tokenData['reverb'] ?? null,
            'team_id' => $tokenData['team_id'] ?? null,
        ];
    }

    /**
     * Fetch token data (team_id + reverb config) from the Inventorai API.
     * Cached for 5 minutes. Returns empty array if the API is down.
     */
    private function getTokenData(): array
    {
        return Cache::remember('inventorai:token_data', 300, function () {
            try {
                return app(InventoraiClient::class)->token()['data'] ?? [];
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
