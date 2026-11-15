<?php

namespace App\Support\Billing;

/**
 * A plan from config/billing.php.
 */
final readonly class Plan
{
    /**
     * @param  array<string, array{amount: int, stripe_price: string|null}>  $prices  keyed by interval (monthly, yearly)
     * @param  list<string>  $features
     * @param  array<string, int|null>  $limits
     */
    public function __construct(
        public bool $perSeat,
        public string $key,
        public string $name,
        public string $description,
        public array $prices,
        public array $features,
        public array $limits,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        return new self(
            perSeat: (bool) ($config['per_seat'] ?? false),
            key: $key,
            name: (string) ($config['name'] ?? ucfirst($key)),
            description: (string) ($config['description'] ?? ''),
            prices: $config['prices'] ?? [],
            features: array_values($config['features'] ?? []),
            limits: $config['limits'] ?? [],
        );
    }

    public function stripePrice(string $interval): ?string
    {
        return $this->prices[$interval]['stripe_price'] ?? null;
    }

    public function isFree(): bool
    {
        return $this->prices === [];
    }

    /**
     * Which plan and interval a Stripe price id belongs to (used by the Cashier integration).
     */
    public function intervalForStripePrice(string $priceId): ?string
    {
        foreach ($this->prices as $interval => $price) {
            if (($price['stripe_price'] ?? null) === $priceId) {
                return $interval;
            }
        }

        return null;
    }
}
