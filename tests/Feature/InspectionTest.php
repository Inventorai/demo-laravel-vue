<?php

/**
 * Inspection page tests.
 *
 * Key things to know when working on inspections:
 * - The API defaults to scope=mine (only the token user's inspections)
 * - Pass scope=all to see all team inspections (requires inspections:all token ability)
 * - Types are grouped: Tenancy (move_in, periodic, move_out) and
 *   Non-Tenancy (vacant, pre_tenancy, landlord_only, between_tenancies)
 * - Available filters: status, type, property_id, inspector_id, from_date, to_date
 * - There is NO search filter on inspections (unlike properties)
 */

use App\Models\User;
use Inventorai\SDK\InventoraiClient;
use Inventorai\SDK\Exceptions\AuthenticationException;
use Inventorai\SDK\Exceptions\ApiException;
use Inventorai\SDK\Resources\Inspections;

beforeEach(function () {
    $this->user = User::factory()->create();
});

function mockInspections(Inspections $inspections): void
{
    $client = mock(InventoraiClient::class);
    $client->shouldReceive('inspections')->andReturn($inspections);
    app()->instance(InventoraiClient::class, $client);
}

test('inspections page renders for authenticated user', function () {
    $inspections = mock(Inspections::class);
    $inspections->shouldReceive('list')->andReturn([
        'data' => [],
        'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1],
    ]);
    mockInspections($inspections);

    $this->actingAs($this->user)
        ->get('/inspections')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Inspections/Index')
            ->has('inspections')
            ->has('meta')
        );
});

test('inspections page requests scope=all and includes', function () {
    $inspections = mock(Inspections::class);
    $inspections->shouldReceive('list')
        ->withArgs(fn ($params) => $params['scope'] === 'all'
            && in_array('property', $params['include'])
            && in_array('inspector', $params['include'])
        )
        ->andReturn(['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1]]);
    mockInspections($inspections);

    $this->actingAs($this->user)
        ->get('/inspections')
        ->assertOk();
});

test('inspections page passes status filter to API', function () {
    $inspections = mock(Inspections::class);
    $inspections->shouldReceive('list')
        ->withArgs(fn ($params) => ($params['filter']['status'] ?? null) === 'completed')
        ->andReturn(['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1]]);
    mockInspections($inspections);

    $this->actingAs($this->user)
        ->get('/inspections?status=completed')
        ->assertOk();
});

test('inspections page passes type filter to API', function () {
    $inspections = mock(Inspections::class);
    $inspections->shouldReceive('list')
        ->withArgs(fn ($params) => ($params['filter']['type'] ?? null) === 'move_in')
        ->andReturn(['data' => [], 'meta' => ['total' => 0, 'current_page' => 1, 'last_page' => 1]]);
    mockInspections($inspections);

    $this->actingAs($this->user)
        ->get('/inspections?type=move_in')
        ->assertOk();
});

test('inspections page redirects with error on auth failure', function () {
    $inspections = mock(Inspections::class);
    $inspections->shouldReceive('list')->andThrow(new AuthenticationException('Invalid token'));
    mockInspections($inspections);

    $this->actingAs($this->user)
        ->get('/inspections')
        ->assertRedirect()
        ->assertSessionHas('error');
});

test('inspections page redirects with error on API failure', function () {
    $inspections = mock(Inspections::class);
    $inspections->shouldReceive('list')->andThrow(new ApiException('Server error', 500));
    mockInspections($inspections);

    $this->actingAs($this->user)
        ->get('/inspections')
        ->assertRedirect()
        ->assertSessionHas('error');
});
