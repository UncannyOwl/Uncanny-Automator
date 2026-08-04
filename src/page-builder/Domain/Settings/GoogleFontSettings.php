<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * One Google Font entry in the brand styles settings.
 */
final class GoogleFontSettings
{
    public function __construct(
        private readonly string $family,
        private readonly string $weights,
    ) {}

    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $family = trim((string) ($data['family'] ?? ''));
        if ($family === '') {
            return null;
        }

        $weights = trim((string) ($data['weights'] ?? ''));
        if ($weights === '') {
            $weights = '100;200;300;400;500;600;700;800;900';
        }

        return new self($family, $weights);
    }

    /**
     * @return array{family: string, weights: string}
     */
    public function toArray(): array
    {
        return [
            'family' => $this->family,
            'weights' => $this->weights,
        ];
    }

    public function family(): string
    {
        return $this->family;
    }

    public function weights(): string
    {
        return $this->weights;
    }
}
