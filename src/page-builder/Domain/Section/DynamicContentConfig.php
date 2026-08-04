<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Section;

/**
 * Governance for dynamic content meta bindings.
 *
 * Public custom fields are allowed unless blocked. Protected underscore-prefixed
 * meta keys are rejected unless the infrastructure layer explicitly registers
 * their value type at boot time. Sensitive keys always fail closed.
 */
final class DynamicContentConfig
{
    /** Safe user fields that may be bound in wp_users loops. */
    private const USER_FIELDS = ['display_name', 'avatar', 'bio', 'profile_url'];

    /** Supported meta value types for typed handling. */
    public const META_TYPE_TEXT   = 'text';
    public const META_TYPE_URL    = 'url';
    public const META_TYPE_IMAGE  = 'image';
    public const META_TYPE_NUMBER = 'number';

    private const VALID_META_TYPES = [
        self::META_TYPE_TEXT,
        self::META_TYPE_URL,
        self::META_TYPE_IMAGE,
        self::META_TYPE_NUMBER,
    ];

    /**
     * Meta key prefixes that are always blocked.
     * These are internal WordPress/plugin keys that should never be exposed.
     */
    private const BLOCKED_PREFIXES = [
        '_edit_',        // _edit_lock, _edit_last
        '_wp_old_',      // _wp_old_slug, _wp_old_date
        '_wp_trash_',    // _wp_trash_meta_status, _wp_trash_meta_time
        '_oembed_',      // oEmbed cache keys
        '_encloseme',
        '_pingme',
        '_wp_attached_', // _wp_attached_file (path exposure)
    ];

    /**
     * Exact meta keys that are always blocked.
     */
    private const BLOCKED_KEYS = [
        '_edit_lock',
        '_edit_last',
        '_wp_page_template',
        '_wp_attachment_metadata',
        '_wp_attached_file',
        '_menu_item_type',
        '_menu_item_menu_item_parent',
        '_menu_item_object_id',
        '_menu_item_object',
        '_menu_item_target',
        '_menu_item_classes',
        '_menu_item_xfn',
        '_menu_item_url',
        '_wp_old_slug',
        '_wp_old_date',
        '_encloseme',
        '_pingme',
        // Auth/session keys (unprefixed + default wp_ table prefix variants)
        'session_tokens',
        '_capabilities',
        '_user_level',
        'wp_capabilities',
        'wp_user_level',
        // Uncanny Page Builder internal
        '_uncanny_page_builder_compiled_css',
        '_uncanny_ai_compiled_css',
        '_uncanny_ai_design_tokens',
        '_uncanny_page_builder_design_standards_overrides',
        '_uncanny_engine_theme_overrides',
        '_upb_global_part_type',
    ];

    private const SENSITIVE_KEY_PATTERNS = [
        '/(?:^|_)token(?:_|$)/i',
        '/(?:^|_)secret(?:_|$)/i',
        '/activation_key/i',
        '/password/i',
        '/private_key/i',
        '/^wp_\d+_capabilities$/i',
        '/^wp_\d+_user_level$/i',
    ];

    /** @var string[] Extra blocked keys injected by infrastructure. */
    private static array $extraBlockedKeys = [];

    /** @var array<string, string> Meta key → type map injected by infrastructure. */
    private static array $metaValueTypes = [];

    /**
     * Configure extension data from infrastructure layer.
     *
     * Called once at boot by WpDynamicContentConfigProvider (or similar)
     * which resolves values from WordPress filters. This keeps apply_filters
     * out of the domain layer.
     *
     * @param string[]             $extraBlockedKeys Additional meta keys to block.
     * @param array<string, string> $metaValueTypes   Meta key → type map.
     */
    public static function configure(array $extraBlockedKeys, array $metaValueTypes): void
    {
        self::$extraBlockedKeys = array_filter($extraBlockedKeys, 'is_string');
        self::$metaValueTypes   = $metaValueTypes;
    }

    /**
     * Reset to defaults (for testing).
     */
    public static function reset(): void
    {
        self::$extraBlockedKeys = [];
        self::$metaValueTypes   = [];
    }

    /**
     * Check if a meta key is allowed.
     */
    public static function isMetaKeyAllowed(string $metaKey): bool
    {
        if ($metaKey === '' || $metaKey === '0') {
            return false;
        }

        // Check exact blocklist.
        $blocked = self::blockedKeys();
        if (in_array($metaKey, $blocked, true)) {
            return false;
        }

        foreach (self::SENSITIVE_KEY_PATTERNS as $pattern) {
            if (preg_match($pattern, $metaKey) === 1) {
                return false;
            }
        }

        // Check prefix blocklist.
        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if (str_starts_with($metaKey, $prefix)) {
                return false;
            }
        }

        if (str_starts_with($metaKey, '_') && !array_key_exists($metaKey, self::$metaValueTypes)) {
            return false;
        }

        return true;
    }

    /**
     * Merged blocklist: hardcoded + infrastructure-provided.
     *
     * @return string[]
     */
    public static function blockedKeys(): array
    {
        return array_values(array_unique(array_merge(
            self::BLOCKED_KEYS,
            self::$extraBlockedKeys,
        )));
    }

    /**
     * Safe public user fields for wp_users bind keys.
     *
     * @return string[]
     */
    public static function userFields(): array
    {
        return self::USER_FIELDS;
    }

    /**
     * Resolve the typed handling mode for a meta key.
     *
     * Falls back to 'text' for unknown keys.
     */
    public static function metaValueType(string $metaKey): string
    {
        $type = self::$metaValueTypes[$metaKey] ?? self::META_TYPE_TEXT;
        return in_array($type, self::VALID_META_TYPES, true) ? $type : self::META_TYPE_TEXT;
    }

    /** @return string[] */
    public static function validMetaTypes(): array
    {
        return self::VALID_META_TYPES;
    }

    /**
     * Full config snapshot for REST/agent consumption.
     *
     * @return array<string, mixed>
     */
    public static function toArray(): array
    {
        return [
            'meta_key_policy'    => 'blocklist',
            'blocked_meta_keys'  => self::blockedKeys(),
            'user_fields'        => self::USER_FIELDS,
            'meta_value_types'   => self::validMetaTypes(),
        ];
    }
}
