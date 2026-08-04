<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Decides whether the canvas template may render editor chrome.
 *
 * Public page rendering must never expose the editor shell. The chrome belongs
 * only to the hidden wp-admin canvas editor route for an authenticated editor.
 */
final class CanvasEditorChromeGate
{
    /**
     * @param array<string, mixed> $queryParams
     */
    public static function shouldShow(int $postId, array $queryParams): bool
    {
        if ($postId <= 0 || array_key_exists('upb_preview', $queryParams)) {
            return false;
        }

        if (!self::isAdminCanvasEditorRequest($queryParams)) {
            return false;
        }

        return self::currentUserHasAllowedCapability();
    }

    public static function currentUserHasAllowedCapability(): bool
    {
        return current_user_can(PageBuilderAccessCapability::NAME);
    }

    /**
     * @param array<string, mixed> $queryParams
     */
    private static function isAdminCanvasEditorRequest(array $queryParams): bool
    {
        if (($queryParams['page'] ?? '') !== AdminCanvasPage::PAGE_SLUG) {
            return false;
        }

        if (\function_exists('is_admin') && !\is_admin()) {
            return false;
        }

        return true;
    }
}
