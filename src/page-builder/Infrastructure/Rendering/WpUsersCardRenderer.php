<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Rendering;

use UncannyPageBuilder\Domain\Section\DynamicContentConfig;

/**
 * Render data-ai-dynamic="wp_users" loops.
 *
 * Runs a WP_User_Query and clones the card template for each user,
 * replacing data-ai-bind placeholders with safe public user data.
 *
 * Supported bind keys:
 *   display_name — user's display name
 *   avatar       — avatar image URL (96px)
 *   bio          — user description/bio
 *   profile_url  — author archive URL
 *   meta.<key>   — governed user meta (allowlist only)
 */
final class WpUsersCardRenderer implements SectionRendererInterface
{
    private const ALLOWED_ORDERBY = [
        'display_name', 'user_nicename', 'user_url', 'user_registered', 'ID', 'include',
    ];

    private const AVATAR_PLACEHOLDER_PATH =
        'assets/images/bindings/feat-image-placeholders/placeholder-1.png';

    public function render(string $cardTemplate, array $args): string
    {
        $role    = sanitize_key($args['role'] ?? '');
        $count   = DynamicCardCount::resolve($args['count'] ?? null, 6);
        $orderby = $this->sanitizeOrderby($args['orderby'] ?? 'display_name');

        // Section: public directory bindings only list users with published
        // content. This keeps author/profile loops aligned with public WordPress
        // archives instead of exposing dormant user records by default.
        $queryArgs = [
            'number'              => $count,
            'orderby'             => $orderby,
            'order'               => 'ASC',
            'has_published_posts' => true,
        ];

        if ($role !== '' && $role !== 'any') {
            $queryArgs['role'] = $role;
        }

        $users = get_users($queryArgs);

        if (empty($users)) {
            return '<!-- No users found -->';
        }

        $metaBindKeys = $this->extractMetaBindKeys($cardTemplate);
        $output = '';

        foreach ($users as $user) {
            $card = $cardTemplate;

            // ── Static user fields ──

            // display_name
            $card = CardBindingEngine::text($card, 'display_name', esc_html($user->display_name));

            // avatar (img src)
            $avatarUrl = get_avatar_url($user->ID, ['size' => 96]) ?: $this->avatarPlaceholderUrl();
            $card = CardBindingEngine::image($card, 'avatar', esc_url($avatarUrl));

            // bio
            $bio = $user->description ?: '';
            $card = CardBindingEngine::text($card, 'bio', esc_html(wp_trim_words($bio, 30)));

            // profile_url (a href)
            $profileUrl = get_author_posts_url($user->ID);
            $card = CardBindingEngine::href($card, 'profile_url', esc_url($profileUrl));

            // ── User meta bindings (blocklist-governed) ──
            foreach ($metaBindKeys as $metaKey) {
                $bindAttr = 'meta.' . $metaKey;
                if (!DynamicContentConfig::isMetaKeyAllowed($metaKey)) {
                    $card = MetaBindingHelper::clearBinding($card, $bindAttr);
                    continue;
                }
                $metaValue = get_user_meta($user->ID, $metaKey, true);
                $card = MetaBindingHelper::applyMetaBinding($card, $bindAttr, $metaValue, $metaKey);
            }

            $output .= $card . "\n";
        }

        return $output;
    }

    private function sanitizeOrderby(mixed $orderby): string
    {
        return is_string($orderby) && in_array($orderby, self::ALLOWED_ORDERBY, true)
            ? $orderby
            : 'display_name';
    }

    private function avatarPlaceholderUrl(): string
    {
        if (!\defined('UNCANNY_PB_URL')) {
            return '';
        }

        return (string) \constant('UNCANNY_PB_URL') . self::AVATAR_PLACEHOLDER_PATH;
    }

    /**
     * @return string[]
     */
    private function extractMetaBindKeys(string $cardTemplate): array
    {
        if (preg_match_all('/data-ai-bind="meta\.([^"]+)"/', $cardTemplate, $matches)) {
            return array_unique($matches[1]);
        }
        return [];
    }
}
