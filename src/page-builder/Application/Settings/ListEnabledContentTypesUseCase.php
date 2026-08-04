<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\ContentType\PageBuilderDisplayPolicy;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;

/**
 * Reads administrator intent inside the product's supported post-type boundary.
 *
 * Older settings may retain custom post type slugs for dormant recovery, but
 * unsupported types must not remain active merely because they were enabled by
 * an earlier build.
 */
final class ListEnabledContentTypesUseCase
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly PageBuilderDisplayPolicy $displayPolicy,
    ) {}

    /**
     * @return list<string>
     */
    public function __invoke(): array
    {
        return array_values(array_filter(
            $this->settings->load()->contentTypes()->enabledSlugs(),
            fn (string $slug): bool => $this->displayPolicy->supportsSlug($slug),
        ));
    }
}
