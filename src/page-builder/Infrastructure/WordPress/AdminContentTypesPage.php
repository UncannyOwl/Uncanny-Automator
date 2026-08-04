<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Settings\InvalidContentTypeSelectionException;
use UncannyPageBuilder\Application\Settings\ListContentTypesUseCase;
use UncannyPageBuilder\Application\Settings\SaveContentTypeSettingsUseCase;

final class AdminContentTypesPage
{
    private const NONCE_ACTION = 'uncanny_page_builder_save_content_types';
    private const NONCE_FIELD = 'uncanny_page_builder_content_types_nonce';
    private const FIELD_CONTENT_TYPES = 'upb_content_types';
    private const FIELD_PRESENTED_CONTENT_TYPES = 'upb_presented_content_types';

    public function __construct(
        private readonly ListContentTypesUseCase $listContentTypes,
        private readonly SaveContentTypeSettingsUseCase $saveContentTypes,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
    ) {}

    // Section: Post type settings content

    public function render(): void
    {
        ['updated' => $updated, 'rejected' => $rejected] = $this->saveSubmittedSettings();
        $fieldName = self::FIELD_CONTENT_TYPES;
        $presentedFieldName = self::FIELD_PRESENTED_CONTENT_TYPES;
        $nonce = [
            'name' => self::NONCE_FIELD,
            'value' => wp_create_nonce(self::NONCE_ACTION),
        ];
        $contentTypes = array_map(
            static fn ($option): array => [
                'slug' => $option->slug(),
                'label' => $option->label(),
                'enabled' => $option->enabled(),
            ],
            ($this->listContentTypes)(),
        );

        include __DIR__ . '/../../Presentation/Settings/content-types-section.php';
    }

    /**
     * @return array{updated: bool, rejected: bool}
     */
    private function saveSubmittedSettings(): array
    {
        $postedNonce = $_POST[self::NONCE_FIELD] ?? null;
        if (!is_scalar($postedNonce)) {
            return ['updated' => false, 'rejected' => false];
        }

        $nonce = sanitize_text_field(wp_unslash((string) $postedNonce));
        if (
            $nonce === ''
            || !wp_verify_nonce($nonce, self::NONCE_ACTION)
            || !$this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            return ['updated' => false, 'rejected' => false];
        }

        $submittedValue = $_POST[self::FIELD_CONTENT_TYPES] ?? [];
        $presentedValue = $_POST[self::FIELD_PRESENTED_CONTENT_TYPES] ?? null;
        if (!is_array($submittedValue) || !is_array($presentedValue)) {
            return ['updated' => false, 'rejected' => true];
        }

        $submitted = self::enabledSlugs(wp_unslash($submittedValue));
        $presented = wp_unslash($presentedValue);

        try {
            ($this->saveContentTypes)(
                $submitted,
                array_values($presented),
            );
        } catch (InvalidContentTypeSelectionException) {
            return ['updated' => false, 'rejected' => true];
        }

        return ['updated' => true, 'rejected' => false];
    }

    /**
     * Reduces the posted post-type field to the list of enabled slugs.
     *
     * <uo-switch> posts every control keyed by slug, carrying "1" when on and
     * "0" when off. The earlier checkbox markup posted a list holding only the
     * enabled slugs, so integer-keyed entries are passed through as slugs and a
     * form cached from that markup keeps saving correctly.
     *
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    private static function enabledSlugs(array $values): array
    {
        $enabled = [];

        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $slug = sanitize_key(is_scalar($value) ? (string) $value : '');
                if ('' !== $slug) {
                    $enabled[] = $slug;
                }
                continue;
            }

            $on = in_array(
                sanitize_text_field(is_scalar($value) ? (string) $value : ''),
                ['1', 'on', 'true'],
                true,
            );

            if ($on) {
                $enabled[] = sanitize_key((string) $key);
            }
        }

        return array_values(array_unique($enabled));
    }
}
