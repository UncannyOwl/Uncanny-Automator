<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * One uploaded font entry in the brand styles settings.
 */
final class CustomFontSettings
{
    public function __construct(
        private readonly string $family,
        private readonly int $attachmentId,
        private readonly string $weight,
    ) {}

    public static function fromArray(mixed $data): ?self
    {
        if (!is_array($data)) {
            return null;
        }

        $family = trim((string) ($data['family'] ?? ''));
        $attachmentId = max(0, (int) ($data['attachment_id'] ?? 0));
        if ($family === '' || $attachmentId <= 0) {
            return null;
        }

        $weight = trim((string) ($data['weight'] ?? '400'));
        if ($weight === '') {
            $weight = '400';
        }

        return new self($family, $attachmentId, $weight);
    }

    /**
     * @return array{family: string, attachment_id: int, weight: string}
     */
    public function toArray(): array
    {
        return [
            'family' => $this->family,
            'attachment_id' => $this->attachmentId,
            'weight' => $this->weight,
        ];
    }

    public function family(): string
    {
        return $this->family;
    }

    public function attachmentId(): int
    {
        return $this->attachmentId;
    }

    public function weight(): string
    {
        return $this->weight;
    }
}
