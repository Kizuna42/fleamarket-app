@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>出品した商品</h1>
        <a href="{{ route('items.create') }}" class="btn btn-primary">新規出品</a>
    </div>

    <div class="row">
        @foreach($items as $item)
            <div class="col-md-4 mb-4">
                <div class="card h-100">
                    @if($item->image)
                        <img src="{{ asset('storage/' . $item->image) }}" class="card-img-top" alt="{{ $item->name }}" style="height: 200px; object-fit: cover;">
                    @endif
                    <div class="card-body">
                        <h5 class="card-title">{{ $item->name }}</h5>
                        <p class="card-text">¥{{ number_format($item->price) }}</p>
                        <p class="card-text">
                            <small class="text-muted">出品日: {{ $item->created_at->format('Y年m月d日') }}</small>
                        </p>
                        @if($item->is_sold)
                            <div class="badge bg-secondary mb-2">売却済み</div>
                        @else
                            <div class="badge bg-success mb-2">出品中</div>
                        @endif
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="d-grid gap-2">
                            <a href="{{ route('items.show', $item->id) }}" class="btn btn-outline-primary">商品詳細</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-center">
        {{ $items->links() }}
    </div>
</div>
@endsection