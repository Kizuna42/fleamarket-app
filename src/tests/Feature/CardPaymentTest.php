<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\User;
use App\Services\StripeCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Stripe\Checkout\Session;
use Tests\TestCase;

/**
 * 支払い方法ごとの購入フロー（Stripe への実通信は StripeCheckout をモックして行わない）
 */
class CardPaymentTest extends TestCase
{
    use RefreshDatabase;

    private $buyer;
    private $seller;
    private $item;

    public function setUp(): void
    {
        parent::setUp();

        $this->buyer = User::factory()->create([
            'postal_code' => '1234567',
            'address' => '東京都渋谷区1-2-3',
        ]);
        $this->seller = User::factory()->create();
        $this->item = Item::factory()->create([
            'user_id' => $this->seller->id,
            'is_sold' => false,
            'price' => 3000,
        ]);
    }

    private function paidSession(array $overrides = []): Session
    {
        return Session::constructFrom(array_merge([
            'id' => 'cs_test_123',
            'payment_status' => 'paid',
            'metadata' => [
                'item_id' => (string) $this->item->id,
                'user_id' => (string) $this->buyer->id,
            ],
        ], $overrides));
    }

    /** @test */
    public function 購入画面の支払い方法の選択肢はサーバー側の定数と一致する()
    {
        $response = $this->actingAs($this->buyer)->get(route('purchases.show', $this->item));

        $response->assertStatus(200);
        foreach (Purchase::PAYMENT_METHODS as $value => $label) {
            $response->assertSee('value="' . $value . '"', false);
            $response->assertSee($label);
        }
        $response->assertSee(json_encode(route('purchases.create-session', $this->item)), false);
    }

    /** @test */
    public function カード払いはStripeCheckoutのセッションを作成してURLを返す()
    {
        $this->mock(StripeCheckout::class, function ($mock) {
            $mock->shouldReceive('createSession')
                ->once()
                ->with(Mockery::on(function (array $params) {
                    return $params['mode'] === 'payment'
                        && $params['line_items'][0]['price_data']['currency'] === 'jpy'
                        && $params['line_items'][0]['price_data']['unit_amount'] === 3000
                        && $params['metadata']['item_id'] === (string) $this->item->id
                        && $params['metadata']['user_id'] === (string) $this->buyer->id
                        && $params['success_url'] === route('purchases.success', $this->item) . '?session_id={CHECKOUT_SESSION_ID}'
                        && $params['cancel_url'] === route('purchases.show', $this->item);
                }))
                ->andReturn(Session::constructFrom([
                    'id' => 'cs_test_123',
                    'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
                ]));
        });

        $response = $this->actingAs($this->buyer)
            ->postJson(route('purchases.create-session', $this->item));

        $response->assertStatus(200)
            ->assertJson([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            ]);

        // セッション作成の時点では購入は確定しない
        $this->assertDatabaseMissing('purchases', ['item_id' => $this->item->id]);
    }

    /** @test */
    public function 自分の商品や売り切れの商品ではセッションを作成しない()
    {
        $this->mock(StripeCheckout::class, function ($mock) {
            $mock->shouldNotReceive('createSession');
        });

        $this->actingAs($this->seller)
            ->postJson(route('purchases.create-session', $this->item))
            ->assertStatus(403);

        $this->item->update(['is_sold' => true]);

        $this->actingAs($this->buyer)
            ->postJson(route('purchases.create-session', $this->item))
            ->assertStatus(403);
    }

    /** @test */
    public function Stripeでエラーが起きた場合は500を返す()
    {
        $this->mock(StripeCheckout::class, function ($mock) {
            $mock->shouldReceive('createSession')->once()->andThrow(new \RuntimeException('network error'));
        });

        $this->actingAs($this->buyer)
            ->postJson(route('purchases.create-session', $this->item))
            ->assertStatus(500)
            ->assertJson(['error' => '決済処理の準備中にエラーが発生しました。']);
    }

    /** @test */
    public function 未認証ユーザーはセッションを作成できない()
    {
        $this->post(route('purchases.create-session', $this->item))
            ->assertRedirect('/login');
    }

    /** @test */
    public function 決済完了後に戻ると購入がカード払いで確定する()
    {
        $this->mock(StripeCheckout::class, function ($mock) {
            $mock->shouldReceive('retrieveSession')->once()->with('cs_test_123')->andReturn($this->paidSession());
        });

        $response = $this->actingAs($this->buyer)
            ->get(route('purchases.success', $this->item) . '?session_id=cs_test_123');

        $response->assertRedirect(route('items.index'));
        $this->assertDatabaseHas('purchases', [
            'user_id' => $this->buyer->id,
            'item_id' => $this->item->id,
            'price' => 3000,
            'payment_method' => Purchase::PAYMENT_CREDIT_CARD,
            'postal_code' => '1234567',
        ]);
        $this->assertDatabaseHas('items', ['id' => $this->item->id, 'is_sold' => true]);

        // 再読み込みしても二重に登録されない
        $this->actingAs($this->buyer)
            ->get(route('purchases.success', $this->item) . '?session_id=cs_test_123')
            ->assertRedirect(route('items.index'));
        $this->assertSame(1, Purchase::where('item_id', $this->item->id)->count());
    }

    /** @test */
    public function 未払いのセッションでは購入が確定しない()
    {
        $this->mock(StripeCheckout::class, function ($mock) {
            $mock->shouldReceive('retrieveSession')->once()->andReturn($this->paidSession(['payment_status' => 'unpaid']));
        });

        $this->actingAs($this->buyer)
            ->get(route('purchases.success', $this->item) . '?session_id=cs_test_123')
            ->assertRedirect(route('purchases.show', $this->item));

        $this->assertDatabaseMissing('purchases', ['item_id' => $this->item->id]);
        $this->assertDatabaseHas('items', ['id' => $this->item->id, 'is_sold' => false]);
    }

    /** @test */
    public function 他人の決済セッションでは購入が確定しない()
    {
        $other = User::factory()->create();
        $this->mock(StripeCheckout::class, function ($mock) use ($other) {
            $mock->shouldReceive('retrieveSession')->once()->andReturn($this->paidSession([
                'metadata' => ['item_id' => (string) $this->item->id, 'user_id' => (string) $other->id],
            ]));
        });

        $this->actingAs($this->buyer)
            ->get(route('purchases.success', $this->item) . '?session_id=cs_test_123')
            ->assertRedirect(route('purchases.show', $this->item));

        $this->assertDatabaseMissing('purchases', ['item_id' => $this->item->id]);
    }

    /** @test */
    public function 別の商品の決済セッションでは購入が確定しない()
    {
        $otherItem = Item::factory()->create(['user_id' => $this->seller->id]);
        $this->mock(StripeCheckout::class, function ($mock) use ($otherItem) {
            $mock->shouldReceive('retrieveSession')->once()->andReturn($this->paidSession([
                'metadata' => ['item_id' => (string) $otherItem->id, 'user_id' => (string) $this->buyer->id],
            ]));
        });

        $this->actingAs($this->buyer)
            ->get(route('purchases.success', $this->item) . '?session_id=cs_test_123')
            ->assertRedirect(route('purchases.show', $this->item));

        $this->assertDatabaseMissing('purchases', ['item_id' => $this->item->id]);
    }

    /** @test */
    public function カード払いを決済なしで購入確定することはできない()
    {
        $this->mock(StripeCheckout::class, function ($mock) {
            $mock->shouldNotReceive('createSession');
            $mock->shouldNotReceive('retrieveSession');
        });

        $this->actingAs($this->buyer)
            ->from(route('purchases.show', $this->item))
            ->post(route('purchases.store', $this->item), [
                'payment_method' => Purchase::PAYMENT_CREDIT_CARD,
            ])
            ->assertRedirect(route('purchases.show', $this->item))
            ->assertSessionHasErrors('payment_method');

        $this->assertDatabaseMissing('purchases', ['item_id' => $this->item->id]);
        $this->assertDatabaseHas('items', ['id' => $this->item->id, 'is_sold' => false]);
    }

    /** @test */
    public function コンビニ払いは購入ボタンで購入が確定する()
    {
        $this->actingAs($this->buyer)
            ->post(route('purchases.store', $this->item), [
                'payment_method' => Purchase::PAYMENT_CONVENIENCE_STORE,
            ])
            ->assertRedirect(route('purchases.success', $this->item));

        $this->assertDatabaseHas('purchases', [
            'user_id' => $this->buyer->id,
            'item_id' => $this->item->id,
            'payment_method' => Purchase::PAYMENT_CONVENIENCE_STORE,
        ]);
    }

    /** @test */
    public function 支払い方法の更新はサーバー側の定数の値を受け付ける()
    {
        foreach (array_keys(Purchase::PAYMENT_METHODS) as $method) {
            $this->actingAs($this->buyer)
                ->post(route('purchases.update-payment', $this->item), ['payment_method' => $method])
                ->assertStatus(200)
                ->assertJson(['payment_method' => $method]);
        }

        // 旧フロントの値は受け付けない
        $this->actingAs($this->buyer)
            ->post(route('purchases.update-payment', $this->item), ['payment_method' => 'credit'])
            ->assertStatus(422);
    }
}
