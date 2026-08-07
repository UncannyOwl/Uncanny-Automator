<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\WordPress;

final class GlobalPartCpt
{
    public function register(): void
    {
        $defaultLabels = [
            'name'                  => _x('Reusable parts', 'Page Builder', 'uncanny-automator'),
            'singular_name'         => _x('Reusable part', 'Page Builder', 'uncanny-automator'),
            'menu_name'             => _x('Reusable parts', 'Page Builder', 'uncanny-automator'),
            'all_items'             => _x('Reusable parts', 'Page Builder', 'uncanny-automator'),
            'add_new'               => _x('Add new', 'Page Builder', 'uncanny-automator'),
            'add_new_item'          => _x('Add new reusable', 'Page Builder', 'uncanny-automator'),
            'edit_item'             => _x('Edit reusable', 'Page Builder', 'uncanny-automator'),
            'new_item'              => _x('New reusable', 'Page Builder', 'uncanny-automator'),
            'view_item'             => _x('View reusable', 'Page Builder', 'uncanny-automator'),
            'view_items'            => _x('View reusables', 'Page Builder', 'uncanny-automator'),
            'search_items'          => _x('Search reusables', 'Page Builder', 'uncanny-automator'),
            'not_found'             => _x('No reusables found', 'Page Builder', 'uncanny-automator'),
            'not_found_in_trash'    => _x('No reusables found in Trash', 'Page Builder', 'uncanny-automator'),
            'archives'              => _x('Reusable archives', 'Page Builder', 'uncanny-automator'),
            'attributes'            => _x('Reusable attributes', 'Page Builder', 'uncanny-automator'),
            'insert_into_item'      => _x('Insert into reusable', 'Page Builder', 'uncanny-automator'),
            'uploaded_to_this_item' => _x('Uploaded to this reusable', 'Page Builder', 'uncanny-automator'),
            'filter_items_list'     => _x('Filter reusables list', 'Page Builder', 'uncanny-automator'),
            'items_list_navigation' => _x('Reusables list navigation', 'Page Builder', 'uncanny-automator'),
            'items_list'            => _x('Reusables list', 'Page Builder', 'uncanny-automator'),
        ];
        $filteredLabels = apply_filters('uncanny_page_builder_global_parts_labels', $defaultLabels);
        $labels = is_array($filteredLabels) ? $filteredLabels : $defaultLabels;
        $menuCapability = PageBuilderAccessCapability::NAME;

        register_post_type('upb_global_part', [
            'labels'             => $labels,
            'public'             => false,
            'publicly_queryable' => false,
            'exclude_from_search' => true,
            'show_ui'            => true,
            'show_in_rest'       => false,
            'show_in_menu'       => 'uncanny-page-builder',
            'supports'           => ['title'],
            'capability_type'    => 'post',
            'map_meta_cap'       => false,
            'capabilities'       => [
                'edit_post'              => $menuCapability,
                'read_post'              => $menuCapability,
                'delete_post'            => $menuCapability,
                'edit_posts'             => $menuCapability,
                'edit_others_posts'      => $menuCapability,
                'delete_posts'           => $menuCapability,
                'publish_posts'          => $menuCapability,
                'read_private_posts'     => $menuCapability,
                'delete_private_posts'   => $menuCapability,
                'delete_published_posts' => $menuCapability,
                'delete_others_posts'    => $menuCapability,
                'edit_private_posts'     => $menuCapability,
                'edit_published_posts'   => $menuCapability,
                'create_posts'           => $menuCapability,
            ],
        ]);
    }
}
