<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    /**
     * 商品一覧を表示
     */
    public function index(Request $request)
    {
        $tab = $request->input('tab', 'recommend');
        $query = Item::with(['user', 'categories']);
        $search = $request->input('search');
        $category = $request->input('category');

        if ($tab === 'mylist') {
            if (!Auth::check()) {
                return view('items.index', ['items' => collect(), 'tab' => $tab]);
            }

            $query = Auth::user()->likedItems()
                ->with(['user', 'categories']);

            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            // 自分の出品した商品を除外（テーブル名を明示的に指定）
            $query->where('items.user_id', '!=', Auth::id());

            if ($category) {
                $query->whereHas('categories', function ($q) use ($category) {
                    $q->where('categories.id', $category);
                });
            }

            $items = $query->latest()
                ->paginate(12);
        } else {
            // 自分の出品以外の商品を表示
            if (Auth::check()) {
                $query->where('user_id', '!=', Auth::id());
            }

            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            if ($category) {
                $query->whereHas('categories', function ($q) use ($category) {
                    $q->where('categories.id', $category);
                });
            }

            $items = $query->latest()->paginate(12);
        }

        return view('items.index', compact('items', 'tab', 'search'));
    }

    /**
     * 商品詳細を表示
     */
    public function show(Item $item)
    {
        $item->load(['user', 'categories', 'comments.user']);

        $likesCount = $item->likes()->count();
        $commentsCount = $item->comments()->count();

        return view('items.show', [
            'item' => $item,
            'likesCount' => $likesCount,
            'commentsCount' => $commentsCount,
        ]);
    }

    /**
     * 商品出品フォームを表示
     */
    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('items.create', compact('categories'));
    }

    /**
     * 商品を出品
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'description' => 'required|string',
            'price' => 'required|integer|min:1',
            'condition' => 'required|in:good,fair,poor',
            'categories' => 'required|array|min:1',
            'categories.*' => 'exists:categories,id',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ], [
            'name.required' => '商品名を入力してください',
            'brand_name.max' => 'ブランド名は255文字以内で入力してください',
            'description.required' => '商品の説明を入力してください',
            'price.required' => '価格を入力してください',
            'price.integer' => '価格は整数で入力してください',
            'price.min' => '価格は1円以上で入力してください',
            'condition.required' => '商品の状態を選択してください',
            'categories.required' => 'カテゴリーを選択してください',
            'categories.min' => '1つ以上のカテゴリーを選択してください',
            'image.required' => '商品画像を選択してください',
            'image.image' => '商品画像は画像ファイルを選択してください',
            'image.mimes' => '商品画像はjpeg,png,jpg,gif形式のファイルを選択してください',
            'image.max' => '商品画像は2MB以下のファイルを選択してください',
        ]);

        try {
            \DB::beginTransaction();

            // 画像のアップロード
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $filename = time() . '_' . $image->getClientOriginalName();

                // 画像をstorage/app/public/itemsに保存
                $path = Storage::disk('public')->putFileAs('items', $image, $filename);
                if (!$path) {
                    throw new \Exception('画像の保存に失敗しました');
                }

                // 画像のURLパスを設定
                $imageUrl = '/storage/' . $path;
            } else {
                throw new \Exception('画像ファイルのアップロードに失敗しました。');
            }

            // 商品の保存
            $item = new Item([
                'user_id' => auth()->id(),
                'name' => $validated['name'],
                'brand_name' => $validated['brand_name'],
                'description' => $validated['description'],
                'price' => $validated['price'],
                'condition' => $validated['condition'],
                'image' => $imageUrl,
                'is_sold' => false,
            ]);

            Auth::user()->items()->save($item);

            // カテゴリーの処理
            $item->categories()->sync($validated['categories']);

            \DB::commit();

            return redirect()->route('items.show', $item)
                ->with('success', '商品を出品しました');

        } catch (\Exception $e) {
            \DB::rollBack();
            \Log::error('商品出品エラー: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            // アップロードした画像を削除
            if (isset($path)) {
                Storage::disk('public')->delete($path);
            }

            return back()
                ->withInput()
                ->with('error', '商品の出品に失敗しました。もう一度お試しください。');
        }
    }
}
