<?php

namespace Agentoom\Core\Services;

use DateTimeInterface;
use InvalidArgumentException;

final readonly class AiCostResolution
{
    public const SOURCES = [
        'provider_reported',
        'manual',
        'provider_native',
        'litellm',
        'models_dev',
    ];

    public function __construct(
        public float $providerCost,
        public float $creditCost,
        public string $source,
        public ?int $pricingId,
        public string $provider,
        public ?string $model,
        public ?DateTimeInterface $pricedAt,
    ) {
        if (! in_array($this->source, self::SOURCES, true)) {
            throw new InvalidArgumentException("Unsupported AI pricing source: {$this->source}");
        }
    }
}
