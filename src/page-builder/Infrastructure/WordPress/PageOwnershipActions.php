<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Canvas\ReturnPageToWordPressUseCase;

/**
 * Completes the owned-page switch after WordPress saves page settings.
 *
 * The action uses the existing post form so pending title, status, and page
 * settings are saved normally before the original page body is restored.
 */
final class PageOwnershipActions
{
    public const ACTION = 'uncanny_page_builder_switch_to_wordpress';
    public const SWITCH_FIELD = 'uncanny_page_builder_switch_to_wordpress';
    public const NONCE_FIELD = 'uncanny_page_builder_switch_to_wordpress_nonce';
    public const NONCE_ACTION = self::ACTION;

    public function __construct(
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
        private readonly ReturnPageToWordPressUseCase $returnToWordPress,
    ) {}

    // Section: WordPress post-save transition

    public function redirectAfterSave($location = null, $postId = null): string
    {
        $location = is_string($location) ? $location : '';
        $postId = WordPressPostId::fromMixed($postId);
        if ($postId === null) {
            return $location;
        }

        $requested = isset($_POST[self::SWITCH_FIELD]) && is_scalar($_POST[self::SWITCH_FIELD])
            ? sanitize_text_field(wp_unslash((string) $_POST[self::SWITCH_FIELD]))
            : '';
        if ($requested !== '1') {
            return $location;
        }

        $nonce = isset($_POST[self::NONCE_FIELD]) && is_scalar($_POST[self::NONCE_FIELD])
            ? sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_FIELD]))
            : '';
        if ($nonce === '' || wp_verify_nonce($nonce, self::NONCE_ACTION) === false) {
            return $location;
        }

        $post = $postId > 0 ? get_post($postId) : null;
        if (!$this->isEditablePage($post)) {
            return $location;
        }

        $this->returnPageToWordPress($postId);

        return admin_url('post.php?post=' . $postId . '&action=edit');
    }

    // Section: Canvas direct transition

    /**
     * Switch from Canvas through a nonce-protected POST and leave the iframe.
     */
    public function switchNow(): void
    {
        check_admin_referer(self::NONCE_ACTION);

        wp_safe_redirect(
            $this->resolveSwitchRedirect($_POST),
            303,
            'Uncanny Page Builder',
        );
        exit;
    }

    /**
     * Resolve the direct action independently so permission and restoration
     * behavior can be verified without terminating the request in tests.
     *
     * @param array<string, mixed> $request
     */
    public function resolveSwitchRedirect(array $request): string
    {
        $rawPageId = $request['page_id'] ?? null;
        $pageId = is_scalar($rawPageId) ? absint(wp_unslash((string) $rawPageId)) : 0;
        $post = $pageId > 0 ? get_post($pageId) : null;

        if (!$this->isEditablePage($post)) {
            wp_die(
                esc_html_x("You don't have permission to switch this page back to WordPress. Ask a site administrator for access.", 'Page Builder', 'uncanny-automator'),
                esc_html_x('The page could not be switched', 'Page Builder', 'uncanny-automator'),
                ['response' => 403, 'back_link' => true],
            );
        }

        $this->returnPageToWordPress($pageId);

        return admin_url('post.php?post=' . $pageId . '&action=edit');
    }

    // Section: Shared ownership transition

    private function returnPageToWordPress(int $postId): void
    {
        try {
            $changed = ($this->returnToWordPress)($postId);
        } catch (\Throwable $error) {
            $this->notifyObservers('uncanny_page_builder_page_switch_failed', $postId, $error);
            wp_die(
                esc_html_x(
                    'WordPress could not safely resume editing this page. Review the page before trying again.',
                    'Page Builder',
                    'uncanny-automator',
                ),
                esc_html_x('The page could not be switched', 'Page Builder', 'uncanny-automator'),
                ['response' => 500, 'back_link' => true],
            );
        }

        if ($changed) {
            $this->notifyObservers('uncanny_page_builder_page_returned_to_wordpress', $postId);
        }
    }

    private function notifyObservers(string $hook, mixed ...$args): void
    {
        try {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Callers pass this plugin's own uncanny_page_builder_* hook names.
            do_action($hook, ...$args);
        } catch (\Throwable $error) {
            // The ownership transition is complete before this notification.
            // An observer cannot reverse it or replace its response.
            DiagnosticLog::write(sprintf(
                '[Uncanny Page Builder] Observer %s failed (%s).',
                $hook,
                $error::class,
            ));
        }
    }

    private function isEditablePage(mixed $post): bool
    {
        if (!$post instanceof \WP_Post) {
            return false;
        }

        return (bool) WordPressCallbackBoundary::valueOrDie(
            'page_ownership.authorize',
            fn (): bool => $post->ID > 0
                && $this->allowedCapabilities->currentUserHasAllowedCapability()
                && current_user_can('edit_post', $post->ID)
                && (($post->post_status ?? '') !== 'publish' || $this->currentUserCanPublish($post)),
        );
    }

    private function currentUserCanPublish(\WP_Post $post): bool
    {
        $postType = get_post_type_object($post->post_type);
        $capability = is_object($postType)
            ? ($postType->cap->publish_posts ?? null)
            : null;

        return is_string($capability)
            && $capability !== ''
            && current_user_can($capability);
    }
}
