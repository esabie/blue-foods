<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * D5: the period and product filter applied to every figure on a dashboard or report.
 * Dates are picked in Ghana time and compared against UTC timestamps.
 */
final class MetricFilter
{
    /**
     * @param  list<int>  $productIds
     */
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly array $productIds = [],
    ) {}

    public static function fromRequest(Request $request): self
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'products' => ['nullable', 'array'],
            'products.*' => ['integer'],
        ]);

        $tz = config('inventory.display_timezone');
        $today = CarbonImmutable::now($tz);

        return new self(
            CarbonImmutable::parse($data['from'] ?? $today->startOfYear()->toDateString(), $tz)->startOfDay()->utc(),
            CarbonImmutable::parse($data['to'] ?? $today->toDateString(), $tz)->endOfDay()->utc(),
            array_values(array_map('intval', $data['products'] ?? [])),
        );
    }

    public static function between(string $from, string $to, array $productIds = []): self
    {
        $tz = config('inventory.display_timezone');

        return new self(
            CarbonImmutable::parse($from, $tz)->startOfDay()->utc(),
            CarbonImmutable::parse($to, $tz)->endOfDay()->utc(),
            $productIds,
        );
    }

    /**
     * The period of equal length immediately before this one (for "revenue change").
     */
    public function previous(): self
    {
        $days = (int) $this->from->diffInDays($this->to->addSecond());

        return new self($this->from->subDays($days), $this->from->subSecond(), $this->productIds);
    }

    /**
     * @return array{from: string, to: string, products: list<int>}
     */
    public function toArray(): array
    {
        $tz = config('inventory.display_timezone');

        return [
            'from' => $this->from->setTimezone($tz)->toDateString(),
            'to' => $this->to->setTimezone($tz)->toDateString(),
            'products' => $this->productIds,
        ];
    }
}
