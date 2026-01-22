<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Models\Design;
use App\Models\DesignItem;
use App\Models\StatusHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DesignControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $role = Role::query()->firstOrCreate(['role_name' => 'admin']);
        $user = User::factory()->create(['role_id' => $role->id]);
        Sanctum::actingAs($user, abilities: ['*']);
        return $user;
    }

    private function createOrderWithDesign(User $creator, ?User $assignee = null, array $orderOverrides = []): array
    {
        $order = Order::create(array_merge([
            'order_number' => Order::generateOrderNumber(),
            'created_by' => $creator->id,
            'cust_name' => 'Cust',
            'cust_phone' => '12345',
            'cust_address' => 'Addr',
            'order_date' => now()->toDateString(),
            'order_deadline' => now()->addDays(7)->toDateString(),
            'product_name' => 'Prod',
            'product_quantity' => 1,
            'product_price' => 1000,
            'order_file' => 'orders/sample.pdf',
        ], $orderOverrides));

        $design = Design::create([
            'order_id' => $order->id,
            'assigned_to' => $assignee?->id,
        ]);

        return [$order, $design];
    }

    public function test_index_returns_designs_in_designing_with_transformed_payload(): void
    {
        $admin = $this->actingAsAdmin();

        // Create 2 designs, one with approved item, one with latest in_progress item
        [$order1, $design1] = $this->createOrderWithDesign($admin);
        [$order2, $design2] = $this->createOrderWithDesign($admin);

        // Put orders into designing status
        StatusHistory::create([
            'order_id' => $order1->id,
            'status_stage' => 'designing',
            'updated_by' => $admin->id,
            'start_time' => now(),
        ]);
        StatusHistory::create([
            'order_id' => $order2->id,
            'status_stage' => 'designing',
            'updated_by' => $admin->id,
            'start_time' => now(),
        ]);

        // Design1: has one approved item -> approval_status true, image_cover from approved item
        $approvedItem = DesignItem::create([
            'design_id' => $design1->id,
            'design_file' => 'designs/approved.jpg',
            'design_status' => 'approved',
        ]);

        // Design2: latest item is in_progress -> approval_status false, image_cover from latest item
        DB::table('design_items')->insert([
            'design_id' => $design2->id,
            'design_file' => 'designs/older.png',
            'design_status' => 'revision',
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);
        // Ensure deterministic ordering: latest item has newer timestamp
        DB::table('design_items')->insert([
            'design_id' => $design2->id,
            'design_file' => 'designs/latest.png',
            'design_status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/admin/designs');

        $response->assertStatus(200)
            ->assertJson(fn ($json) => $json
                ->where('success', true)
                ->where('message', 'List of designs')
                ->has('data', 2)
                ->has('meta', fn ($meta) => $meta->hasAll(['current_page','last_page','total','per_page']))
            );

        $payload = $response->json('data');
        // Find transformed entries by order_id
        $t1 = collect($payload)->firstWhere('order_id', $order1->id);
        $t2 = collect($payload)->firstWhere('order_id', $order2->id);

        $this->assertNotNull($t1);
        $this->assertTrue($t1['approval_status']);
        $this->assertEquals('designs/approved.jpg', $t1['image_cover']);

        $this->assertNotNull($t2);
        $this->assertFalse($t2['approval_status']);
        $this->assertEquals('designs/latest.png', $t2['image_cover']);
    }

    public function test_show_returns_404_when_design_not_found(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/designs/999');
        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Design not found',
            ]);
    }

    public function test_update_item_status_prevents_multiple_approved_items(): void
    {
        $admin = $this->actingAsAdmin();
        [$order, $design] = $this->createOrderWithDesign($admin);

        // Prepare items: one already approved, one to be approved -> should 400
        $existingApproved = DesignItem::create([
            'design_id' => $design->id,
            'design_file' => 'designs/approved1.jpg',
            'design_status' => 'approved',
        ]);
        $targetItem = DesignItem::create([
            'design_id' => $design->id,
            'design_file' => 'designs/target.jpg',
            'design_status' => 'revision',
        ]);

        $response = $this->putJson("/api/admin/design-items/{$targetItem->id}", [
            'design_status' => 'approved',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Only one design item can be approved',
            ]);
    }

    public function test_update_item_status_updates_revision_successfully(): void
    {
        $admin = $this->actingAsAdmin();
        [$order, $design] = $this->createOrderWithDesign($admin);
        $item = DesignItem::create([
            'design_id' => $design->id,
            'design_file' => 'designs/file.jpg',
            'design_status' => 'in_progress',
        ]);

        $response = $this->putJson("/api/admin/design-items/{$item->id}", [
            'design_status' => 'revision',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Design item updated',
            ]);

        $item->refresh();
        $this->assertEquals('revision', $item->design_status);
    }

    public function test_confirm_design_fails_when_no_approved_item(): void
    {
        $admin = $this->actingAsAdmin();
        [$order, $design] = $this->createOrderWithDesign($admin);

        // Put order in designing
        StatusHistory::create([
            'order_id' => $order->id,
            'status_stage' => 'designing',
            'updated_by' => $admin->id,
            'start_time' => now(),
        ]);

        // No approved items
        DesignItem::create([
            'design_id' => $design->id,
            'design_file' => 'designs/item.jpg',
            'design_status' => 'revision',
        ]);

        $response = $this->postJson("/api/admin/designs/{$design->id}/confirm");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot confirm design - no approved design item found',
            ]);
    }

    public function test_confirm_design_prevents_duplicate_confirmation(): void
    {
        $admin = $this->actingAsAdmin();
        [$order, $design] = $this->createOrderWithDesign($admin);

        // designing -> confirmed already
        StatusHistory::create([
            'order_id' => $order->id,
            'status_stage' => 'designing',
            'updated_by' => $admin->id,
            'start_time' => now(),
        ]);
        StatusHistory::create([
            'order_id' => $order->id,
            'status_stage' => 'confirmed',
            'updated_by' => $admin->id,
            'start_time' => now(),
        ]);

        // at least one approved item exists
        DesignItem::create([
            'design_id' => $design->id,
            'design_file' => 'designs/item.jpg',
            'design_status' => 'approved',
        ]);

        $response = $this->postJson("/api/admin/designs/{$design->id}/confirm");

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Design confirmation has been done!',
            ]);
    }

    public function test_confirm_design_closes_designing_and_creates_confirmed_status(): void
    {
        $admin = $this->actingAsAdmin();
        [$order, $design] = $this->createOrderWithDesign($admin);

        // designing status open
        StatusHistory::create([
            'order_id' => $order->id,
            'status_stage' => 'designing',
            'updated_by' => $admin->id,
            'start_time' => now(),
            'end_time' => null,
        ]);

        // approved item present
        DesignItem::create([
            'design_id' => $design->id,
            'design_file' => 'designs/item.jpg',
            'design_status' => 'approved',
        ]);

        $response = $this->postJson("/api/admin/designs/{$design->id}/confirm");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Design confirmed successfully',
            ]);

        // designing should be closed (end_time not null)
        $this->assertDatabaseHas('status_history', [
            'order_id' => $order->id,
            'status_stage' => 'designing',
        ]);
        $this->assertNotNull(StatusHistory::where('order_id', $order->id)
            ->where('status_stage', 'designing')
            ->latest('id')
            ->first()
            ->end_time);

        // confirmed should be created
        $this->assertDatabaseHas('status_history', [
            'order_id' => $order->id,
            'status_stage' => 'confirmed',
        ]);
    }
}
