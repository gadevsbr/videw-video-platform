<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\UserRepository;
use DateTimeImmutable;
use RuntimeException;

final class BillingService
{
    /**
     * @var array<int, string>
     */
    private const PREMIUM_STATUSES = ['active', 'trialing'];

    private UserRepository $users;
    private StripeWebhookEventStore $webhookEvents;

    public function __construct(
        ?UserRepository $users = null,
        ?StripeWebhookEventStore $webhookEvents = null
    ) {
        $this->users = $users ?? new UserRepository();
        $this->webhookEvents = $webhookEvents ?? new StripeWebhookEventStore();
    }

    public function planName(): string
    {
        return (string) config('billing.premium_plan_name', 'Premium');
    }

    public function planCopy(): string
    {
        return (string) config('billing.premium_plan_copy', 'Unlock the full premium catalog with an active recurring subscription.');
    }

    public function planPriceLabel(): string
    {
        return (string) config('billing.premium_price_label', '$9.99 / month');
    }

    public function premiumPriceId(): string
    {
        return trim((string) config('billing.premium_price_id', ''));
    }

    public function isConfigured(): bool
    {
        return trim((string) config('billing.stripe_secret_key', '')) !== ''
            && $this->premiumPriceId() !== '';
    }

    public function webhookConfigured(): bool
    {
        return trim((string) config('billing.stripe_secret_key', '')) !== ''
            && trim((string) config('billing.stripe_webhook_secret', '')) !== '';
    }

    public function webhookUrl(): string
    {
        return base_url('webhooks/stripe.php');
    }

    /**
     * @return array{processed:int,failed:int,processing:int,duplicates:int,total:int,latest:?array<string,mixed>}
     */
    public function webhookStatusSnapshot(int $limit = 25): array
    {
        return $this->webhookEvents->summary($limit);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function webhookRecentEvents(int $limit = 10, ?string $status = null): array
    {
        return $this->webhookEvents->recent($limit, $status);
    }

    public function checkoutUrl(array $user): string
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException($this->message('premium_unavailable', 'Premium access is not available right now.'));
        }

        if (user_has_premium_access($user)) {
            throw new RuntimeException($this->message('already_premium', 'This account already has Premium access.'));
        }

        $params = [
            'line_items' => [
                [
                    'price' => $this->premiumPriceId(),
                    'quantity' => 1,
                ],
            ],
            'mode' => 'subscription',
            'success_url' => base_url('premium.php?checkout=success&session_id={CHECKOUT_SESSION_ID}'),
            'cancel_url' => base_url('premium.php?checkout=cancel'),
            'allow_promotion_codes' => 'true',
            'client_reference_id' => (string) ($user['id'] ?? ''),
            'metadata' => [
                'user_id' => (string) ($user['id'] ?? ''),
            ],
            'subscription_data' => [
                'metadata' => [
                    'user_id' => (string) ($user['id'] ?? ''),
                ],
            ],
        ];

        if (!empty($user['stripe_customer_id'])) {
            $params['customer'] = (string) $user['stripe_customer_id'];
        } else {
            $params['customer_email'] = (string) ($user['email'] ?? '');
        }

        $session = $this->client()->createCheckoutSession($params);
        $url = trim((string) ($session['url'] ?? ''));

        if ($url === '') {
            throw new RuntimeException($this->message('checkout_start_failed', 'We could not start checkout right now.'));
        }

        return $url;
    }

    public function billingPortalUrl(array $user): string
    {
        if (trim((string) ($user['stripe_customer_id'] ?? '')) === '') {
            throw new RuntimeException($this->message('portal_unavailable', 'This account does not have plan management available yet.'));
        }

        $session = $this->client()->createBillingPortalSession([
            'customer' => (string) $user['stripe_customer_id'],
            'return_url' => base_url('account.php#subscription'),
        ]);
        $url = trim((string) ($session['url'] ?? ''));

        if ($url === '') {
            throw new RuntimeException($this->message('portal_open_failed', 'We could not open plan management right now.'));
        }

        return $url;
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function syncSuccessfulCheckout(string $sessionId, ?int $expectedUserId = null): array
    {
        if (trim($sessionId) === '') {
            return ['success' => false, 'message' => $this->message('checkout_confirm_failed', 'We could not confirm your payment.')];
        }

        try {
            $session = $this->client()->retrieveCheckoutSession($sessionId);
            $resolvedUserId = (int) ($session['client_reference_id'] ?? $session['metadata']['user_id'] ?? 0);

            if ($expectedUserId !== null && $expectedUserId > 0 && $resolvedUserId > 0 && $resolvedUserId !== $expectedUserId) {
                throw new RuntimeException($this->message('checkout_account_mismatch', 'This payment confirmation does not match your account.'));
            }

            if (!empty($session['subscription']) && is_array($session['subscription'])) {
                $this->syncSubscriptionPayload($session['subscription'], $resolvedUserId > 0 ? $resolvedUserId : $expectedUserId);
            } elseif (!empty($session['subscription'])) {
                $subscription = $this->client()->retrieveSubscription((string) $session['subscription']);
                $this->syncSubscriptionPayload($subscription, $resolvedUserId > 0 ? $resolvedUserId : $expectedUserId);
            }

            return ['success' => true, 'message' => $this->message('checkout_success', 'Your Premium access is now active.')];
        } catch (RuntimeException $exception) {
            return ['success' => false, 'message' => $exception->getMessage()];
        }
    }

    /**
     * @return array{type:string,event_id:string,status:string}
     */
    public function handleWebhook(string $payload, string $signatureHeader): array
    {
        $secret = trim((string) config('billing.stripe_webhook_secret', ''));

        if ($secret === '') {
            throw new RuntimeException('Stripe webhook secret is missing.');
        }

        $event = $this->verifyWebhookSignature($payload, $signatureHeader, $secret);
        return $this->processWebhookEvent($event);
    }

    /**
     * @return array{type:string,event_id:string,status:string}
     */
    public function retryWebhookEvent(string $eventId): array
    {
        $record = $this->webhookEvents->find($eventId);

        if (!is_array($record)) {
            throw new RuntimeException('Webhook event record not found.');
        }

        $status = (string) ($record['status'] ?? '');

        if ($status === 'processing') {
            throw new RuntimeException('This webhook event is already being processed.');
        }

        if ($status !== 'failed') {
            throw new RuntimeException('Only failed webhook events can be retried manually.');
        }

        $claim = $this->webhookEvents->claim(
            $eventId,
            (string) ($record['type'] ?? ''),
            is_string($record['event_created_at'] ?? null) ? (string) $record['event_created_at'] : null
        );

        try {
            $event = $this->client()->retrieveEvent($claim['event_id']);
        } catch (RuntimeException $exception) {
            $this->webhookEvents->markFailed($claim['event_id'], $exception->getMessage(), [
                'type' => (string) ($record['type'] ?? ''),
                'effect' => 'manual_retry_fetch',
            ]);

            throw new RuntimeException('Could not fetch the Stripe event for retry. ' . $exception->getMessage());
        }

        return $this->processWebhookEvent($event, false, $claim);
    }

    /**
     * @param array<string, mixed> $event
     * @param array{event_id:string,status:string,duplicate:bool,in_progress:bool,record:array<string,mixed>}|null $existingClaim
     * @return array{type:string,event_id:string,status:string}
     */
    private function processWebhookEvent(array $event, bool $shouldClaim = true, ?array $existingClaim = null): array
    {
        $type = (string) ($event['type'] ?? '');
        $eventId = trim((string) ($event['id'] ?? hash('sha256', json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: uniqid('stripe-event-', true))));
        $eventCreatedAt = !empty($event['created']) ? gmdate('c', (int) $event['created']) : null;
        $claim = $existingClaim;

        if ($shouldClaim || !is_array($claim)) {
            $claim = $this->webhookEvents->claim($eventId, $type, $eventCreatedAt);
        }

        if ($claim['duplicate']) {
            return [
                'type' => $type,
                'event_id' => $claim['event_id'],
                'status' => $claim['in_progress'] ? 'duplicate_processing' : 'duplicate_processed',
            ];
        }

        $object = $event['data']['object'] ?? null;

        try {
            if (!is_array($object)) {
                $this->webhookEvents->markProcessed($claim['event_id'], [
                    'type' => $type,
                    'effect' => 'ignored',
                ]);

                return ['type' => $type, 'event_id' => $claim['event_id'], 'status' => 'ignored'];
            }

            if ($type === 'checkout.session.completed') {
                $sessionUserId = (int) ($object['client_reference_id'] ?? $object['metadata']['user_id'] ?? 0);

                if (!empty($object['subscription'])) {
                    $subscription = $this->client()->retrieveSubscription((string) $object['subscription']);
                    $this->syncSubscriptionPayload($subscription, $sessionUserId > 0 ? $sessionUserId : null, (string) ($object['customer'] ?? ''));
                }

                $this->webhookEvents->markProcessed($claim['event_id'], [
                    'type' => $type,
                    'effect' => 'subscription_sync',
                ]);

                return ['type' => $type, 'event_id' => $claim['event_id'], 'status' => 'processed'];
            }

            if (in_array($type, [
                'customer.subscription.created',
                'customer.subscription.updated',
                'customer.subscription.deleted',
                'customer.subscription.paused',
                'customer.subscription.resumed',
            ], true)) {
                $this->syncSubscriptionPayload($object);
                $this->webhookEvents->markProcessed($claim['event_id'], [
                    'type' => $type,
                    'effect' => 'subscription_sync',
                ]);
                return ['type' => $type, 'event_id' => $claim['event_id'], 'status' => 'processed'];
            }

            if (in_array($type, ['invoice.paid', 'invoice.payment_failed'], true) && !empty($object['subscription'])) {
                $subscription = $this->client()->retrieveSubscription((string) $object['subscription']);
                $this->syncSubscriptionPayload($subscription);
                $this->webhookEvents->markProcessed($claim['event_id'], [
                    'type' => $type,
                    'effect' => 'subscription_refresh',
                ]);
                return ['type' => $type, 'event_id' => $claim['event_id'], 'status' => 'processed'];
            }

            $this->webhookEvents->markProcessed($claim['event_id'], [
                'type' => $type,
                'effect' => 'ignored',
            ]);

            return ['type' => $type, 'event_id' => $claim['event_id'], 'status' => 'ignored'];
        } catch (RuntimeException $exception) {
            $this->webhookEvents->markFailed($claim['event_id'], $exception->getMessage(), [
                'type' => $type,
            ]);
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $subscription
     */
    private function syncSubscriptionPayload(array $subscription, ?int $fallbackUserId = null, ?string $fallbackCustomerId = null): void
    {
        $subscriptionId = trim((string) ($subscription['id'] ?? ''));
        $customerId = trim((string) ($subscription['customer'] ?? $fallbackCustomerId ?? ''));
        $status = trim((string) ($subscription['status'] ?? ''));
        $metadataUserId = (int) ($subscription['metadata']['user_id'] ?? 0);
        $user = null;

        if ($subscriptionId !== '') {
            $user = $this->users->findByStripeSubscriptionId($subscriptionId);
        }

        if (!$user && $customerId !== '') {
            $user = $this->users->findByStripeCustomerId($customerId);
        }

        if (!$user && $metadataUserId > 0) {
            $user = $this->users->findById($metadataUserId);
        }

        if (!$user && $fallbackUserId !== null && $fallbackUserId > 0) {
            $user = $this->users->findById($fallbackUserId);
        }

        if (!$user) {
            return;
        }

        $priceId = null;

        if (!empty($subscription['items']['data'][0]['price']['id'])) {
            $priceId = (string) $subscription['items']['data'][0]['price']['id'];
        } elseif (!empty($subscription['items']['data'][0]['price'])) {
            $priceId = (string) $subscription['items']['data'][0]['price'];
        }

        $periodEnd = null;

        if (!empty($subscription['current_period_end'])) {
            $periodEnd = (new DateTimeImmutable('@' . (int) $subscription['current_period_end']))
                ->setTimezone(new \DateTimeZone((string) env_value('VIDEW_TIMEZONE', 'America/Sao_Paulo')))
                ->format('Y-m-d H:i:s');
        }

        $accountTier = in_array($status, self::PREMIUM_STATUSES, true) ? 'premium' : 'free';

        $this->users->syncStripeBilling((int) $user['id'], [
            'account_tier' => $accountTier,
            'stripe_customer_id' => $customerId !== '' ? $customerId : ($user['stripe_customer_id'] ?? null),
            'stripe_subscription_id' => $subscriptionId !== '' ? $subscriptionId : ($user['stripe_subscription_id'] ?? null),
            'stripe_subscription_price_id' => $priceId,
            'stripe_subscription_status' => $status !== '' ? $status : null,
            'stripe_current_period_end' => $periodEnd,
        ]);

        $freshUser = $this->users->findById((int) $user['id']);

        if ($freshUser && current_user() && (int) (current_user()['id'] ?? 0) === (int) $freshUser['id']) {
            $_SESSION['auth_user']['account_tier'] = $freshUser['account_tier'] ?? 'free';
            $_SESSION['auth_user']['stripe_customer_id'] = $freshUser['stripe_customer_id'] ?? null;
            $_SESSION['auth_user']['stripe_subscription_id'] = $freshUser['stripe_subscription_id'] ?? null;
            $_SESSION['auth_user']['stripe_subscription_status'] = $freshUser['stripe_subscription_status'] ?? null;
        }
    }

    private function client(): StripeApiClient
    {
        return new StripeApiClient((string) config('billing.stripe_secret_key', ''));
    }

    private function message(string $key, string $default): string
    {
        return \copy_text('messages.billing.' . $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    private function verifyWebhookSignature(string $payload, string $signatureHeader, string $secret): array
    {
        if (trim($signatureHeader) === '') {
            throw new RuntimeException('Missing Stripe signature header.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = (int) $value;
                continue;
            }

            if ($key === 'v1' && is_string($value)) {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || $signatures === []) {
            throw new RuntimeException('Invalid Stripe signature header.');
        }

        if (abs(time() - $timestamp) > 300) {
            throw new RuntimeException('Expired Stripe webhook signature.');
        }

        $signedPayload = $timestamp . '.' . $payload;
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);
        $matched = false;

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            throw new RuntimeException('Stripe webhook signature verification failed.');
        }

        $event = json_decode($payload, true);

        if (!is_array($event)) {
            throw new RuntimeException('Invalid Stripe webhook payload.');
        }

        return $event;
    }
}
