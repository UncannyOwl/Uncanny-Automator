<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Settings;

/**
 * Tool-settings subsection of the sitewide settings aggregate.
 */
final class ToolSettings
{
    public const LIBRARY_ANIME = 'anime';
    public const LIBRARY_SWIPER = 'swiper';

    public function __construct(
        private readonly bool $pageCustomJavaScriptEnabled = true,
        private readonly bool $globalPartCustomJavaScriptEnabled = true,
        /** @var array<string, bool> */
        private readonly array $approvedLibraries = [
            self::LIBRARY_ANIME => true,
            self::LIBRARY_SWIPER => true,
        ],
    ) {}

    public static function defaults(): self
    {
        return new self();
    }

    public static function fromArray(mixed $data): self
    {
        if (!is_array($data)) {
            return self::defaults();
        }

        $customJavaScript = is_array($data['custom_javascript'] ?? null)
            ? $data['custom_javascript']
            : [];
        $approvedLibraries = is_array($data['approved_libraries'] ?? null)
            ? $data['approved_libraries']
            : [];

        return new self(
            self::toBoolean($customJavaScript['page'] ?? true),
            self::toBoolean($customJavaScript['global_part'] ?? true),
            self::normalizedApprovedLibraries($approvedLibraries),
        );
    }

    /**
     * @return array{
     *     custom_javascript: array{page: bool, global_part: bool},
     *     approved_libraries: array{anime: bool, swiper: bool}
     * }
     */
    public function toArray(): array
    {
        return [
            'custom_javascript' => [
                'page' => $this->pageCustomJavaScriptEnabled,
                'global_part' => $this->globalPartCustomJavaScriptEnabled,
            ],
            'approved_libraries' => $this->approvedLibraries,
        ];
    }

    public function pageCustomJavaScriptEnabled(): bool
    {
        return $this->pageCustomJavaScriptEnabled;
    }

    public function globalPartCustomJavaScriptEnabled(): bool
    {
        return $this->globalPartCustomJavaScriptEnabled;
    }

    /**
     * @return array{anime: bool, swiper: bool}
     */
    public function approvedLibraries(): array
    {
        return $this->approvedLibraries;
    }

    public function libraryEnabled(string $slug): bool
    {
        return $this->approvedLibraries[$slug] ?? false;
    }

    /**
     * @return list<string>
     */
    public static function knownLibrarySlugs(): array
    {
        return [
            self::LIBRARY_ANIME,
            self::LIBRARY_SWIPER,
        ];
    }

    private static function toBoolean(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * @param array<string, mixed> $libraries
     * @return array{anime: bool, swiper: bool}
     */
    private static function normalizedApprovedLibraries(array $libraries): array
    {
        $normalized = [];
        foreach (self::knownLibrarySlugs() as $slug) {
            $normalized[$slug] = self::toBoolean($libraries[$slug] ?? true);
        }

        return $normalized;
    }
}
