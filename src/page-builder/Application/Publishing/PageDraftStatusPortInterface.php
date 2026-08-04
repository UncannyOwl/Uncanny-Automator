<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

/** Persistence boundary for the public WordPress status of one owned page. */
interface PageDraftStatusPortInterface
{
    public function currentStatus(int $pageId): string;

    /** Change visibility without changing the selected public artifact. */
    public function setDraft(int $pageId): void;

    /** Change visibility without publishing working-draft content. */
    public function setPublished(int $pageId): void;
}
