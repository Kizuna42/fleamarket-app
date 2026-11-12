<?php

namespace App\Services;

use Stripe\Checkout\Session;
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
}
