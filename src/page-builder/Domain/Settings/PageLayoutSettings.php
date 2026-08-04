<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * Page-layout subsection of the sitewide settings aggregate.
 */
final class PageLayoutSettings
{
    public function __construct(
        private readonly ?int $defaultHeaderId,
        private readonly ?int $defaultFooterId,
    ) {}

    public static function defaults(): self
    {
        return new self(null, null);
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return self::defaults();
        }

        return new self(
            self::normalizeOptionalId($data['default_header_id'] ?? null),
            self::normalizeOptionalId($data['default_footer_id'] ?? null),
        );
    }

    /**
     * @return array{default_header_id: ?int, default_footer_id: ?int}
     */
    public function toArray(): array
    {
        return [
            'default_header_id' => $this->defaultHeaderId,
            'default_footer_id' => $this->defaultFooterId,
        ];
    }

    public function defaultHeaderId(): ?int
    {
        return $this->defaultHeaderId;
    }

    public function defaultFooterId(): ?int
    {
        return $this->defaultFooterId;
    }

    private static function normalizeOptionalId(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
