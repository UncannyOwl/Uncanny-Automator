<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Preview;

use UncannyPageBuilder\Application\Export\StaticPageExportBuilderInterface;
use UncannyPageBuilder\Domain\Export\StaticPageExport;
use UncannyPageBuilder\Domain\Export\StaticExportPurpose;

/** A shared preview must never freeze the issuing administrator's identity. */
final class VisitorPreviewBuilder implements StaticPageExportBuilderInterface
{
    public function __construct(private readonly StaticPageExportBuilderInterface $builder) {}

    public function buildForPage(
        int $pageId,
        ?string $documentTitle = null,
        ?string $documentPermalink = null,
        StaticExportPurpose $purpose = StaticExportPurpose::Portable,
    ): StaticPageExport {
        $userId = get_current_user_id();
        try {
            wp_set_current_user(0);
            return $this->builder->buildForPage($pageId, $documentTitle, $documentPermalink, $purpose);
        } finally {
            wp_set_current_user($userId);
        }
    }
}
