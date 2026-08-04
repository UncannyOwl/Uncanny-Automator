<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

use UncannyPageBuilder\Application\Access\GetPageBuilderAllowedCapabilities;
use UncannyPageBuilder\Domain\Section\SectionRepositoryInterface;

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class AiPagesListTable extends \WP_List_Table
{
    private SectionRepositoryInterface $repository;
    private GetPageBuilderAllowedCapabilities $allowedCapabilities;

    /** @var array<int, int> page_id => section count, prefetched in prepare_items */
    private array $sectionCounts = [];

    public function __construct(
        SectionRepositoryInterface $repository,
        GetPageBuilderAllowedCapabilities $allowedCapabilities,
    ) {
        $this->repository = $repository;
        $this->allowedCapabilities = $allowedCapabilities;

        parent::__construct([
            'singular' => 'ai-page',
            'plural'   => 'ai-pages',
            'ajax'     => false,
        ]);
    }

    public function get_columns(): array
    {
        return [
            'cb'       => '<input type="checkbox" />',
            'title'    => _x('Title', 'Page Builder', 'uncanny-automator'),
            'sections' => _x('Sections', 'Page Builder', 'uncanny-automator'),
            'status'   => _x('Status', 'Page Builder', 'uncanny-automator'),
            'date'     => _x('Date', 'Page Builder', 'uncanny-automator'),
        ];
    }

    public function get_sortable_columns(): array
    {
        return [
            'title' => ['title', false],
            'date'  => ['date', true],
        ];
    }

    public function prepare_items(): void
    {
        $perPage     = 20;
        $currentPage = $this->get_pagenum();
        $orderby     = sanitize_text_field(wp_unslash($_GET['orderby'] ?? 'date')) ?: 'date';
        $order       = (strtolower($_GET['order'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
        $status      = sanitize_text_field($_GET['post_status'] ?? '');

        $queryArgs = [
            'post_type'      => 'page',
            'posts_per_page' => $perPage,
            'paged'          => $currentPage,
            'orderby'        => $orderby,
            'order'          => $order,
            'meta_key'       => '_uncanny_page_builder_owned',
            'meta_value'     => '1',
        ];

        if ($status === 'trash') {
            $queryArgs['post_status'] = 'trash';
        } elseif (in_array($status, ['publish', 'draft'], true)) {
            $queryArgs['post_status'] = $status;
        } else {
            $queryArgs['post_status'] = ['publish', 'draft'];
        }

        $query = new \WP_Query($queryArgs);

        $this->items = $query->posts;

        // Prefetch section counts in a single GROUP BY query to avoid N+1.
        $pageIds = array_map(static fn($p) => (int) $p->ID, $query->posts);
        $this->sectionCounts = $this->fetchSectionCounts($pageIds);

        $this->set_pagination_args([
            'total_items' => $query->found_posts,
            'per_page'    => $perPage,
            'total_pages' => $query->max_num_pages,
        ]);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    protected function get_views(): array
    {
        $baseUrl = admin_url('admin.php?page=uncanny-page-builder');
        $currentStatus = sanitize_text_field($_GET['post_status'] ?? '');

        global $wpdb;

        // Single query to count all statuses at once.
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.post_status, COUNT(*) AS cnt
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
                 WHERE p.post_type = 'page'
                   AND pm.meta_key = '_uncanny_page_builder_owned'
                   AND pm.meta_value = %s
                   AND p.post_status IN ('publish', 'draft', 'trash')
                 GROUP BY p.post_status",
                '1'
            )
        );

        $counts = ['all' => 0, 'publish' => 0, 'draft' => 0, 'trash' => 0];
        foreach ($rows as $row) {
            $counts[$row->post_status] = (int) $row->cnt;
        }
        $counts['all'] = $counts['publish'] + $counts['draft'];

        $views = [];

        $views['all'] = sprintf(
            '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
            esc_url($baseUrl),
            $currentStatus === '' ? ' class="current"' : '',
            esc_html_x('All', 'Page Builder', 'uncanny-automator'),
            $counts['all']
        );

        if ($counts['publish'] > 0) {
            $views['publish'] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('post_status', 'publish', $baseUrl)),
                $currentStatus === 'publish' ? ' class="current"' : '',
                esc_html_x('Published', 'Page Builder', 'uncanny-automator'),
                $counts['publish']
            );
        }

        if ($counts['draft'] > 0) {
            $views['draft'] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('post_status', 'draft', $baseUrl)),
                $currentStatus === 'draft' ? ' class="current"' : '',
                esc_html_x('Draft', 'Page Builder', 'uncanny-automator'),
                $counts['draft']
            );
        }

        if ($counts['trash'] > 0) {
            $views['trash'] = sprintf(
                '<a href="%s"%s>%s <span class="count">(%d)</span></a>',
                esc_url(add_query_arg('post_status', 'trash', $baseUrl)),
                $currentStatus === 'trash' ? ' class="current"' : '',
                esc_html_x('Trash', 'Page Builder', 'uncanny-automator'),
                $counts['trash']
            );
        }

        return $views;
    }

    public function column_cb($item): string
    {
        return sprintf('<input type="checkbox" name="page_ids[]" value="%d" />', $item->ID);
    }

    /**
     * Canvas editor URL — opens the canvas inside the Page Builder admin shell.
     */
    public static function frontendEditorUrl(int $postId): string
    {
        return AdminCanvasEditorWindowedPage::editorUrl($postId);
    }

    public function column_title($item): string
    {
        $title   = $item->post_title !== ''
            ? $item->post_title
            : sprintf(
                /* translators: %d is the WordPress page ID. */
                _x('Untitled page #%d', 'Page Builder', 'uncanny-automator'),
                (int) $item->ID
            );
        $isTrashed = $item->post_status === 'trash';

        if ($isTrashed) {
            $actions = [];

            $actions['untrash'] = sprintf(
                '<a href="%s">%s</a>',
                esc_url(wp_nonce_url(
                    admin_url('post.php?action=untrash&post=' . $item->ID),
                    'untrash-post_' . $item->ID
                )),
                esc_html_x('Restore', 'Page list row action', 'uncanny-automator')
            );

            if (current_user_can('delete_post', $item->ID)) {
                $actions['delete'] = sprintf(
                    '<a href="%s" class="submitdelete">%s</a>',
                    esc_url(get_delete_post_link($item->ID, '', true)),
                    esc_html_x('Delete permanently', 'Page list row action', 'uncanny-automator')
                );
            }

            return sprintf(
                '<strong>%s</strong>%s',
                esc_html($title),
                $this->row_actions($actions)
            );
        }

        $canvasUrl = self::frontendEditorUrl($item->ID);
        $viewUrl   = get_permalink($item->ID);
        $settingsUrl = get_edit_post_link($item->ID);

        // Page list actions
        $actions = [
            'edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($canvasUrl),
                esc_html_x('Edit', 'Page list row action', 'uncanny-automator')
            ),
            'wp_edit' => sprintf(
                '<a href="%s">%s</a>',
                esc_url($settingsUrl),
                esc_html_x('Settings', 'Page list row action', 'uncanny-automator')
            ),
        ];

        if (current_user_can('delete_post', $item->ID)) {
            $actions['trash'] = sprintf(
                '<a href="%s" class="submitdelete">%s</a>',
                esc_url(get_delete_post_link($item->ID)),
                esc_html_x('Trash', 'Page list row action', 'uncanny-automator')
            );
        }

        $actions['view'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($viewUrl),
            esc_html_x('View', 'Page list row action', 'uncanny-automator')
        );

        return sprintf(
            '<strong><a class="row-title" href="%s">%s</a></strong>%s',
            esc_url($canvasUrl),
            esc_html($title),
            $this->row_actions($actions)
        );
    }

    public function column_sections($item): string
    {
        $count = $this->sectionCounts[(int) $item->ID] ?? 0;

        return $count > 0
            ? sprintf('<span class="count">%d</span>', $count)
            : '<span class="dashicons dashicons-minus" style="color:#a7aaad;"></span>';
    }

    /**
     * Single GROUP BY query returning section counts keyed by page_id.
     *
     * @param int[] $pageIds
     * @return array<int, int>
     */
    private function fetchSectionCounts(array $pageIds): array
    {
        if (empty($pageIds)) {
            return [];
        }

        global $wpdb;
        $table       = \UncannyPageBuilder\Infrastructure\Persistence\SchemaManager::tableName();
        $placeholders = implode(',', array_fill(0, count($pageIds), '%d'));

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT page_id, COUNT(*) AS cnt FROM {$table} WHERE page_id IN ({$placeholders}) GROUP BY page_id",
                ...$pageIds
            )
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->page_id] = (int) $row->cnt;
        }

        return $counts;
    }

    public function column_status($item): string
    {
        $status = get_post_status_object($item->post_status);
        return $status ? esc_html($status->label) : esc_html($item->post_status);
    }

    public function column_date($item): string
    {
        $date = get_the_date('', $item);
        $time = get_the_time('', $item);
        return esc_html($date) . '<br><small>' . esc_html($time) . '</small>';
    }

    protected function get_bulk_actions(): array
    {
        $currentStatus = sanitize_text_field($_GET['post_status'] ?? '');

        if ($currentStatus === 'trash') {
            return [
                'untrash' => _x('Restore', 'Page Builder', 'uncanny-automator'),
                'delete'  => _x('Delete permanently', 'Page Builder', 'uncanny-automator'),
            ];
        }

        return [
            'trash' => _x('Move to trash', 'Page Builder', 'uncanny-automator'),
        ];
    }

    public function process_bulk_action(): void
    {
        $action = $this->current_action();

        if (!in_array($action, ['trash', 'untrash', 'delete'], true)) {
            return;
        }

        if (
            !isset($_REQUEST['_wpnonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), 'bulk-ai-pages')
        ) {
            return;
        }

        $pageIds = array_map('absint', (array) ($_REQUEST['page_ids'] ?? []));

        foreach ($pageIds as $pageId) {
            if ($pageId === 0 || !current_user_can('delete_post', $pageId)) {
                continue;
            }

            if ($action === 'trash') {
                wp_trash_post($pageId);
            } elseif ($action === 'untrash') {
                wp_untrash_post($pageId);
            } elseif ($action === 'delete') {
                wp_delete_post($pageId, true);
            }
        }
    }

    public function no_items(): void
    {
        if (!$this->allowedCapabilities->currentUserHasAllowedCapability()) {
            echo esc_html_x('No pages yet.', 'Page Builder', 'uncanny-automator');
            return;
        }

        $createUrl = wp_nonce_url(
            admin_url('admin-post.php?action=uncanny_page_builder_create_page'),
            'uncanny_page_builder_create_page'
        );

        printf(
            '%s <a href="%s">%s</a>',
            esc_html_x('No pages yet.', 'Page Builder', 'uncanny-automator'),
            esc_url($createUrl),
            esc_html_x('Create your first page', 'Page Builder', 'uncanny-automator')
        );
    }
}
