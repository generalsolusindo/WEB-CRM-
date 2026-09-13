<?php

namespace Tests\Feature\Warehouse;

use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseItemTest extends TestCase
{
    use RefreshDatabase;

    private function warehouseUser(): User
    {
        return User::factory()->create(['role' => 'warehouse', 'is_active' => true]);
    }

    public function test_non_warehouse_role_cannot_manage_items(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $item = WarehouseItem::create(['name' => 'Kabel LAN', 'unit' => 'meter', 'qty_on_hand' => 10]);

        $this->actingAs($sales)->get('/warehouse/items')->assertForbidden();
        $this->actingAs($sales)->post('/warehouse/items', ['name' => 'X', 'unit' => 'unit', 'qty_on_hand' => 1])->assertForbidden();
        $this->actingAs($sales)->put("/warehouse/items/{$item->id}", ['name' => 'Y', 'unit' => 'unit'])->assertForbidden();
        $this->actingAs($sales)->delete("/warehouse/items/{$item->id}")->assertForbidden();
    }

    public function test_procurement_can_view_but_not_manage(): void
    {
        $procurement = User::factory()->create(['role' => 'procurement', 'is_active' => true]);
        WarehouseItem::create(['name' => 'CCTV Dome', 'unit' => 'unit', 'qty_on_hand' => 5]);

        $this->actingAs($procurement)->get('/warehouse/items')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManage', false)
                ->where('items.data.0.name', 'CCTV Dome'));

        $this->actingAs($procurement)->get('/warehouse/items/create')->assertForbidden();
    }

    public function test_warehouse_creates_edits_and_deletes_item(): void
    {
        $warehouse = $this->warehouseUser();

        $this->actingAs($warehouse)->post('/warehouse/items', [
            'name' => 'Kabel LAN Cat6', 'category' => 'Networking', 'unit' => 'meter', 'qty_on_hand' => 100,
        ])->assertRedirect('/warehouse/items');

        $item = WarehouseItem::where('name', 'Kabel LAN Cat6')->firstOrFail();
        $this->assertSame(100, $item->qty_on_hand);
        $this->assertSame($warehouse->id, $item->created_by);

        $this->actingAs($warehouse)->put("/warehouse/items/{$item->id}", [
            'name' => 'Kabel LAN Cat6 (updated)', 'unit' => 'meter',
        ])->assertRedirect('/warehouse/items');
        $this->assertSame('Kabel LAN Cat6 (updated)', $item->fresh()->name);
        // Qty tidak berubah lewat form edit biasa.
        $this->assertSame(100, $item->fresh()->qty_on_hand);

        $this->actingAs($warehouse)->delete("/warehouse/items/{$item->id}")->assertRedirect('/warehouse/items');
        $this->assertModelMissing($item);
    }

    public function test_warehouse_adjusts_stock_in_and_out(): void
    {
        $warehouse = $this->warehouseUser();
        $item = WarehouseItem::create(['name' => 'CCTV Dome', 'unit' => 'unit', 'qty_on_hand' => 10]);

        $this->actingAs($warehouse)->post("/warehouse/items/{$item->id}/adjust", [
            'direction' => 'in', 'qty' => 5,
        ])->assertRedirect();
        $this->assertSame(15, $item->fresh()->qty_on_hand);

        $this->actingAs($warehouse)->post("/warehouse/items/{$item->id}/adjust", [
            'direction' => 'out', 'qty' => 8,
        ])->assertRedirect();
        $this->assertSame(7, $item->fresh()->qty_on_hand);
    }

    public function test_cannot_reduce_stock_below_zero(): void
    {
        $warehouse = $this->warehouseUser();
        $item = WarehouseItem::create(['name' => 'CCTV Dome', 'unit' => 'unit', 'qty_on_hand' => 3]);

        $this->actingAs($warehouse)->post("/warehouse/items/{$item->id}/adjust", [
            'direction' => 'out', 'qty' => 5,
        ])->assertSessionHasErrors('qty');

        $this->assertSame(3, $item->fresh()->qty_on_hand);
    }

    public function test_search_filters_by_name_or_category(): void
    {
        $warehouse = $this->warehouseUser();
        WarehouseItem::create(['name' => 'CCTV Dome', 'category' => 'Security', 'unit' => 'unit', 'qty_on_hand' => 5]);
        WarehouseItem::create(['name' => 'Kabel LAN', 'category' => 'Networking', 'unit' => 'meter', 'qty_on_hand' => 20]);

        $this->actingAs($warehouse)->get('/warehouse/items?search=CCTV')
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.name', 'CCTV Dome'));

        $this->actingAs($warehouse)->get('/warehouse/items?search=Networking')
            ->assertInertia(fn ($page) => $page
                ->has('items.data', 1)
                ->where('items.data.0.name', 'Kabel LAN'));
    }
}
