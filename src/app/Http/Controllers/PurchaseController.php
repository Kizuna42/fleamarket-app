<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe;
use Stripe\Checkout\Session;
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
                'payment_method' => 'required|in:credit_card,bank_transfer,convenience_store',
            ], [
                'payment_method.required' => '支払い方法を選択してください',
                'payment_method.in' => '無効な支払い方法です',
            ]);

            // セッションに支払い方法を保存
            $request->session()->put('payment_method', $validated['payment_method']);

            return response()->json([
                'payment_method' => $validated['payment_method']
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => '無効な支払い方法です',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * 商品を購入
     */
    public function store(Request $request, Item $item)
    {
        $user = auth()->user();

        // 同時リクエストによる二重購入を防ぐため、商品行をロックした上で判定・更新する
        $error = DB::transaction(function () use ($request, $item, $user) {
            $lockedItem = Item::whereKey($item->id)->lockForUpdate()->first();

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
                'payment_method' => $request->input('payment_method'),
                'postal_code' => $user->postal_code,
                'address' => $user->address,
                'building_name' => $user->building_name,
                'status' => 'completed'
            ]);

            // 商品を売り切れ状態に更新
            $lockedItem->update(['is_sold' => true]);

            return null;
        });

        if ($error !== null) {
            return response()->json(['message' => $error], 403);
        }

        return redirect()->route('purchases.success', ['item' => $item->id]);
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

    public function createSession(Item $item)
    {
        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
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
                'success_url' => route('purchases.success', $item),
                'cancel_url' => route('purchases.show', $item),
            ]);

            return response()->json(['id' => $session->id]);
        } catch (\Exception $e) {
            \Log::error('Stripe session creation failed: ' . $e->getMessage());
            return response()->json(['error' => '決済処理の準備中にエラーが発生しました。'], 500);
        }
    }

    public function success(Item $item)
    {
        return redirect()->route('items.index')
            ->with('success', '商品の購入が完了しました。');
    }
}
