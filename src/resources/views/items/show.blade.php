@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <div class="row g-4">
        <!-- 商品画像 -->
        <div class="col-12 col-md-6">
            <div class="image-wrapper position-relative" style="padding-top: 100%;">
                @if($item->image)
                    <img src="{{ $item->image }}" class="position-absolute top-0 start-0 w-100 h-100" alt="{{ $item->name }}" style="object-fit: cover;">
                @else
                    <div class="position-absolute top-0 start-0 w-100 h-100 bg-secondary text-white d-flex align-items-center justify-content-center">
                        <span>商品画像</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- 商品情報 -->
        <div class="col-12 col-md-6">
            <h1 class="product-title h2 mb-3">{{ $item->name }}</h1>
            @if($item->brand_name)
                <p class="brand-name text-muted mb-2">{{ $item->brand_name }}</p>
            @endif
            <p class="price-text h3 mb-4">¥{{ number_format($item->price) }}<span class="tax-included ms-2 fs-6">(税込)</span></p>

            <div class="d-flex justify-content-start mb-4">
                <div class="text-center me-4">
                    <button class="btn like-button d-block mx-auto" data-item-id="{{ $item->id }}" style="border: none; background: none; padding: 0;">
                        <i class="fa-star fa-lg {{ $item->isLikedBy(auth()->user()) ? 'fas text-warning' : 'far text-secondary' }}"></i>
                        <span class="likes-count d-block mt-1" style="min-width: 30px">{{ $likesCount }}</span>
                    </button>
                </div>
                <div class="text-center">
                    <div class="d-block mx-auto">
                        <i class="far fa-comment fa-lg text-secondary"></i>
                    </div>
                    <span class="d-block mt-1" style="min-width: 30px">{{ $commentsCount }}</span>
                </div>
            </div>

            @if(!$item->is_sold)
                <a href="{{ route('purchases.show', $item) }}" class="btn btn-danger w-100 mb-5">購入手続きへ</a>
            @else
                <button class="btn btn-secondary w-100 mb-5" disabled>売り切れました</button>
            @endif

            <div class="product-section mb-4">
                <h4 class="section-title mb-3">商品説明</h4>
                <p class="description">{{ $item->description }}</p>
            </div>

            <div class="product-section mb-4">
                <h4 class="section-title mb-3">商品の情報</h4>
                <div class="info-list">
                    <div class="info-item mb-3">
                        <span class="label d-block mb-2">カテゴリー</span>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach($item->categories as $category)
                                <span class="badge bg-light text-dark">
                                    @if($category->parent)
                                        {{ $category->parent->name }} > {{ $category->name }}
                                    @else
                                        {{ $category->name }}
                                    @endif
                                </span>
                            @endforeach
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="label d-block mb-2">商品の状態</span>
                        <span class="badge bg-light text-dark">{{ $item->condition_name }}</span>
                    </div>
                </div>
            </div>

            <div class="product-section">
                <h4 class="section-title mb-3">コメント (<span id="comments-count">{{ $commentsCount }}</span>)</h4>
                <div class="comments-container bg-light p-3">
                    @if($item->comments->isEmpty())
                        <p class="text-center mb-0" id="no-comments-message">こちらにコメントが入ります。</p>
                    @endif
                    <div id="comments-list">
                        @foreach($item->comments as $comment)
                            <div class="comment-item mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="me-2">
                                        @if($comment->user->avatar)
                                            <img src="{{ asset('storage/avatars/' . $comment->user->avatar) }}" alt="{{ $comment->user->name }}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                                        @else
                                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <span>{{ substr($comment->user->name, 0, 1) }}</span>
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="commenter-info">
                                            <strong>{{ $comment->user->name }}</strong>
                                            <small class="text-muted ms-2">{{ $comment->created_at->format('Y/m/d H:i') }}</small>
                                        </div>
                                        <p class="comment-text mb-0">{{ $comment->content }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @auth
                    <form method="POST" action="{{ route('items.comments.store', $item) }}" class="mt-4" id="comment-form" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="comment" class="form-label">商品へのコメント</label>
                            <textarea
                                class="form-control @error('content') is-invalid @enderror"
                                id="comment"
                                name="content"
                                rows="3"
                                required
                                maxlength="255"
                            >{{ old('content') }}</textarea>
                            <div class="text-muted small mt-1">
                                <span id="char-count">0</span>/255文字
                            </div>
                            @error('content')
                                <span class="invalid-feedback">
                                    {{ $message }}
                                </span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-danger w-100">コメントを送信する</button>
                    </form>
                @else
                    <div class="text-center mt-4">
                        <a href="{{ route('login') }}" class="btn btn-outline-danger">ログインしてコメントする</a>
                    </div>
                @endauth
            </div>
        </div>
    </div>
</div>

<style>
@media (min-width: 768px) and (max-width: 850px) {
    .container {
        max-width: 720px;
    }
    .product-title {
        font-size: 1.5rem;
    }
    .price-text {
        font-size: 1.3rem;
    }
}

@media (min-width: 1400px) and (max-width: 1540px) {
    .container {
        max-width: 1320px;
    }
}

.section-title {
    font-size: 1.2rem;
    font-weight: bold;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #dee2e6;
}

.info-list {
    display: flex;
    flex-direction: column;
}

.info-item .label {
    font-weight: 500;
    color: #666;
}
</style>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // いいねボタンの処理
    const likeButton = document.querySelector('.like-button');
    if (likeButton) {
        likeButton.addEventListener('click', async function() {
            @auth
                const itemId = this.dataset.itemId;
                const starIcon = this.querySelector('i');
                const likesCountElement = this.querySelector('.likes-count');

                try {
                    const response = await fetch(`/items/${itemId}/like`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json'
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }

                    const data = await response.json();

                    // いいねの状態を更新
                    if (data.is_liked) {
                        starIcon.classList.remove('far', 'text-secondary');
                        starIcon.classList.add('fas', 'text-warning');
                    } else {
                        starIcon.classList.remove('fas', 'text-warning');
                        starIcon.classList.add('far', 'text-secondary');
                    }

                    // いいね数を更新
                    likesCountElement.textContent = data.likes_count;

                } catch (error) {
                    console.error('Error:', error);
                    alert('いいねの処理中にエラーが発生しました。');
                }
            @else
                window.location.href = '{{ route('login') }}';
            @endauth
        });
    }

    // コメントフォームの処理
    const commentForm = document.getElementById('comment-form');
    const commentTextarea = document.getElementById('comment');
    const charCount = document.getElementById('char-count');
    const commentTotalCount = document.getElementById('comments-count');
    // アイコン下のカウント要素を確実に取得
    const commentCountDisplay = document.querySelector('.fa-comment').parentElement.nextElementSibling;

    // 文字数カウントの処理
    if (commentTextarea && charCount) {
        commentTextarea.addEventListener('input', function() {
            const count = this.value.length;
            charCount.textContent = count;

            if (count > 255) {
                this.classList.add('is-invalid');
                charCount.classList.add('text-danger');
            } else {
                this.classList.remove('is-invalid');
                charCount.classList.remove('text-danger');
            }
        });
    }

    // サーバーから返された値をHTMLに埋め込む前にエスケープする（XSS対策）
    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    if (commentForm) {
        commentForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    body: new FormData(this),
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'コメントの送信に失敗しました。');
                }

                // コメントリストを更新
                const commentsList = document.getElementById('comments-list');
                const noCommentsMessage = document.getElementById('no-comments-message');

                // 新しいコメントのHTML
                const newComment = `
                    <div class="comment-item mb-3">
                        <div class="d-flex align-items-start">
                            <div class="me-2">
                                ${data.user_avatar
                                    ? `<img src="/storage/${escapeHtml(data.user_avatar)}" alt="${escapeHtml(data.user_name)}" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">`
                                    : `<div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                        <span>${escapeHtml(data.user_name.charAt(0))}</span>
                                    </div>`
                                }
                            </div>
                            <div class="flex-grow-1">
                                <div class="commenter-info">
                                    <strong>${escapeHtml(data.user_name)}</strong>
                                    <small class="text-muted ms-2">${escapeHtml(data.created_at)}</small>
                                </div>
                                <p class="comment-text mb-0">${escapeHtml(data.content)}</p>
                            </div>
                        </div>
                    </div>
                `;

                // "コメントがありません"メッセージを非表示
                if (noCommentsMessage) {
                    noCommentsMessage.style.display = 'none';
                }

                // 新しいコメントを追加
                commentsList.insertAdjacentHTML('beforeend', newComment);

                // コメント数を更新（両方の表示箇所）
                const newCount = data.comments_count;
                if (commentTotalCount) commentTotalCount.textContent = newCount;
                if (commentCountDisplay) commentCountDisplay.textContent = newCount;

                // フォームをリセット
                this.reset();
                if (charCount) charCount.textContent = '0';

                console.log('コメント数を更新しました:', newCount); // デバッグ用

            } catch (error) {
                console.error('Error:', error);
                alert(error.message);
            }
        });
    }
});
</script>
@endpush
@endsection
