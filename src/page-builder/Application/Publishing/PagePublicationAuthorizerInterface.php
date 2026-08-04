<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Publishing;

interface PagePublicationAuthorizerInterface
{
    public function canPublish(int $pageId, int $userId): bool;
}
