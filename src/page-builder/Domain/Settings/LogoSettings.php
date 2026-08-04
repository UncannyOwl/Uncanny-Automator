<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * Stored logo selection for the sitewide settings aggregate.
 */
final class LogoSettings
{
    public function __construct(
        private readonly int $attachmentId = 0,
    ) {}

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return new self();
        }

        return new self(max(0, (int) ($data['attachment_id'] ?? 0)));
    }

    /**
     * @return array{attachment_id: int}
     */
    public function toArray(): array
    {
        return [
            'attachment_id' => $this->attachmentId,
        ];
    }

    public function attachmentId(): int
    {
        return $this->attachmentId;
    }
}
