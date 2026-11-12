<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShippingAddressTest extends TestCase
{
    use RefreshDatabase;

    private $user;
    private $item;
    private $addressData;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->item = Item::factory()->create([
            'price' => 1000,
            'is_sold' => false,
        ]);

        $this->addressData = [
            'postal_code' => '1234567',
            'address' => '東京都渋谷区道玄坂1-1-1',
            'building_name' => 'テストビル101',
        ];
    }

    /** @test */
    public function 送付先住所変更画面にて登録した住所が商品購入画面に反映されている()
    {
        // 住所を登録
        $response = $this->actingAs($this->user)
            ->post("/purchase/address/{$this->item->id}", $this->addressData);

        $response->assertStatus(302)
            ->assertRedirect("/purchase/{$this->item->id}");

        // 商品購入画面で住所が反映されていることを確認
        $response = $this->actingAs($this->user)
            ->get("/purchase/{$this->item->id}");

        $response->assertStatus(200)
            ->assertSee($this->addressData['postal_code'])
            ->assertSee($this->addressData['address'])
            ->assertSee($this->addressData['building_name']);
    }

    /** @test */
    public function 購入した商品に送付先住所が紐づいて登録される()
    {
        // 住所を登録
        $this->actingAs($this->user)
            ->post("/purchase/address/{$this->item->id}", $this->addressData);

        // 商品を購入
        $response = $this->actingAs($this->user)
            ->post("/purchase/{$this->item->id}", [
                'payment_method' => 'convenience_store',
            ]);

        $response->assertStatus(302)
            ->assertRedirect("/purchases/{$this->item->id}/success");

        // 購入記録に住所が紐づいていることを確認
        $this->assertDatabaseHas('purchases', [
            'user_id' => $this->user->id,
            'item_id' => $this->item->id,
            'postal_code' => $this->addressData['postal_code'],
            'address' => $this->addressData['address'],
            'building_name' => $this->addressData['building_name'],
        ]);
    }

    /** @test */
    public function 未認証ユーザーは住所を変更できない()
    {
        $response = $this->post("/purchase/address/{$this->item->id}", $this->addressData);

        $response->assertStatus(302)
            ->assertRedirect('/login');
    }

    /** @test */
    public function 不正な住所データは登録できない()
    {
        $invalidData = [
            'postal_code' => '123-4567', // ハイフンあり
            'address' => '',
        ];

        $response = $this->actingAs($this->user)
            ->from("/purchase/address/{$this->item->id}")
            ->post("/purchase/address/{$this->item->id}", $invalidData);

        $response->assertStatus(302)
            ->assertRedirect("/purchase/address/{$this->item->id}")
            ->assertSessionHasErrors(['postal_code', 'address']);
    }
}
