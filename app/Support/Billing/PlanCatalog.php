<?php

namespace App\Support\Billing;

use InvalidArgumentException;

class PlanCatalog
{
    /**
     * @return list<Plan>
     */
    public function all(): array
    {
        $plans = [];

        foreach (config('billing.plans', []) as $key => $config) {
            $plans[] = Plan::fromConfig((string) $key, $config);
        }

        return $plans;
    }

    public function find(?string $key): ?Plan
    {
        $config = $key !== null ? config("billing.plans.{$key}") : null;

        return is_array($config) ? Plan::fromConfig($key, $config) : null;
    }

    public function get(string $key): Plan
    {
        return $this->find($key) ?? throw new InvalidArgumentException("Unknown plan [{$key}].");
    }

    public function default(): Plan
    {
        return $this->get(config('billing.default_plan'));
    }

    public function trial(): Plan
    {
        return $this->get(config('billing.trial.plan'));
    }

    public function forStripePrice(string $priceId): ?Plan
    {
        foreach ($this->all() as $plan) {
            if ($plan->intervalForStripePrice($priceId) !== null) {
                return $plan;
            }
        }

        return null;
    }
}
