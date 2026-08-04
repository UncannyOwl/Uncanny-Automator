<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

/**
 * Shared shaping for submitted typography role payloads.
 *
 * Both the sitewide Typography form (ds_typography) and the page-level
 * overrides metabox (upb_ds_typography) pass their POST payload through here
 * before the domain layer validates it, so the two surfaces cannot drift.
 * Value VALIDATION stays in TypographyRoleProfile::fromArray() — this class
 * only removes UI artifacts and, for page scope, inherit markers.
 */
final class TypographyRolesPostPayload
{
    /**
     * Drop the '__custom__' font dropdown sentinel — it is the select's
     * UI marker for "show the custom input", never a real font value.
     *
     * @return array<string, mixed>
     */
    public static function withoutCustomSentinel(mixed $rawRoles): array
    {
        if (!is_array($rawRoles)) {
            return [];
        }

        foreach ($rawRoles as $role => $fields) {
            if (is_array($fields) && ($fields['font_family'] ?? '') === '__custom__') {
                unset($rawRoles[$role]['font_family']);
            }
        }

        return $rawRoles;
    }

    /**
     * Page scope: an empty field means "inherit the site value", so empty
     * values and empty roles are dropped — only explicit overrides survive.
     *
     * @return array<string, array<string, string>>
     */
    public static function sparseOverrides(mixed $rawRoles): array
    {
        $sparse = [];

        foreach (self::withoutCustomSentinel($rawRoles) as $roleKey => $fields) {
            if (!is_string($roleKey) || !is_array($fields)) {
                continue;
            }

            $kept = [];
            foreach ($fields as $fieldKey => $value) {
                if (!is_string($fieldKey) || !is_string($value)) {
                    continue;
                }

                $value = trim($value);
                if ($value === '') {
                    continue;
                }

                $kept[$fieldKey] = $value;
            }

            if ($kept !== []) {
                $sparse[$roleKey] = $kept;
            }
        }

        return $sparse;
    }
}
