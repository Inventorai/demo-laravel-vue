<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Inventorai\Laravel\Facades\Inventorai;
use Inertia\Inertia;

/**
 * Dashboard with overview stats from the Inventorai API.
 *
 * Chart data is cached for 5 minutes.
 * SDK errors are handled by the global exception handler in bootstrap/app.php.
 */
class DashboardController extends Controller
{
    private const CACHE_TTL = 300;

    public function index()
    {
        $dashboardData = Cache::remember('dashboard_stats', self::CACHE_TTL, function () {
            $properties = Inventorai::properties()->list(['per_page' => 100]);
            $inspections = Inventorai::inspections()->list(['per_page' => 100, 'scope' => 'all']);

            $propData = collect($properties['data'] ?? []);
            $inspData = collect($inspections['data'] ?? []);

            return [
                'stats' => [
                    'totalProperties' => $properties['meta']['total'] ?? $propData->count(),
                    'totalInspections' => $inspections['meta']['total'] ?? $inspData->count(),
                    'completedInspections' => $inspData->where('status', 'completed')->count(),
                    'inProgressInspections' => $inspData->where('status', 'in_progress')->count(),
                ],
                'charts' => [
                    'propertyTypes' => $propData->pluck('property_type')
                        ->countBy()
                        ->map(fn ($count, $type) => ['type' => $type, 'count' => $count])
                        ->values()
                        ->all(),

                    'inspectionTypes' => $inspData->pluck('type')
                        ->countBy()
                        ->map(fn ($count, $type) => ['type' => $type, 'count' => $count])
                        ->values()
                        ->all(),

                    'inspectionStatuses' => $inspData->pluck('status')
                        ->countBy()
                        ->map(fn ($count, $status) => ['status' => $status, 'count' => $count])
                        ->values()
                        ->all(),
                ],
            ];
        });

        return Inertia::render('Dashboard', $dashboardData);
    }
}
