<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Application\Editor;

use UncannyPageBuilder\Application\Controls\PageDetailsPortInterface;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\PageGlobalPartSelectionService;
use UncannyPageBuilder\Application\SectionService;
use UncannyPageBuilder\Application\ShellModeService;
use UncannyPageBuilder\Domain\DesignStandards\DesignStandardsProfile;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartRepositoryInterface;
use UncannyPageBuilder\Domain\GlobalPart\GlobalPartType;
use UncannyPageBuilder\Domain\Shell\ShellMode;
use UncannyPageBuilder\Domain\Shell\ShellProvider;

final class EditorStateService
{
    private const SCHEMA_VERSION = 'editor-state.v1';

    public function __construct(
        private readonly SectionService $sectionService,
        private readonly GlobalPartRepositoryInterface $globalPartRepository,
        private readonly ShellModeService $shellModeService,
        private readonly GlobalPartDefaultsService $globalPartDefaultsService,
        private readonly DesignStandardsService $designStandardsService,
        private readonly ?PageDetailsPortInterface $pageDetails = null,
        private readonly ?SelectEditorPageSource $pageSources = null,
        private readonly ?PageGlobalPartSelectionService $pageGlobalParts = null,
        private readonly ?PublishedSourceSnapshotMigrationInterface $sourceSnapshotMigration = null,
    ) {}

    /** @param array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool} $capabilities */
    public function buildForPage(int $pageId, array $capabilities): EditorState
    {
        $this->sourceSnapshotMigration?->migrateIfSafe($pageId);
        $sourceSelection = $this->pageSources?->forPage($pageId);
        $publishedSource = $sourceSelection?->loadedSource() === 'published'
            ? $sourceSelection->publishedSnapshot()?->source()
            : null;
        $layout = is_array($publishedSource)
            ? ['sections' => $publishedSource['sections'] ?? []]
            : $this->sectionService->getLayout($pageId);
        $capabilitiesMap = is_array($publishedSource)
            ? $this->sectionService->buildEditableCapabilitiesMapForSource(
                $pageId,
                is_array($publishedSource['sections'] ?? null) ? $publishedSource['sections'] : [],
            )
            : $this->sectionService->buildEditableCapabilitiesMap($pageId);
        $workingShellMode = $this->shellModeService->resolveForPage($pageId);
        $snapshotShellMode = is_array($publishedSource)
            ? ShellMode::tryFrom((string) ($publishedSource['shell_mode'] ?? ''))
            : null;
        $shellMode = $snapshotShellMode ?? $workingShellMode->mode;
        $shellModeExplicit = is_array($publishedSource)
            ? (bool) ($publishedSource['shell_mode_explicit'] ?? false)
            : $workingShellMode->isExplicit;
        $workingPartSelection = $this->pageGlobalParts?->selectionForPage($pageId);
        $headerOverrideId = is_array($publishedSource)
            ? $this->partOverrideId($publishedSource['header_override_id'] ?? null)
            : $workingPartSelection?->headerOverrideId();
        $footerOverrideId = is_array($publishedSource)
            ? $this->partOverrideId($publishedSource['footer_override_id'] ?? null)
            : $workingPartSelection?->footerOverrideId();
        $provider = $this->shellModeService->detectProviderForPage($pageId);
        $details = $this->pageDetails?->find($pageId);
        $title = is_array($publishedSource)
            ? (string) ($publishedSource['title'] ?? '')
            : ($details?->title() ?? $this->resolvePostTitle($pageId, true));
        $slug = is_array($publishedSource)
            ? (string) ($publishedSource['slug'] ?? '')
            : ($details?->slug() ?? '');

        return new EditorState([
            'schema_version'   => self::SCHEMA_VERSION,
            'context'          => EditorContext::forPage($pageId)->toArray(),
            'capabilities'     => $capabilities,
            'page'             => [
                'id'                     => $pageId,
                'title'                  => $title,
                'slug'                   => $slug,
                'status'                 => $this->resolvePostStatus($pageId),
                'permalink'              => $details?->permalink() ?? $this->resolvePermalink($pageId),
                'preview_url'            => $details?->previewUrl() ?? $this->resolvePermalink($pageId),
                'permalink_is_live'      => $details?->permalinkIsLive() ?? true,
                'dashboard_url'          => $this->dashboardUrl(),
                'new_canvas_url'         => $this->newCanvasUrl(),
                'shell_mode'             => $shellMode->value,
                'shell_mode_label'       => $shellMode->label(),
                'is_shell_mode_explicit' => $shellModeExplicit,
                'header_override_id'     => $headerOverrideId,
                'footer_override_id'     => $footerOverrideId,
            ],
            'source'           => $sourceSelection?->toArray() ?? [
                'loaded_source' => 'working',
                'working_generation' => 0,
                'loaded_working_generation' => null,
                'loaded_snapshot_id' => null,
                'published_snapshot_id' => null,
                'working_draft_newer' => false,
                'draft_resume_policy' => 'active',
                'offer_parked_draft' => false,
            ],
            'global_part'      => null,
            'sections'         => $this->sectionMetadataFromLayout($layout['sections'] ?? [], $capabilitiesMap),
            'design_standards' => $this->designStandardsSummary($this->designStandardsService->resolve()),
            'chrome'           => $this->chrome($pageId, $provider),
        ]);
    }

    private function partOverrideId(mixed $value): ?int
    {
        return is_int($value) && ($value === -1 || $value > 0) ? $value : null;
    }

    /** @param array{can_edit: bool, can_manage: bool, can_upload: bool, can_publish?: bool} $capabilities */
    public function buildForGlobalPart(int $globalPartId, array $capabilities): ?EditorState
    {
        $part = $this->globalPartRepository->findById($globalPartId);
        if ($part === null) {
            return null;
        }

        return new EditorState([
            'schema_version'   => self::SCHEMA_VERSION,
            'context'          => EditorContext::forGlobalPart($globalPartId)->toArray(),
            'capabilities'     => $capabilities,
            'page'             => null,
            'source'           => null,
            'global_part'      => [
                'id'               => $globalPartId,
                'title'            => (string) ($part['title'] ?? $this->resolvePostTitle($globalPartId, false)),
                'type'             => (string) ($part['type'] ?? GlobalPartType::Section->value),
                'status'           => $this->resolvePostStatus($globalPartId),
                'dashboard_url'    => $this->dashboardUrl(),
                'shell_mode'       => ShellMode::UncannyNative->value,
                'shell_mode_label' => ShellMode::UncannyNative->label(),
            ],
            'sections'         => $this->sectionMetadataFromGlobalPart($part['sections'] ?? []),
            'design_standards' => $this->designStandardsSummary($this->designStandardsService->resolve()),
            'chrome'           => $this->globalPartChrome(),
        ]);
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @param array<int, array<int, array<string, mixed>>> $capabilitiesMap
     * @return array<int, array<string, mixed>>
     */
    private function sectionMetadataFromLayout(array $sections, array $capabilitiesMap): array
    {
        $metadata = [];

        foreach ($sections as $section) {
            $sectionId = (int) ($section['id'] ?? 0);
            $metadata[] = [
                'id'                    => $sectionId,
                'position'              => (int) ($section['position'] ?? 0),
                'name'                  => (string) ($section['name'] ?? ''),
                'editable_capabilities' => $capabilitiesMap[$sectionId] ?? [],
            ];
        }

        return $metadata;
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array<string, mixed>>
     */
    private function sectionMetadataFromGlobalPart(array $sections): array
    {
        $metadata = [];

        foreach ($sections as $position => $section) {
            $metadata[] = [
                'id'                    => (int) ($section['id'] ?? 0),
                'position'              => (int) ($section['position'] ?? $position),
                'name'                  => (string) ($section['name'] ?? ''),
                'editable_capabilities' => [],
            ];
        }

        return $metadata;
    }

    /** @return array{schema_version: string, summary: array<string, int>} */
    private function designStandardsSummary(DesignStandardsProfile $profile): array
    {
        $lockedKeys = $profile->lockedKeys();

        return [
            'schema_version' => (string) ($profile->toArray()['schema_version'] ?? '2.0'),
            'summary'        => [
                'token_count' => count($profile->tokens()->toArray()),
                'breakpoint_count' => count($profile->breakpoints()->toArray()),
                'locked_token_count' => count($lockedKeys['tokens'] ?? []),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function chrome(int $pageId, ShellProvider $provider): array
    {
        return [
            'has_default_header'  => $this->hasGlobalPart(GlobalPartType::Header),
            'has_default_footer'  => $this->hasGlobalPart(GlobalPartType::Footer),
            'theme_name'          => $this->themeName(),
            'shell_provider'      => $provider->value,
            'shell_provider_label' => $provider->label(),
            'site_name'           => $this->siteName(),
            'page_id'             => $pageId,
        ];
    }

    /** @return array<string, mixed> */
    private function globalPartChrome(): array
    {
        return [
            'has_default_header'  => false,
            'has_default_footer'  => false,
            'theme_name'          => $this->themeName(),
            'shell_provider'      => ShellProvider::Uncanny->value,
            'shell_provider_label' => ShellProvider::Uncanny->label(),
            'site_name'           => $this->siteName(),
            'page_id'             => 0,
        ];
    }

    private function hasGlobalPart(GlobalPartType $type): bool
    {
        if ($this->globalPartDefaultsService->resolveForType($type) !== null) {
            return true;
        }

        return $this->globalPartDefaultsService->listByType($type) !== [];
    }

    private function resolvePostTitle(int $postId, bool $isPage): string
    {
        $title = get_the_title($postId);
        if (!is_string($title) || $title === '') {
            return $isPage
                ? sprintf(
                    /* translators: %d is the WordPress page/post ID. */
                    _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
                    $postId,
                )
                : 'Untitled';
        }

        // Editor state is transport data, not HTML. WordPress title helpers may
        // return display entities, which React would otherwise show literally.
        return html_entity_decode($title, ENT_QUOTES, 'UTF-8');
    }

    private function resolvePostStatus(int $postId): string
    {
        $status = get_post_status($postId);
        return is_string($status) && $status !== '' ? $status : 'draft';
    }

    private function resolvePermalink(int $postId): string
    {
        $permalink = get_permalink($postId);
        return is_string($permalink) ? $permalink : '';
    }

    private function dashboardUrl(): string
    {
        return admin_url('admin.php?page=uncanny-page-builder');
    }

    private function newCanvasUrl(): string
    {
        $url = admin_url('admin-post.php?action=uncanny_page_builder_create_page');

        // Editor state is JSON, not rendered HTML. Keep the nonce URL raw so
        // clients receive & instead of an HTML entity such as &amp;.
        return $url
            . (str_contains($url, '?') ? '&' : '?')
            . '_wpnonce='
            . rawurlencode((string) wp_create_nonce('uncanny_page_builder_create_page'));
    }

    private function siteName(): string
    {
        return (string) get_bloginfo('name');
    }

    private function themeName(): string
    {
        $theme = wp_get_theme();

        return (string) $theme->get('Name');
    }
}
