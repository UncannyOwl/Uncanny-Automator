<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\ContentType\ContentType;
use UncannyPageBuilder\Domain\ContentType\ContentTypeCatalogInterface;
use UncannyPageBuilder\Domain\ContentType\PageBuilderDisplayPolicy;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;

/**
 * Resolves the intersection of saved user intent and current host capability.
 */
final class ListDisplayableContentTypesUseCase
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly ContentTypeCatalogInterface $catalog,
        private readonly PageBuilderDisplayPolicy $displayPolicy,
    ) {}

    /**
     * @return list<ContentType>
     */
    public function __invoke(): array
    {
        $saved = $this->settings->load()->contentTypes();
        $displayable = [];

        foreach ($this->catalog->contentTypes() as $contentType) {
            if ($this->displayPolicy->shouldDisplay($contentType, $saved)) {
                $displayable[] = $contentType;
            }
        }

        return $displayable;
    }
}
