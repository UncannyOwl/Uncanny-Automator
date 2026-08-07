<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Kernel\Providers\WordPress\Admin;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Application\Concurrency\PageSourceMutation;
use UncannyPageBuilder\Application\ContentType\SupportsPostTypeUseCase;
use UncannyPageBuilder\Application\Controls\Handlers\ManualChangeSetHandler;
use UncannyPageBuilder\Application\DesignStandardsService;
use UncannyPageBuilder\Application\Editor\SelectEditorPageSource;
use UncannyPageBuilder\Application\GetAvailableFontFamilies;
use UncannyPageBuilder\Application\GlobalPartDefaultsService;
use UncannyPageBuilder\Application\PageGlobalPartSelectionService;
use UncannyPageBuilder\Domain\Publishing\PageStateRepositoryInterface;
use UncannyPageBuilder\Domain\Concurrency\SourceGenerationStoreInterface;
use UncannyPageBuilder\Infrastructure\Persistence\DatabaseSectionRepository;
use UncannyPageBuilder\Infrastructure\WordPress\DesignStandardsMetaBox;
use UncannyPageBuilder\Infrastructure\WordPress\GlobalPartSelectorMetaBox;
use UncannyPageBuilder\Infrastructure\WordPress\NativePageSave;
use UncannyPageBuilder\Infrastructure\WordPress\SectionOrderMetaBox;
use UncannyPageBuilder\Infrastructure\WordPress\WordPressPostId;
use UncannyPageBuilder\Kernel\Container;
use UncannyPageBuilder\Kernel\Contracts\ServiceProviderInterface;

final class PageEditorEnhancementProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->factory(DesignStandardsMetaBox::class, static function (Container $c): DesignStandardsMetaBox {
            return new DesignStandardsMetaBox(
                $c->typed(DesignStandardsService::class),
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(GetAvailableFontFamilies::class),
                $c->typed(SupportsPostTypeUseCase::class),
                $c->typed(PageSourceMutation::class),
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(SelectEditorPageSource::class),
                $c->typed(NativePageSave::class),
            );
        });

        $container->factory(SectionOrderMetaBox::class, static function (Container $c): SectionOrderMetaBox {
            return new SectionOrderMetaBox(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(ManualChangeSetHandler::class),
                $c->typed(SourceGenerationStoreInterface::class),
                $c->typed(NativePageSave::class),
            );
        });

        $container->factory(GlobalPartSelectorMetaBox::class, static function (Container $c): GlobalPartSelectorMetaBox {
            return new GlobalPartSelectorMetaBox(
                $c->typed(DatabaseSectionRepository::class),
                $c->typed(GlobalPartDefaultsService::class),
                $c->typed(GetPageBuilderAllowedCapabilities::class),
                $c->typed(PageGlobalPartSelectionService::class),
                $c->typed(PageSourceMutation::class),
                $c->typed(PageStateRepositoryInterface::class),
                $c->typed(SelectEditorPageSource::class),
                $c->typed(NativePageSave::class),
            );
        });
    }

    public function boot(Container $container): void
    {
        $dsMetaBox           = $container->typed(DesignStandardsMetaBox::class);
        $sectionOrderMetaBox = $container->typed(SectionOrderMetaBox::class);
        $gpSelectorMetaBox   = $container->typed(GlobalPartSelectorMetaBox::class);
        $sectionRepo         = $container->typed(DatabaseSectionRepository::class);
        $allowedCapabilities = $container->typed(GetPageBuilderAllowedCapabilities::class);
        $supportsPostType    = $container->typed(SupportsPostTypeUseCase::class);
        $pageSources = $container->has(SelectEditorPageSource::class)
            ? $container->typed(SelectEditorPageSource::class)
            : null;
        $canEditWorkingSource = static function (int $pageId) use ($pageSources): bool {
            static $allowedByPage = [];
            if (array_key_exists($pageId, $allowedByPage)) {
                return $allowedByPage[$pageId];
            }

            return $allowedByPage[$pageId] = !$pageSources instanceof SelectEditorPageSource
                || $pageSources->forPage($pageId)->loadedSource() === 'working';
        };

        // 10. Design Standards page overrides meta box
        add_action('add_meta_boxes', static function ($postType = null, $post = null) use ($supportsPostType, $dsMetaBox, $canEditWorkingSource): void {
            if (
                is_string($postType)
                && $post instanceof \WP_Post
                && $supportsPostType->isSupported($postType)
                && $canEditWorkingSource($post->ID)
            ) {
                $dsMetaBox->register($post);
            }
        }, 10, 2);
        add_action('save_post', static function ($postId = null, $post = null) use ($supportsPostType, $dsMetaBox): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null || !$post instanceof \WP_Post) {
                return;
            }

            if ($supportsPostType->isSupported($post->post_type)) {
                $dsMetaBox->save($postId, $post);
            }
        }, 10, 2);

        // 10b-gp. Header & Footer selector meta box
        add_action('add_meta_boxes', static function ($postType = null, $post = null) use ($supportsPostType, $gpSelectorMetaBox, $canEditWorkingSource): void {
            if (
                is_string($postType)
                && $post instanceof \WP_Post
                && $supportsPostType->isSupported($postType)
                && $canEditWorkingSource($post->ID)
            ) {
                $gpSelectorMetaBox->register($post);
            }
        }, 10, 2);
        add_action('save_post', static function ($postId = null, $post = null) use ($supportsPostType, $gpSelectorMetaBox): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null || !$post instanceof \WP_Post) {
                return;
            }

            if ($supportsPostType->isSupported($post->post_type)) {
                $gpSelectorMetaBox->save($postId, $post);
            }
        }, 10, 2);

        // 10c. Section order meta box (drag-and-drop reordering)
        add_action('add_meta_boxes', static function ($postType = null, $post = null) use ($supportsPostType, $sectionOrderMetaBox, $canEditWorkingSource): void {
            if (
                is_string($postType)
                && $post instanceof \WP_Post
                && $supportsPostType->isSupported($postType)
                && $canEditWorkingSource($post->ID)
            ) {
                $sectionOrderMetaBox->register($post);
            }
        }, 10, 2);
        add_action('save_post', static function ($postId = null, $post = null) use ($supportsPostType, $sectionOrderMetaBox): void {
            $postId = WordPressPostId::fromMixed($postId);
            if ($postId === null || !$post instanceof \WP_Post) {
                return;
            }

            if ($supportsPostType->isSupported($post->post_type)) {
                $sectionOrderMetaBox->save($postId, $post);
            }
        }, 5, 2);
        add_action('admin_notices', [$sectionOrderMetaBox, 'renderSaveNotice']);

        add_action('admin_enqueue_scripts', static function ($hook = null) use ($sectionRepo, $allowedCapabilities, $supportsPostType, $canEditWorkingSource): void {
            if (!is_string($hook) || ($hook !== 'post.php' && $hook !== 'post-new.php')) {
                return;
            }
            $postId = absint($_GET['post'] ?? 0);
            $post = $postId > 0 ? get_post($postId) : null;
            if (
                $postId > 0
                && $post instanceof \WP_Post
                && $supportsPostType->isSupported($post->post_type)
                && $sectionRepo->isOwnedPage($postId)
                && $allowedCapabilities->currentUserHasAllowedCapability()
                && $canEditWorkingSource($postId)
            ) {
                wp_enqueue_script('jquery-ui-sortable');
            }
        });

        // Page-level style overrides are rendered by the shared page editor
        // admin bundle enqueued by EditorEnvironmentProvider.
    }
}
