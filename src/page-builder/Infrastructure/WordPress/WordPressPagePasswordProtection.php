<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Canvas\PagePasswordProtectionInterface;

/**
 * Keeps exact Page Builder output behind WordPress's native password cookie.
 */
final class WordPressPagePasswordProtection implements PagePasswordProtectionInterface
{
    public function isPasswordRequired(int $pageId): bool
    {
        return $pageId > 0 && post_password_required($pageId);
    }
}
