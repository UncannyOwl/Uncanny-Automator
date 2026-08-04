<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;

final class AdminSettingsPage
{
    private const SELECTION_CLEAR = 'clear';
    private const SELECTION_SELECTED = 'selected';

    public function __construct(
        private readonly GlobalPartDefaultsService $globalPartDefaultsService,
        private readonly GetPageBuilderAllowedCapabilities $allowedCapabilities,
    ) {}

    public function renderPlainLayoutSettingsContent(): void
    {
        [
            'globalPartsUpdated' => $globalPartsUpdated,
            'globalPartsRejected' => $globalPartsRejected,
            'currentHeaderId' => $currentHeaderId,
            'currentFooterId' => $currentFooterId,
            'headerOptions' => $headerOptions,
            'footerOptions' => $footerOptions,
        ] = $this->layoutSettingsViewData();

        include __DIR__ . '/../../Presentation/Settings/layout-settings-form.php';
    }

    /**
     * @return array{
     *     globalPartsUpdated: bool,
     *     globalPartsRejected: bool,
     *     currentHeaderId: ?int,
     *     currentFooterId: ?int,
     *     headerOptions: array<int, array{id: int, title: string}>,
     *     footerOptions: array<int, array{id: int, title: string}>
     * }
     */
    private function layoutSettingsViewData(): array
    {
        $globalPartsUpdated  = false;
        $globalPartsRejected = false;

        // Keep the duplicate settings page on the same guarded write path as
        // the existing layout page, so the new shell cannot drift during rollout.
        if (
            isset($_POST['uncanny_page_builder_settings_nonce'])
            && wp_verify_nonce(
                sanitize_text_field(wp_unslash($_POST['uncanny_page_builder_settings_nonce'])),
                'uncanny_page_builder_save_settings'
            )
            && $this->allowedCapabilities->currentUserHasAllowedCapability()
        ) {
            $headerSelection = $this->parseSelection($_POST['default_page_header'] ?? null);
            $footerSelection = $this->parseSelection($_POST['default_page_footer'] ?? null);

            if (
                $headerSelection !== null
                && $footerSelection !== null
                && $this->selectionCanBeSaved(GlobalPartType::Header, $headerSelection)
                && $this->selectionCanBeSaved(GlobalPartType::Footer, $footerSelection)
            ) {
                $headerAccepted = $this->globalPartDefaultsService->setDefaultId(
                    GlobalPartType::Header,
                    $this->selectionPostId($headerSelection)
                );
                $footerAccepted = $this->globalPartDefaultsService->setDefaultId(
                    GlobalPartType::Footer,
                    $this->selectionPostId($footerSelection)
                );

                $globalPartsUpdated  = $headerAccepted && $footerAccepted;
                $globalPartsRejected = !$globalPartsUpdated;
            } else {
                $globalPartsRejected = true;
            }
        }

        return [
            'globalPartsUpdated' => $globalPartsUpdated,
            'globalPartsRejected' => $globalPartsRejected,
            'currentHeaderId' => $this->globalPartDefaultsService->getDefaultId(GlobalPartType::Header),
            'currentFooterId' => $this->globalPartDefaultsService->getDefaultId(GlobalPartType::Footer),
            'headerOptions' => $this->loadOptions(GlobalPartType::Header),
            'footerOptions' => $this->loadOptions(GlobalPartType::Footer),
        ];
    }

    /**
     * Tri-state parser for the settings form.
     *
     * Empty string is the explicit "clear this default" command from the React
     * form. Missing or malformed values are invalid and must reject the whole
     * submission instead of silently deleting the current assignment.
     *
     * @return array{status: string, post_id: ?int}|null
     */
    private function parseSelection(mixed $value): ?array
    {
        if ($value === '') {
            return ['status' => self::SELECTION_CLEAR, 'post_id' => null];
        }

        if ($value === null || !is_scalar($value) || !preg_match('/^\d+$/', (string) $value)) {
            return null;
        }

        $postId = (int) $value;

        if ($postId <= 0) {
            return null;
        }

        return ['status' => self::SELECTION_SELECTED, 'post_id' => $postId];
    }

    /**
     * @param array{status: string, post_id: ?int} $selection
     */
    private function selectionPostId(array $selection): ?int
    {
        return $selection['status'] === self::SELECTION_SELECTED
            ? $selection['post_id']
            : null;
    }

    /**
     * Validate both form fields before any write so one stale selection cannot
     * partially apply the other field.
     *
     * @param array{status: string, post_id: ?int} $selection
     */
    private function selectionCanBeSaved(GlobalPartType $type, array $selection): bool
    {
        if ($selection['status'] === self::SELECTION_CLEAR) {
            return true;
        }

        $postId = $selection['post_id'];

        return is_int($postId) && $this->globalPartDefaultsService->isAssignablePartId($type, $postId);
    }

    /**
     * @return array<int, array{id: int, title: string}>
     */
    private function loadOptions(GlobalPartType $type): array
    {
        return array_map(
            static fn(array $part): array => [
                'id'    => (int) $part['post_id'],
                'title' => $part['title'] !== '' ? $part['title'] : sprintf(
                    /* translators: %d: WordPress reusable post ID. */
                    _x('Untitled #%d', 'Page Builder', 'uncanny-automator'),
                    $part['post_id'],
                ),
            ],
            $this->globalPartDefaultsService->listByType($type)
        );
    }
}
