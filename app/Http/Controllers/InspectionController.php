<?php

namespace App\Http\Controllers;

use App\Services\ApiActivityTracker;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Inventorai\Laravel\Facades\Inventorai;
use Inventorai\SDK\Resources\InspectionAreas;
use Inventorai\SDK\Resources\InspectionItems;
use Inventorai\SDK\Resources\Inspections;

/**
 * Demonstrates the Inventorai SDK's Inspections resource.
 *
 * Uses Inventorai::inspections() to list, filter, and view inspections.
 * The show page demonstrates editing areas and items via the SDK.
 *
 * Important: The API defaults to scope=mine (only inspections assigned to
 * the authenticated user as inspector). For third-party API tokens, pass
 * scope=all to see all team inspections. The token must have the
 * 'inspections:all' ability for this to work.
 *
 * Inspection types are grouped into two categories:
 * - Tenancy: move_in, periodic, move_out
 * - Non-tenancy: vacant, pre_tenancy, landlord_only, between_tenancies
 *
 * Editable area fields: name, condition (poor|fair|good|excellent),
 *   cleanliness (dirty|fair|clean|spotless), notes
 *
 * Editable item fields: name, condition, cleanliness, description, notes
 *
 * SDK errors are handled by the global exception handler in bootstrap/app.php.
 *
 * @see Inspections
 * @see InspectionAreas
 * @see InspectionItems
 */
class InspectionController extends Controller
{
    /**
     * List inspections with optional type and status filtering.
     */
    public function index(Request $request): Response
    {
        $params = [
            'per_page' => 25,
            'page' => $request->integer('page', 1),
            'scope' => 'all',
            'include' => ['property', 'inspector'],
        ];

        if ($request->filled('status')) {
            $params['filter']['status'] = $request->input('status');
        }

        if ($request->filled('type')) {
            $params['filter']['type'] = $request->input('type');
        }

        $response = ApiActivityTracker::track('GET', '/inspections', fn () => Inventorai::inspections()->list($params)
        );

        $inspections = collect((array) ($response['data'] ?? []))
            ->map(fn ($i) => $this->formatDates($i))
            ->all();

        return Inertia::render('Inspections/Index', [
            'inspections' => $inspections,
            'meta' => $response['meta'] ?? null,
            'filters' => $request->only(['status', 'type', 'page']),
        ]);
    }

    /**
     * Show a single inspection with all related data in one call.
     *
     * Demonstrates the canonical read pattern: assemble everything you need
     * through `include` rather than firing follow-up requests to
     * inspectionAreas()->list(), inspectionItems()->list(), etc.
     *
     * The granular sub-resource SDKs are reserved for *writes* (see
     * updateArea / updateItem / uploadAreaPhoto below) and mobile sync
     * (paginating one slice, re-pulling after a change).
     */
    public function show(string $id): Response
    {
        $response = ApiActivityTracker::track('GET', "/inspections/{$id}", fn () => Inventorai::inspections()->get($id, [
            'include' => [
                'property', 'property.currentTenancy.tenants',
                'inspector',
                'areas.items.elements',
                'meterReadings', 'keysFobs',
                'assetChecks.propertyAsset.propertyArea',
                'complianceForms.sections.fields.responses',
            ],
        ])
        );

        $inspection = $response['data'] ?? $response;
        $inspection = $this->formatDates($inspection);

        return Inertia::render('Inspections/Show', [
            'inspection' => $inspection,
        ]);
    }

    /**
     * Update an area's condition, cleanliness, or notes.
     *
     * Uses Inventorai::inspectionAreas()->update() which sends a
     * PATCH request to /inspections/{id}/areas/{areaId}.
     */
    public function updateArea(string $inspectionId, string $areaId, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'condition' => ['nullable', 'in:poor,fair,good,excellent'],
            'cleanliness' => ['nullable', 'in:dirty,fair,clean,spotless'],
            'notes' => ['nullable', 'string'],
        ]);

        ApiActivityTracker::track('PATCH', "/inspections/{$inspectionId}/areas/{$areaId}", fn () => Inventorai::inspectionAreas()->update($inspectionId, $areaId, $data)
        );

        return back()->with('success', 'Area updated.');
    }

    /**
     * Update an item's condition, cleanliness, description, or notes.
     *
     * Uses Inventorai::inspectionItems()->update() which sends a
     * PATCH request to /inspections/{id}/items/{itemId}.
     */
    public function updateItem(string $inspectionId, string $itemId, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'condition' => ['nullable', 'in:poor,fair,good,excellent'],
            'cleanliness' => ['nullable', 'in:dirty,fair,clean,spotless'],
            'description' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        ApiActivityTracker::track('PATCH', "/inspections/{$inspectionId}/items/{$itemId}", fn () => Inventorai::inspectionItems()->update($inspectionId, $itemId, $data)
        );

        return back()->with('success', 'Item updated.');
    }

    /**
     * Upload a photo to an area.
     *
     * Uses Inventorai::inspectionAreas()->uploadPhoto() which sends a
     * multipart POST to /inspections/{id}/areas/{areaId}/photos.
     */
    public function uploadAreaPhoto(string $inspectionId, string $areaId, Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'image', 'max:10240']]);

        ApiActivityTracker::track('POST', "/inspections/{$inspectionId}/areas/{$areaId}/photos", fn () => Inventorai::inspectionAreas()->uploadPhoto($inspectionId, $areaId, $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName())
        );

        return back()->with('success', 'Photo uploaded.');
    }

    /**
     * Upload a photo to an item.
     *
     * Uses Inventorai::inspectionItems()->uploadPhoto() which sends a
     * multipart POST to /inspections/{id}/items/{itemId}/photos.
     */
    public function uploadItemPhoto(string $inspectionId, string $itemId, Request $request): RedirectResponse
    {
        $request->validate(['file' => ['required', 'image', 'max:10240']]);

        ApiActivityTracker::track('POST', "/inspections/{$inspectionId}/items/{$itemId}/photos", fn () => Inventorai::inspectionItems()->uploadPhoto($inspectionId, $itemId, $request->file('file')->getRealPath(), $request->file('file')->getClientOriginalName())
        );

        return back()->with('success', 'Photo uploaded.');
    }

    /**
     * Search phrases for autocomplete suggestions.
     *
     * Proxies to Inventorai::phrases()->search() which hits
     * GET /phrases/search?q=...&category=...&limit=...
     *
     * The token needs the 'phrases:read' ability for this to work.
     */
    public function searchPhrases(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'category' => ['nullable', 'in:area,item,element'],
            'context' => ['nullable', 'string', 'max:100'],
            'items' => ['nullable', 'string', 'max:500'],
            'item_name' => ['nullable', 'string', 'max:100'],
        ]);

        $params = array_filter([
            'q' => $request->input('q'),
            'category' => $request->input('category', 'item'),
            'context' => $request->input('context'),
            'items' => $request->input('items'),
            'item_name' => $request->input('item_name'),
            'limit' => 20,
        ]);

        $response = ApiActivityTracker::track('GET', '/phrases/search', fn () => Inventorai::phrases()->search($params)
        );

        return response()->json($response['data']['phrases'] ?? []);
    }

    /**
     * Format ISO date strings to UK format (e.g. Mon 21 Apr 2026).
     *
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function formatDates(array $data): array
    {
        $dateFields = ['created_at', 'updated_at', 'inspection_date', 'scheduled_at', 'completed_at', 'started_at'];

        foreach ($dateFields as $field) {
            if (! empty($data[$field])) {
                try {
                    $data[$field] = Carbon::parse($data[$field])->format('D j M Y');
                } catch (\Exception) {
                }
            }
        }

        return $data;
    }
}
