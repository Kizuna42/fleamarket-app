<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\User;
use App\Services\StripeCheckout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * 購入確認画面を表示
     */
    public function show(Item $item)
    {
        if ($item->is_sold) {
            return redirect()->route('items.show', $item)
                ->with('error', 'この商品は既に売却済みです。');
        }

        if ($item->user_id === Auth::id()) {
            return redirect()->route('items.show', $item)
                ->with('error', '自分の商品は購入できません。');
        }

        return view('purchases.show', compact('item'));
    }

    /**
     * 支払い方法を更新する
     */
    public function updatePayment(Request $request, Item $item)
    {
        try {
            $validated = $request->validate([
                'payment_method' => ['required', Rule::in(array_keys(Purchase::PAYMENT_METHODS))],
            ], [
                'payment_method.required' => '支払い方法を選択してください',
                'payment_method.in' => '無効な支払い方法です',
            ]);

            // セッションに支払い方法を保存
            $request->session()->put('payment_method', $validated['payment_method']);

            return response()->json([
                'payment_method' => $validated['payment_method']
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => '無効な支払い方法です',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * 商品を購入（コンビニ払い）
     *
     * クレジットカード払いは createSession で Stripe Checkout を経由し、
     * 決済完了後に success で購入を確定する。
     */
    public function store(Request $request, Item $item)
    {
        $validated = $request->validate([
            'payment_method' => ['required', Rule::in([Purchase::PAYMENT_CONVENIENCE_STORE])],
        ], [
            'payment_method.required' => '支払い方法を選択してください',
            'payment_method.in' => 'クレジットカード払いは決済画面から手続きしてください',
        ]);

        $error = $this->completePurchase(auth()->user(), $item, $validated['payment_method']);

        if ($error !== null) {
            return response()->json(['message' => $error], 403);
        }

        return redirect()->route('purchases.success', ['item' => $item->id]);
    }

    /**
     * 商品行をロックした上で購入可否を判定し、購入を確定する。
     * 購入できない場合はエラーメッセージを返す。
     */
    private function completePurchase(User $user, Item $item, string $paymentMethod, ?string $stripeSessionId = null): ?string
    {
        // 同時リクエストによる二重購入を防ぐため、商品行をロックした上で判定・更新する
        return DB::transaction(function () use ($item, $user, $paymentMethod, $stripeSessionId) {
            $lockedItem = Item::whereKey($item->id)->lockForUpdate()->first();

            // 同じ Checkout セッションの戻りが同時に処理された場合は、先に確定した側を正とし冪等に成功させる
            if ($stripeSessionId !== null && Purchase::where('stripe_session_id', $stripeSessionId)->exists()) {
                return null;
            }

            if ($lockedItem->user_id === $user->id) {
                return '自分の出品した商品は購入できません。';
            }

            if ($lockedItem->is_sold) {
                return 'この商品は既に売り切れです。';
            }

            Purchase::create([
                'user_id' => $user->id,
                'item_id' => $lockedItem->id,
                'price' => $lockedItem->price,
                'payment_method' => $paymentMethod,
                'stripe_session_id' => $stripeSessionId,
                'postal_code' => $user->postal_code,
                'address' => $user->address,
                'building_name' => $user->building_name,
                'status' => 'completed'
            ]);

            // 商品を売り切れ状態に更新
            $lockedItem->update(['is_sold' => true]);

            return null;
        });
    }

    /**
     * 配送先住所編集フォームを表示
     */
    public function editAddress(Item $item)
    {
        $prefectures = ['北海道', '青森県', '岩手県', /* ... 他の都道府県 ... */];
        return view('purchases.address', compact('item', 'prefectures'));
    }

    /**
     * 配送先住所を更新
     */
    public function updateAddress(Request $request, Item $item)
    {
        $validated = $request->validate([
            'postal_code' => 'required|digits:7',
            'address' => 'required|string|max:255',
            'building_name' => 'nullable|string|max:255',
        ], [
            'postal_code.digits' => '郵便番号は7桁の数字で入力してください。',
            'postal_code.required' => '郵便番号は必須です。',
            'address.required' => '住所は必須です。',
        ]);

        $user = Auth::user();
        $user->update($validated);

        if ($request->wantsJson()) {
            return response()->json(['message' => '配送先住所を更新しました。']);
        }

        return redirect()->route('purchases.show', $item)
            ->with('success', '配送先住所を更新しました。');
    }

    /**
     * クレジットカード払い用の Stripe Checkout セッションを作成する
     */
    public function createSession(Item $item, StripeCheckout $stripe)
    {
        if ($item->user_id === Auth::id()) {
            return response()->json(['error' => '自分の出品した商品は購入できません。'], 403);
        }

        if ($item->is_sold) {
            return response()->json(['error' => 'この商品は既に売り切れです。'], 403);
        }

        try {
            $session = $stripe->createSession([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'jpy',
                        'product_data' => [
                            'name' => $item->name,
                        ],
                        'unit_amount' => $item->price,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'client_reference_id' => (string) Auth::id(),
                'metadata' => [
                    'item_id' => (string) $item->id,
                    'user_id' => (string) Auth::id(),
                ],
                // {CHECKOUT_SESSION_ID} は Stripe 側で実際のセッション ID に置き換えられる
                'success_url' => route('purchases.success', $item) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('purchases.show', $item),
            ]);

            return response()->json(['id' => $session->id, 'url' => $session->url]);
        } catch (\Exception $e) {
            Log::error('Stripe session creation failed: ' . $e->getMessage());
            return response()->json(['error' => '決済処理の準備中にエラーが発生しました。'], 500);
        }
    }

    /**
     * 購入完了
     *
     * Stripe Checkout からの戻り（session_id 付き）の場合は、決済状態を Stripe に
     * 問い合わせて確認してから購入を確定する。
     */
    public function success(Request $request, Item $item, StripeCheckout $stripe)
    {
        $sessionId = $request->query('session_id');

        if (is_string($sessionId) && $sessionId !== '') {
            $user = Auth::user();

            // このセッションで既に購入が確定していれば（再読み込み等）、そのまま完了とする
            $alreadyProcessed = Purchase::where('stripe_session_id', $sessionId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$alreadyProcessed) {
                try {
                    $session = $stripe->retrieveSession($sessionId);
                } catch (\Exception $e) {
                    Log::error('Stripe session retrieval failed: ' . $e->getMessage());
                    return redirect()->route('purchases.show', $item)
                        ->with('error', '決済結果の確認に失敗しました。');
                }

                $metadata = $session->metadata;
                $isValidSession = $session->payment_status === 'paid'
                    && $metadata !== null
                    && (string) ($metadata['item_id'] ?? '') === (string) $item->id
                    && (string) ($metadata['user_id'] ?? '') === (string) $user->id;

                if (!$isValidSession) {
                    return redirect()->route('purchases.show', $item)
                        ->with('error', '決済が完了していません。');
                }

                $error = $this->completePurchase($user, $item, Purchase::PAYMENT_CREDIT_CARD, $sessionId);

                if ($error !== null) {
                    // 決済後に他の購入者（または同じ購入者の別の決済）で先に購入が確定した場合など、
                    // このセッションの支払いで購入できないときは返金する
                    Log::error("Paid Stripe session {$sessionId} could not be completed: {$error}");

                    try {
                        $stripe->refund((string) $session->payment_intent, $sessionId);
                        $message = $error . ' お支払いは返金手続きを行いました。';
                    } catch (\Exception $e) {
                        Log::critical("Refund for Stripe session {$sessionId} failed: " . $e->getMessage());
                        $message = $error . ' 返金処理に失敗しました。お手数ですがお問い合わせください。';
                    }

                    return redirect()->route('items.show', $item)
                        ->with('error', $message);
                }
            }
        }

        return redirect()->route('items.index')
            ->with('success', '商品の購入が完了しました。');
    }
}
