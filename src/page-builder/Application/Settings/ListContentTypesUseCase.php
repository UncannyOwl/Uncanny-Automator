<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Settings;

use UncannyPageBuilder\Domain\ContentType\ContentTypeCatalogInterface;
use UncannyPageBuilder\Domain\ContentType\PageBuilderDisplayPolicy;
use UncannyPageBuilder\Domain\Settings\SettingsRepositoryInterface;

final class ListContentTypesUseCase
{
    public function __construct(
        private readonly ContentTypeCatalogInterface $catalog,
        private readonly PageBuilderDisplayPolicy $displayPolicy,
        private readonly SettingsRepositoryInterface $settings,
    ) {}

    /**
     * @return list<ContentTypeOption>
     */
    public function __invoke(): array
    {
        $saved = $this->settings->load()->contentTypes();
        $options = [];

        foreach ($this->catalog->contentTypes() as $contentType) {
            if (!$this->displayPolicy->isEligible($contentType)) {
                continue;
            }

            $options[] = new ContentTypeOption(
                $contentType->slug(),
                $contentType->label(),
                $saved->isEnabled($contentType->slug()),
            );
        }

        usort(
            $options,
            static function (ContentTypeOption $left, ContentTypeOption $right): int {
                if ($left->slug() === 'page') {
                    return -1;
                }
                if ($right->slug() === 'page') {
                    return 1;
                }

                return strcasecmp($left->label(), $right->label());
            },
        );

        return $options;
    }
}
