<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private $buyer;
    private $seller;
    private $item;

    public function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create();
        $this->seller = User::factory()->create();
        $this->item = Item::factory()->create([
            'user_id' => $this->seller->id,
            'is_sold' => false,
            'price' => 1000,
        ]);
    }

    /** @test */
    public function 購入するボタンを押下すると購入が完了する()
    {
        $response = $this->actingAs($this->buyer)
            ->post("/purchase/{$this->item->id}", [
                'payment_method' => 'convenience_store',
            ]);

        $response->assertStatus(302)
            ->assertRedirect("/purchases/{$this->item->id}/success");

        $this->assertDatabaseHas('items', [
            'id' => $this->item->id,
            'is_sold' => true,
        ]);

        $this->assertDatabaseHas('purchases', [
            'user_id' => $this->buyer->id,
            'item_id' => $this->item->id,
            'payment_method' => 'convenience_store',
        ]);
    }

    /** @test */
    public function 購入した商品は商品一覧画面にてsoldと表示される()
    {
        // 商品を購入
        $this->actingAs($this->buyer)
            ->post("/purchase/{$this->item->id}", [
                'payment_method' => 'convenience_store',
            ]);

        // 商品一覧画面を表示
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('Sold');
    }

    /** @test */
    public function プロフィール購入した商品一覧に追加されている()
    {
        // 商品を購入
        $this->actingAs($this->buyer)
            ->post("/purchase/{$this->item->id}", [
                'payment_method' => 'convenience_store',
            ]);

        // プロフィール画面の購入商品一覧を表示
        $response = $this->actingAs($this->buyer)
            ->get('/mypage?tab=buy');

        $response->assertStatus(200)
            ->assertSee($this->item->name);
    }

    /** @test */
    public function 未認証ユーザーは商品を購入できない()
    {
        $response = $this->post("/purchase/{$this->item->id}", [
            'payment_method' => 'convenience_store',
        ]);

        $response->assertStatus(302)
            ->assertRedirect('/login');

        $this->assertDatabaseMissing('purchases', [
            'item_id' => $this->item->id,
        ]);
    }

    /** @test */
    public function 自分の出品した商品は購入できない()
    {
        $response = $this->actingAs($this->seller)
            ->post("/purchase/{$this->item->id}", [
                'payment_method' => 'convenience_store',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('purchases', [
            'item_id' => $this->item->id,
        ]);
    }

    /** @test */
    public function 売り切れの商品は購入できない()
    {
        // 商品を売り切れ状態に変更
        $this->item->update(['is_sold' => true]);

        $response = $this->actingAs($this->buyer)
            ->post("/purchase/{$this->item->id}", [
                'payment_method' => 'convenience_store',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('purchases', [
            'user_id' => $this->buyer->id,
            'item_id' => $this->item->id,
        ]);
    }
}
