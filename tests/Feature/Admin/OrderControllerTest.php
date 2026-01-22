<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\User;
use App\Models\Role;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['role_name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($user, abilities: ['*']);
        return $user;
    }

    public function test_index_returns_paginated_orders_with_meta(): void
    {
        $admin = $this->actingAsAdmin();
        // Create minimal orders for pagination
        for ($i = 0; $i < 3; $i++) {
            Order::create([
                'order_number' => Order::generateOrderNumber(),
                'created_by' => $admin->id,
                'cust_name' => 'C'.$i,
                'cust_phone' => '12345',
                'cust_address' => 'Addr',
                'order_date' => now()->toDateString(),
                'order_deadline' => now()->addDays(7)->toDateString(),
                'product_name' => 'P',
                'product_quantity' => 1,
                'product_price' => 1000,
            ]);
        }

        $response = $this->getJson('/api/admin/orders?limit=2');

        $response->assertStatus(200)
            ->assertJson(fn ($json) => $json
                ->where('success', true)
                ->where('message', 'List of orders')
                ->has('data')
                ->has('meta', fn ($meta) => $meta
                    ->hasAll(['current_page', 'last_page', 'total', 'per_page'])
                )
            );
    }

    public function test_show_returns_404_when_order_not_found(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/orders/999');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Order not found',
            ]);
    }

    public function test_store_creates_order_with_optional_file_and_assignments(): void
    {
        $user = $this->actingAsAdmin();
        Storage::fake('public');

        $payload = [
            'cust_name' => 'John Doe',
            'cust_phone' => '123456789',
            'cust_address' => '123 Street',
            'order_date' => now()->toDateString(),
            'order_deadline' => now()->addDays(7)->toDateString(),
            'product_name' => 'Product A',
            'product_quantity' => 2,
            'product_price' => 1000,
            'order_notes' => 'Note',
            'invoice_url' => 'http://invoice.test/abc',
            // assign none to let controller create unassigned design/production
        ];

        $response = $this->postJson('/api/admin/orders', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order created successfully',
            ]);

        $this->assertDatabaseCount('orders', 1);
        $order = Order::first();
        $this->assertNotNull($order->order_number);
        $this->assertEquals($user->id, $order->created_by);
        $this->assertDatabaseHas('designs', ['order_id' => $order->id]);
        $this->assertDatabaseHas('productions', ['order_id' => $order->id]);
        $this->assertDatabaseHas('status_history', [
            'order_id' => $order->id,
            'status_stage' => 'pending',
        ]);
    }

    public function test_update_replaces_file_and_updates_relations_when_provided(): void
    {
        $admin = $this->actingAsAdmin();
        Storage::fake('public');

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'created_by' => $admin->id,
            'cust_name' => 'Old',
            'cust_phone' => '12345',
            'cust_address' => 'Addr',
            'order_date' => now()->toDateString(),
            'order_deadline' => now()->addDays(1)->toDateString(),
            'product_name' => 'P',
            'product_quantity' => 1,
            'product_price' => 1000,
            'order_file' => 'orders/old.pdf',
        ]);
        // Put old file to simulate existing file
        Storage::disk('public')->put('orders/old.pdf', 'old');

        $designerRole = Role::query()->firstOrCreate(['role_name' => 'designer_pic']);
        $productionRole = Role::query()->firstOrCreate(['role_name' => 'production_pic']);
        $designUser = User::factory()->create(['role_id' => $designerRole->id]);
        $prodUser = User::factory()->create(['role_id' => $productionRole->id]);

        // Use non-image file to avoid GD dependency in tests
        $file = UploadedFile::fake()->create('new.pdf', 5, 'application/pdf');

        $payload = [
            'cust_name' => 'Jane',
            'order_file' => $file,
            'assigned_to_design' => $designUser->id,
            'assigned_to_production' => $prodUser->id,
        ];

        $response = $this->putJson("/api/admin/orders/{$order->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Order updated successfully',
            ]);

        $order->refresh();
        $this->assertEquals('Jane', $order->cust_name);
        // old file removed
        $this->assertFalse(Storage::disk('public')->exists('orders/old.pdf'));
        // new file stored (controller may compress to jpg path); ensure any file path present exists
        if ($order->order_file) {
            $this->assertTrue(Storage::disk('public')->exists($order->order_file));
        }
        $this->assertDatabaseHas('designs', [
            'order_id' => $order->id,
            'assigned_to' => $designUser->id,
        ]);
        $this->assertDatabaseHas('productions', [
            'order_id' => $order->id,
            'assigned_to' => $prodUser->id,
        ]);
    }

    public function test_destroy_prevents_deletion_when_confirmed(): void
    {
        $admin = $this->actingAsAdmin();

        $order = Order::create([
            'order_number' => Order::generateOrderNumber(),
            'created_by' => $admin->id,
            'cust_name' => 'C',
            'cust_phone' => '12345',
            'cust_address' => 'Addr',
            'order_date' => now()->toDateString(),
            'order_deadline' => now()->addDays(1)->toDateString(),
            'product_name' => 'P',
            'product_quantity' => 1,
            'product_price' => 1000,
        ]);
        StatusHistory::create([
            'order_id' => $order->id,
            'status_stage' => 'confirmed',
            'updated_by' => $admin->id,
            'start_time' => now(),
        ]);

        $response = $this->deleteJson("/api/admin/orders/{$order->id}");
        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot delete confirmed design!',
            ]);

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_completed_returns_404_when_none_completed(): void
    {
        $admin = $this->actingAsAdmin();

        for ($i = 0; $i < 2; $i++) {
            Order::create([
                'order_number' => Order::generateOrderNumber(),
                'created_by' => $admin->id,
                'cust_name' => 'C'.$i,
                'cust_phone' => '12345',
                'cust_address' => 'Addr',
                'order_date' => now()->toDateString(),
                'order_deadline' => now()->addDays(7)->toDateString(),
                'product_name' => 'P',
                'product_quantity' => 1,
                'product_price' => 1000,
            ]);
        }

        $response = $this->getJson('/api/admin/completed');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'No orders completed yet!',
            ]);
    }
}
