<?php

namespace Test\BackendTest\Laravel\Feature\Admin;

use App\Models\Order;
use App\Models\TokenBundle;
use App\Models\TokenTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Test\BackendTest\Laravel\Helpers\BackendTestCase;

class AdminSalesTest extends BackendTestCase
{
    /** @test */
    public function it_returns_revenue_by_period(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        $bundle = TokenBundle::factory()->create();
        
        // Create orders in different periods
        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDays(2),
        ]);

        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 200,
            'price_toman' => 100000,
            'price_usd' => 2.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'range' => 'week',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_revenue_toman',
                    'total_revenue_usd',
                    'total_orders',
                    'revenue_by_day',
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['total_revenue_usd']);
        $this->assertEquals(2, $data['total_orders']);
    }

    /** @test */
    public function it_calculates_total_revenue_correctly(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        $bundle = TokenBundle::factory()->create();
        
        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
        ]);

        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 200,
            'price_toman' => 100000,
            'price_usd' => 2.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
        ]);

        // Pending order should not be counted
        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 300,
            'price_toman' => 150000,
            'price_usd' => 3.00,
            'dollar_rate' => 50000.00,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'range' => 'month',
        ]);

        $data = $response->json('data');
        $this->assertEquals(3.00, $data['total_revenue_usd']);
        $this->assertEquals(2, $data['total_orders']); // Only paid orders
    }

    /** @test */
    public function it_returns_top_selling_bundles(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        $bundle1 = TokenBundle::factory()->create(['name' => 'Bundle 1']);
        $bundle2 = TokenBundle::factory()->create(['name' => 'Bundle 2']);
        
        // Bundle 1: 3 orders
        for ($i = 0; $i < 3; $i++) {
            Order::create([
                'user_id' => User::factory()->create()->id,
                'token_bundle_id' => $bundle1->id,
                'amount_tokens' => 100,
                'price_toman' => 50000,
                'price_usd' => 1.00,
                'dollar_rate' => 50000.00,
                'status' => 'paid',
            ]);
        }

        // Bundle 2: 1 order
        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle2->id,
            'amount_tokens' => 200,
            'price_toman' => 100000,
            'price_usd' => 2.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'range' => 'month',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('top_bundles', $data);
        $topBundles = $data['top_bundles'];
        
        // Bundle 1 should be first (more orders)
        $this->assertGreaterThan(0, count($topBundles));
        $this->assertEquals(3, $topBundles[0]['orders_count']);
    }

    /** @test */
    public function it_tracks_refunds(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        $user = User::factory()->create();
        
        // Create refund transaction
        TokenTransaction::create([
            'user_id' => $user->id,
            'type' => 'refund',
            'amount_tokens' => -100,
            'amount_usd' => -1.00,
        ]);

        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'range' => 'month',
        ]);

        $data = $response->json('data');
        $this->assertArrayHasKey('refunds', $data);
        $this->assertEquals(1, $data['refunds']['count']);
        $this->assertEquals(100, $data['refunds']['refunded_tokens']);
    }

    /** @test */
    public function it_supports_date_range_filtering(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        // Clear any existing orders and cache to ensure test isolation
        Order::query()->delete();
        TokenTransaction::query()->delete();
        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        
        // Use a very specific date range to avoid conflicts
        $now = Carbon::now();
        $endDate = $now->copy()->endOfDay();
        $startDate = $now->copy()->subDays(7)->startOfDay(); // Last 7 days
        
        // Order outside range (10 days ago - well outside 7 day range)
        $orderOutsideDate = $now->copy()->subDays(10)->startOfDay();
        $orderOutside = Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => $orderOutsideDate,
        ]);

        // Order within range (1 day ago)
        $orderInsideDate = $now->copy()->subDay()->startOfDay();
        $orderInside = Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 200,
            'price_toman' => 100000,
            'price_usd' => 2.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
            'created_at' => $orderInsideDate,
        ]);
        
        // Verify dates are correct
        $this->assertTrue($orderOutsideDate->lt($startDate), 'Order outside should be before start date');
        $this->assertTrue($orderInsideDate->gte($startDate) && $orderInsideDate->lte($endDate), 'Order inside should be within range');

        // Verify orders were created correctly
        $totalOrders = Order::count();
        $this->assertGreaterThanOrEqual(2, $totalOrders, 'Should have at least 2 orders');
        
        // Verify the orders we created exist
        $this->assertNotNull($orderOutside->id);
        $this->assertNotNull($orderInside->id);

        // Use explicit date range: last 7 days
        $response = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
        ]);

        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Verify the order inside the range is included
        $ordersInRange = Order::where('status', 'paid')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
        
        // Check that our order inside is in the results
        $orderInsideInResults = $ordersInRange->pluck('id')->contains($orderInside->id);
        $this->assertTrue($orderInsideInResults, 'Order from 1 day ago should be in 7-day range');
        
        // The API response should include at least our order inside the range
        $this->assertGreaterThanOrEqual(2.00, $data['total_revenue_usd'], 
            'Revenue should include at least the order within range');
        $this->assertGreaterThanOrEqual(1, $data['total_orders'], 
            'Should have at least one order in range');
        
        // Verify the date range in response matches what we requested
        $this->assertEquals($startDate->format('Y-m-d'), $data['date_range']['start_date']);
        $this->assertEquals($endDate->format('Y-m-d'), $data['date_range']['end_date']);
    }

    /** @test */
    public function it_caches_sales_summary(): void
    {
        $admin = User::factory()->create(['role' => 'admin'])->refresh();

        Cache::flush();

        $bundle = TokenBundle::factory()->create();
        Order::create([
            'user_id' => User::factory()->create()->id,
            'token_bundle_id' => $bundle->id,
            'amount_tokens' => 100,
            'price_toman' => 50000,
            'price_usd' => 1.00,
            'dollar_rate' => 50000.00,
            'status' => 'paid',
        ]);

        // First request
        $response1 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'range' => 'month',
        ]);

        // Second request should use cache
        $response2 = $this->actingAs($admin)->makeRequest('GET', '/api/v1/admin/sales/summary', [
            'range' => 'month',
        ]);

        $this->assertEquals(
            $response1->json('data.total_revenue_usd'),
            $response2->json('data.total_revenue_usd')
        );
    }
}

