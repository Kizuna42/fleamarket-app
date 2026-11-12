<?php

namespace App\Services;

use Stripe\Checkout\Session;
use Stripe\Refund;
use Stripe\StripeClient;

/**
 * Stripe Checkout との通信を担当する薄いラッパー。
 * テストではこのクラスをモックし、Stripe への実通信を行わない。
 */
class StripeCheckout
{
    private function client(): StripeClient
    {
        return new StripeClient((string) config('services.stripe.secret'));
    }

    public function createSession(array $params): Session
    {
        return $this->client()->checkout->sessions->create($params);
    }

    public function retrieveSession(string $sessionId): Session
    {
        return $this->client()->checkout->sessions->retrieve($sessionId);
    }

    /**
     * 決済を全額返金する。同じセッションに対する再実行で二重返金しないよう冪等キーを付ける。
     */
    public function refund(string $paymentIntentId, string $sessionId): Refund
    {
        return $this->client()->refunds->create(
            ['payment_intent' => $paymentIntentId],
            ['idempotency_key' => 'refund-' . $sessionId]
        );
    }
}
