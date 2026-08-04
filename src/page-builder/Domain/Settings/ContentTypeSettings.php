<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * Sitewide selection of content types where Page Builder may be offered.
 */
final class ContentTypeSettings
{
    /** @var list<string> */
    private readonly array $enabledSlugs;

    /**
     * @param list<mixed> $enabledSlugs
     */
    public function __construct(array $enabledSlugs)
    {
        $normalized = [];

        foreach ($enabledSlugs as $slug) {
            if (!is_string($slug)) {
                continue;
            }

            $slug = strtolower(trim($slug));
            if ($slug === '' || preg_match('/^[a-z0-9_-]+$/', $slug) !== 1) {
                continue;
            }

            $normalized[$slug] = true;
        }

        $slugs = array_keys($normalized);
        sort($slugs, SORT_STRING);
        $this->enabledSlugs = array_values($slugs);
    }

    public static function defaults(): self
    {
        return new self(['page']);
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data) || !array_key_exists('enabled', $data)) {
            return self::defaults();
        }

        return is_array($data['enabled'])
            ? new self($data['enabled'])
            : self::defaults();
    }

    /**
     * @return array{enabled: list<string>}
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabledSlugs];
    }

    /**
     * @return list<string>
     */
    public function enabledSlugs(): array
    {
        return $this->enabledSlugs;
    }

    public function isEnabled(string $slug): bool
    {
        return in_array(strtolower(trim($slug)), $this->enabledSlugs, true);
    }
}
