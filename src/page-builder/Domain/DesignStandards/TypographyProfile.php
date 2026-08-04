<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\DesignStandards;

/**
 * Canonical sitewide or page-sparse typography role collection.
 *
 * Roles are normalized on read so older storage and future UI aliases can map
 * to one stable vocabulary inside the domain.
 */
final class TypographyProfile
{
    // ── Role Contract ───────────────────────────────────────

    /** @var array<string, string> */
    private const ROLE_ALIASES = [
        'body' => 'body',
        'paragraph' => 'paragraph',
        'paragraphs' => 'paragraph',
        'heading' => 'headings',
        'headings' => 'headings',
        'button' => 'buttons',
        'buttons' => 'buttons',
        'nav' => 'navigation',
        'navigation' => 'navigation',
        'blockquote' => 'blockquote',
        'code' => 'code',
        'caption' => 'caption',
        'captions' => 'caption',
        'small' => 'caption',
        'caption_small' => 'caption',
        'caption_small_text' => 'caption',
        'h1' => 'h1',
        'h2' => 'h2',
        'h3' => 'h3',
        'h4' => 'h4',
        'h5' => 'h5',
        'h6' => 'h6',
    ];

    /** @var list<string> */
    private const ROLE_ORDER = [
        'body',
        'paragraph',
        'headings',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'buttons',
        'navigation',
        'blockquote',
        'code',
        'caption',
    ];

    /**
     * @param array<string, TypographyRoleProfile> $roles
     */
    public function __construct(
        private readonly array $roles = [],
    ) {}

    public static function defaults(): self
    {
        return self::fromTokens(BootstrapTokenProfile::defaults()->toArray());
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawRoles = is_array($data['roles'] ?? null) ? $data['roles'] : [];

        return self::fromRolesArray($rawRoles);
    }

    /**
     * @param array<string, mixed> $roles
     */
    public static function fromRolesArray(array $roles): self
    {
        $normalized = [];

        foreach ($roles as $key => $value) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException('Typography roles must use string keys.');
            }

            $role = self::normalizeRoleName($key);
            if (!is_array($value)) {
                throw new \InvalidArgumentException("Typography role '{$role}' must be an object.");
            }

            $profile = TypographyRoleProfile::fromArray($value, "Typography role '{$role}'");
            if ($profile->isEmpty()) {
                continue;
            }

            $normalized[$role] = $profile;
        }

        return new self(self::orderedRoles($normalized));
    }

    /**
     * Derive the role model from the persisted token map.
     *
     * This is the schema 2.0 -> 3.0 compatibility bridge: token-only state
     * becomes a role-first model without losing Bootstrap compatibility.
     *
     * @param array<string, string> $tokens
     */
    public static function fromTokens(array $tokens): self
    {
        $bodyFamily = self::tokenValue($tokens, '--bs-body-font-family')
            ?? self::tokenValue($tokens, '--bs-font-sans-serif')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-body-font-family'];
        $bodySize = self::tokenValue($tokens, '--bs-body-font-size')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-body-font-size'];
        $bodyWeight = self::tokenValue($tokens, '--bs-body-font-weight')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-body-font-weight'];
        $bodyLineHeight = self::tokenValue($tokens, '--bs-body-line-height')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-body-line-height'];
        $buttonSize = self::tokenValue($tokens, '--bs-btn-font-size')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-btn-font-size'];
        $buttonWeight = self::tokenValue($tokens, '--bs-btn-font-weight')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-btn-font-weight'];
        $monospace = self::tokenValue($tokens, '--bs-font-monospace')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-font-monospace'];
        $headingFamily = self::tokenValue($tokens, '--bs-heading-font-family', '--bs-headings-font-family') ?? 'inherit';
        $headingWeight = self::tokenValue($tokens, '--bs-heading-font-weight', '--bs-headings-font-weight')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-heading-font-weight'];
        $headingLineHeight = self::tokenValue($tokens, '--bs-heading-line-height', '--bs-headings-line-height')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-heading-line-height'];
        $headingColor = self::tokenValue($tokens, '--bs-heading-color')
            ?? BootstrapTokenProfile::defaults()->toArray()['--bs-heading-color'];

        return self::fromRolesArray([
            'body' => [
                'font_family' => $bodyFamily,
                'font_size' => $bodySize,
                'font_weight' => $bodyWeight,
                'line_height' => $bodyLineHeight,
            ],
            'paragraph' => [
                'font_family' => 'inherit',
                'font_size' => 'inherit',
                'font_weight' => 'inherit',
                'line_height' => 'inherit',
            ],
            'headings' => [
                'font_family' => $headingFamily,
                'font_weight' => $headingWeight,
                'line_height' => $headingLineHeight,
                'color' => $headingColor,
            ],
            'h1' => ['font_size' => self::tokenValue($tokens, '--bs-heading-h1-font-size') ?? '2.5rem'],
            'h2' => ['font_size' => self::tokenValue($tokens, '--bs-heading-h2-font-size') ?? '2rem'],
            'h3' => ['font_size' => self::tokenValue($tokens, '--bs-heading-h3-font-size') ?? '1.75rem'],
            'h4' => ['font_size' => self::tokenValue($tokens, '--bs-heading-h4-font-size') ?? '1.5rem'],
            'h5' => ['font_size' => self::tokenValue($tokens, '--bs-heading-h5-font-size') ?? '1.25rem'],
            'h6' => ['font_size' => self::tokenValue($tokens, '--bs-heading-h6-font-size') ?? '1rem'],
            'buttons' => [
                'font_family' => self::tokenValue($tokens, '--bs-btn-font-family') ?? 'inherit',
                'font_size' => $buttonSize,
                'font_weight' => $buttonWeight,
            ],
            'navigation' => [
                'font_family' => self::tokenValue($tokens, '--upb-nav-font-family') ?? 'inherit',
                'font_size' => self::tokenValue($tokens, '--upb-nav-font-size') ?? 'inherit',
                'font_weight' => self::tokenValue($tokens, '--upb-nav-font-weight') ?? '600',
                'line_height' => self::tokenValue($tokens, '--upb-nav-line-height') ?? 'inherit',
                'letter_spacing' => self::tokenValue($tokens, '--upb-nav-letter-spacing') ?? 'inherit',
                'text_transform' => self::tokenValue($tokens, '--upb-nav-text-transform') ?? 'inherit',
            ],
            'blockquote' => [
                'font_family' => self::tokenValue($tokens, '--upb-blockquote-font-family') ?? 'inherit',
                'font_size' => self::tokenValue($tokens, '--upb-blockquote-font-size') ?? 'inherit',
                'font_weight' => self::tokenValue($tokens, '--upb-blockquote-font-weight') ?? 'inherit',
                'line_height' => self::tokenValue($tokens, '--upb-blockquote-line-height') ?? 'inherit',
                'font_style' => self::tokenValue($tokens, '--upb-blockquote-font-style') ?? 'italic',
            ],
            'code' => [
                'font_family' => $monospace,
                'font_size' => self::tokenValue($tokens, '--upb-code-font-size') ?? 'inherit',
                'font_weight' => self::tokenValue($tokens, '--upb-code-font-weight') ?? 'inherit',
                'line_height' => self::tokenValue($tokens, '--upb-code-line-height') ?? 'inherit',
            ],
            'caption' => [
                'font_family' => self::tokenValue($tokens, '--upb-small-font-family') ?? 'inherit',
                'font_size' => self::tokenValue($tokens, '--upb-small-font-size') ?? '0.875rem',
                'font_weight' => self::tokenValue($tokens, '--upb-small-font-weight') ?? 'inherit',
                'line_height' => self::tokenValue($tokens, '--upb-small-line-height') ?? 'inherit',
                'letter_spacing' => self::tokenValue($tokens, '--upb-small-letter-spacing') ?? 'inherit',
                'text_transform' => self::tokenValue($tokens, '--upb-small-text-transform') ?? 'inherit',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $roles = [];
        foreach ($this->roles as $name => $role) {
            $roles[$name] = $role->toArray();
        }

        return [
            'roles' => $roles ?: new \stdClass(),
        ];
    }

    /** @return array<string, TypographyRoleProfile> */
    public function roles(): array
    {
        return $this->roles;
    }

    public function role(string $roleName): ?TypographyRoleProfile
    {
        return $this->roles[self::normalizeRoleName($roleName)] ?? null;
    }

    /** @return array<string, array<string, string>> */
    public function toRoleArray(): array
    {
        $roles = [];
        foreach ($this->roles as $name => $role) {
            $roles[$name] = $role->toArray();
        }

        return $roles;
    }

    /**
     * Project the current role model back into the token map used by existing
     * Bootstrap and Page Builder CSS consumers.
     *
     * @param array<string, string> $tokens
     * @return array<string, string>
     */
    public function applyToTokens(array $tokens): array
    {
        foreach ($this->roles as $roleName => $role) {
            foreach ($role->fields() as $field => $value) {
                foreach (self::tokenKeysFor($roleName, $field) as $tokenKey) {
                    $tokens[$tokenKey] = $value;
                }
            }
        }

        return $tokens;
    }

    public static function normalizeRoleName(string $roleName): string
    {
        $candidate = trim(strtolower($roleName));
        if ($candidate === '') {
            throw new \InvalidArgumentException('Typography role names cannot be empty.');
        }

        $candidate = str_replace('-', '_', preg_replace('/\s+/', '_', $candidate) ?? $candidate);
        $canonical = self::ROLE_ALIASES[$candidate] ?? null;
        if ($canonical === null) {
            throw new \InvalidArgumentException("Typography role '{$roleName}' is not supported.");
        }

        return $canonical;
    }

    /**
     * @param array<string, TypographyRoleProfile> $roles
     * @return array<string, TypographyRoleProfile>
     */
    private static function orderedRoles(array $roles): array
    {
        $ordered = [];

        foreach (self::ROLE_ORDER as $role) {
            if (isset($roles[$role])) {
                $ordered[$role] = $roles[$role];
            }
        }

        return $ordered;
    }

    private static function tokenValue(array $tokens, string ...$keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $tokens) && trim((string) $tokens[$key]) !== '') {
                return (string) $tokens[$key];
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private static function tokenKeysFor(string $roleName, string $field): array
    {
        return match ($roleName) {
            'body' => match ($field) {
                'font_family' => ['--bs-body-font-family', '--bs-font-sans-serif'],
                'font_size' => ['--bs-body-font-size'],
                'font_weight' => ['--bs-body-font-weight'],
                'line_height' => ['--bs-body-line-height'],
                default => [],
            },
            'paragraph' => self::upbTokenKeys('paragraph', $field),
            'headings' => match ($field) {
                'font_family' => ['--bs-heading-font-family'],
                'font_weight' => ['--bs-heading-font-weight'],
                'line_height' => ['--bs-heading-line-height'],
                'color' => ['--bs-heading-color'],
                default => [],
            },
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => $field === 'font_size'
                ? ['--bs-heading-' . $roleName . '-font-size']
                : [],
            'buttons' => match ($field) {
                'font_family' => ['--bs-btn-font-family'],
                'font_size' => ['--bs-btn-font-size'],
                'font_weight' => ['--bs-btn-font-weight'],
                default => [],
            },
            'navigation' => self::upbTokenKeys('nav', $field),
            'blockquote' => self::upbTokenKeys('blockquote', $field),
            'code' => match ($field) {
                'font_family' => ['--bs-font-monospace'],
                'font_size', 'font_weight', 'line_height' => ['--upb-code-' . str_replace('_', '-', $field)],
                default => [],
            },
            'caption' => self::upbTokenKeys('small', $field),
            default => [],
        };
    }

    /**
     * @return list<string>
     */
    private static function upbTokenKeys(string $prefix, string $field): array
    {
        return match ($field) {
            'font_family',
            'font_size',
            'font_weight',
            'line_height',
            'font_style',
            'letter_spacing',
            'text_transform' => ['--upb-' . $prefix . '-' . str_replace('_', '-', $field)],
            default => [],
        };
    }
}
