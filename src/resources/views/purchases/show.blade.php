@extends('layouts.app')

@section('content')
<div class="container-fluid px-3 px-md-5 mt-4">
    <div class="row gx-3 gx-md-5">
        <!-- 左側：商品情報 -->
        <div class="col-md-8 mb-4 mb-md-0">
            <div class="mb-5 pb-4 border-bottom">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        @if($item->image)
                            <div class="image-wrapper position-relative" style="padding-top: 100%;">
                                <img src="{{ $item->image }}" class="position-absolute top-0 start-0 w-100 h-100" alt="{{ $item->name }}" style="object-fit: cover;">
                            </div>
                        @endif
                    </div>
                    <div class="col-md-7">
                        <h3 class="mb-3 product-title">{{ $item->name }}</h3>
                        <h4 class="mb-0 price">¥{{ number_format($item->price) }}</h4>
                    </div>
                </div>
            </div>

            <!-- フォーム -->
            <form action="{{ route('purchases.store', $item) }}" method="POST" id="purchase-form">
                @csrf
                <!-- 支払い方法 -->
                <div class="mb-5 pb-4 border-bottom">
                    <h4 class="mb-4 section-title">支払い方法</h4>
                    <select class="form-select form-select-lg border-0 ps-0" id="payment_method" name="payment_method" required>
                        <option value="" selected disabled>選択してください</option>
                        @foreach(\App\Models\Purchase::PAYMENT_METHODS as $value => $label)
                            <option value="{{ $value }}" @if(old('payment_method') === $value) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')
                        <div class="text-danger mt-2">{{ $message }}</div>
                    @enderror
                </div>

                <!-- 配送先 -->
                <div class="mb-5 pb-4 border-bottom">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0 section-title">配送先</h4>
                        <a href="{{ route('purchases.address.edit', $item) }}" class="text-primary text-decoration-none">変更する</a>
                    </div>
                    <div id="address-display">
                    @if(Auth::user()->postal_code)
                        <p class="mb-2 address-text">〒{{ Auth::user()->postal_code }}</p>
                        <p class="mb-0 address-text">{{ Auth::user()->address }}</p>
                        @if(Auth::user()->building_name)
                            <p class="mb-0 address-text">{{ Auth::user()->building_name }}</p>
                        @endif
                    @else
                        <p class="mb-0 text-muted">配送先住所を設定してください</p>
                    @endif
                </div>
                </div>
            </form>
        </div>

        <!-- 右側：購入確認 -->
        <div class="col-md-4">
            <div class="position-sticky" style="top: 2rem;">
                <!-- 金額・支払い方法の確認 -->
                <div class="border rounded mb-4">
                    <div class="border-bottom p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-label">商品代金</span>
                            <span class="summary-price">¥{{ number_format($item->price) }}</span>
                        </div>
                    </div>
                    <div class="p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="summary-label">支払い方法</span>
                            <span class="summary-text" id="selected-payment">選択してください</span>
                        </div>
                    </div>
                </div>

                <!-- 購入ボタン -->
                <button type="submit" form="purchase-form" class="btn btn-danger btn-lg w-100 py-3 rounded-3" id="purchase-button" disabled>
                    購入する
                </button>
            </div>
        </div>
    </div>
</div>

<style>
@media (min-width: 768px) and (max-width: 850px) {
    .product-title {
        font-size: 1.3rem;
    }
    .price {
        font-size: 1.2rem;
    }
    .section-title {
        font-size: 1.1rem;
    }
    .address-text {
        font-size: 0.9rem;
    }
    .summary-label {
        font-size: 0.9rem;
    }
    .summary-price {
        font-size: 1.2rem;
        font-weight: bold;
    }
    .summary-text {
        font-size: 0.9rem;
    }
    .form-select {
        font-size: 0.9rem;
    }
    .btn-lg {
        font-size: 1rem;
        padding: 0.5rem 1rem;
    }
}

@media (min-width: 1400px) and (max-width: 1540px) {
    .container-fluid {
        max-width: 1320px;
    }
}

.summary-label {
    font-size: 1.1rem;
    color: #666;
}

.summary-price {
    font-size: 1.5rem;
    font-weight: bold;
}

.summary-text {
    font-size: 1.1rem;
}
</style>

@push('scripts')
<script>
window.addEventListener('load', function() {
    const form = document.getElementById('purchase-form');
    const select = document.getElementById('payment_method');
    const button = document.getElementById('purchase-button');
    const paymentDisplay = document.getElementById('selected-payment');

    // 支払い方法の値はサーバー側の定数（App\Models\Purchase）と共通
    const CREDIT_CARD = @json(\App\Models\Purchase::PAYMENT_CREDIT_CARD);
    const createSessionUrl = @json(route('purchases.create-session', $item));
    const csrfToken = @json(csrf_token());

    // 住所の状態
    const hasValidAddress = {{ auth()->user()->postal_code && auth()->user()->address ? 'true' : 'false' }};

    function refreshButton() {
        button.disabled = !(hasValidAddress && select.value);
    }

    function refreshSelection() {
        if (select.value) {
            paymentDisplay.textContent = select.options[select.selectedIndex].text;
        }
        refreshButton();
    }

    select.addEventListener('change', refreshSelection);
    refreshSelection();

    // カード払いは Stripe Checkout へリダイレクト、コンビニ払いはそのままフォーム送信で購入確定
    form.addEventListener('submit', async function(event) {
        if (select.value !== CREDIT_CARD) {
            return;
        }

        event.preventDefault();
        button.disabled = true;

        try {
            const response = await fetch(createSessionUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });
            const data = await response.json();

            if (!response.ok || !data.url) {
                alert(data.error || '決済処理の準備中にエラーが発生しました。');
                refreshButton();
                return;
            }

            window.location.href = data.url;
        } catch (error) {
            alert('決済処理中にエラーが発生しました。');
            refreshButton();
        }
    });
});
</script>
@endpush
@endsection
