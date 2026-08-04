<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\ContentType;

use UncannyPageBuilder\Application\Settings\ListDisplayableContentTypesUseCase;
use UncannyPageBuilder\Application\Settings\ListEnabledContentTypesUseCase;
use UncannyPageBuilder\Domain\ContentType\ContentType;

/**
 * Answers whether Page Builder may currently be offered for a post type.
 *
 * The configured instance resolves the intersection of saved administrator
 * intent and current host capability. A no-argument instance retains the
 * page-only baseline for isolated adapters and tests that deliberately operate
 * without a WordPress/settings runtime.
 *
 * This is a mutable eligibility decision, not persisted ownership. Runtime
 * capability loss may preserve an owned post for recovery. Explicit settings
 * removal makes Page Builder dormant for that type, while recovery and
 * deletion cleanup remain available for its retained Page Builder data.
 */
final class SupportsPostTypeUseCase
{
    /** @var list<string>|null */
    private ?array $resolvedPostTypes = null;

    /** @var list<string>|null */
    private ?array $enabledPostTypes = null;

    public function __construct(
        private readonly ?ListDisplayableContentTypesUseCase $displayableContentTypes = null,
        private readonly ?ListEnabledContentTypesUseCase $enabledContentTypes = null,
    ) {}

    public function isSupported(string $postType): bool
    {
        return in_array($postType, $this->supportedPostTypes(), true);
    }

    /**
     * Administrator intent is checked separately on continuity surfaces.
     * Persisted ownership may bridge missing runtime capability, but it must
     * never override an explicit decision to disable the post type.
     */
    public function isEnabledByAdministrator(string $postType): bool
    {
        return in_array($postType, $this->administratorEnabledPostTypes(), true);
    }

    /**
     * @return list<string>
     */
    private function supportedPostTypes(): array
    {
        if ($this->resolvedPostTypes !== null) {
            return $this->resolvedPostTypes;
        }

        if ($this->displayableContentTypes === null) {
            return $this->resolvedPostTypes = ['page'];
        }

        return $this->resolvedPostTypes = array_map(
            static fn (ContentType $contentType): string => $contentType->slug(),
            ($this->displayableContentTypes)(),
        );
    }

    /**
     * @return list<string>
     */
    private function administratorEnabledPostTypes(): array
    {
        if ($this->enabledPostTypes !== null) {
            return $this->enabledPostTypes;
        }

        if ($this->enabledContentTypes === null) {
            return $this->enabledPostTypes = ['page'];
        }

        return $this->enabledPostTypes = array_values(array_unique(array_filter(
            array_map('strval', ($this->enabledContentTypes)()),
            static fn (string $postType): bool => $postType !== '',
        )));
    }
}
