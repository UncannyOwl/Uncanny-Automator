<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Persistence;

final class SchemaManager
{
    private const OPTION_KEY = 'uncanny_page_builder_db_version';
    // 2.4.1 verifies immutable editable-source JSON with a canonical hash.
    private const DB_VERSION = '2.4.1';

    public static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'upb_sections';
    }

    public static function globalSectionsTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'upb_global_sections';
    }

    public static function operationsTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'upb_operations';
    }

    public static function pageStateTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'upb_page_state';
    }

    public static function pageArtifactsTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'upb_page_artifacts';
    }

    public static function pageSourceSnapshotsTableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'upb_page_source_snapshots';
    }

    public static function install(bool $networkWide = false): void
    {
        if (
            $networkWide
            && function_exists('is_multisite')
            && is_multisite()
            && function_exists('get_sites')
            && function_exists('switch_to_blog')
            && function_exists('restore_current_blog')
        ) {
            $sites = get_sites(['fields' => 'ids', 'number' => 0]);
            if (is_array($sites)) {
                foreach ($sites as $siteId) {
                    switch_to_blog((int) $siteId);
                    try {
                        self::installForCurrentSite();
                    } finally {
                        restore_current_blog();
                    }
                }
                return;
            }
        }

        self::installForCurrentSite();
    }

    private static function installForCurrentSite(): void
    {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        $sectionsTable = self::tableName();
        $sql = "CREATE TABLE {$sectionsTable} (
            id          bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id     bigint(20) unsigned NOT NULL,
            position    int unsigned NOT NULL DEFAULT 0,
            name        varchar(255) NOT NULL DEFAULT '',
            html        longtext NOT NULL,
            css         longtext NOT NULL,
            element_styles longtext NULL,
            created_at  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_id_position (page_id, position)
        ) ENGINE=InnoDB {$charset};";

        $globalSectionsTable = self::globalSectionsTableName();
        $sql .= "CREATE TABLE {$globalSectionsTable} (
            id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            global_part_id  bigint(20) unsigned NOT NULL,
            position        int unsigned NOT NULL DEFAULT 0,
            name            varchar(255) NOT NULL DEFAULT '',
            html            longtext NOT NULL,
            css             longtext NOT NULL,
            element_styles  longtext NULL,
            created_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY global_part_id_position (global_part_id, position)
        ) ENGINE=InnoDB {$charset};";

        $operationsTable = self::operationsTableName();
        $sql .= "CREATE TABLE {$operationsTable} (
            id              bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            scope_type      varchar(32) NOT NULL,
            scope_id        bigint(20) unsigned NOT NULL,
            actor_user_id   bigint(20) unsigned NOT NULL,
            operation       varchar(128) NOT NULL,
            label           varchar(255) NOT NULL DEFAULT '',
            before_payload  longtext NOT NULL,
            after_payload   longtext NOT NULL,
            created_at      datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            undone_at       datetime NULL,
            PRIMARY KEY (id),
            KEY scope_created (scope_type, scope_id, created_at),
            KEY scope_undone (scope_type, scope_id, undone_at),
            KEY scope_active (scope_type, scope_id, undone_at, id)
        ) ENGINE=InnoDB {$charset};";

        $pageStateTable = self::pageStateTableName();
        $sql .= "CREATE TABLE {$pageStateTable} (
            page_id               bigint(20) unsigned NOT NULL,
            draft_title           text NOT NULL,
            draft_slug            varchar(200) NOT NULL DEFAULT '',
            published_artifact_id bigint(20) unsigned NULL,
            published_source_snapshot_id bigint(20) unsigned NULL,
            draft_resume_policy   varchar(16) NOT NULL DEFAULT 'active',
            updated_by            bigint(20) unsigned NOT NULL DEFAULT 0,
            updated_at            datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            published_by          bigint(20) unsigned NULL,
            published_at          datetime NULL,
            PRIMARY KEY (page_id),
            KEY published_artifact (published_artifact_id),
            KEY published_source_snapshot (published_source_snapshot_id)
        ) ENGINE=InnoDB {$charset};";

        $snapshotsTable = self::pageSourceSnapshotsTableName();
        $sql .= "CREATE TABLE {$snapshotsTable} (
            id                   bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id              bigint(20) unsigned NOT NULL,
            snapshot_version     int unsigned NOT NULL DEFAULT 1,
            source_revision_hash varchar(128) NOT NULL,
            source_content_hash  char(64) NULL,
            page_generation      bigint(20) unsigned NOT NULL DEFAULT 0,
            source_json          longtext NOT NULL,
            created_by           bigint(20) unsigned NOT NULL,
            created_at           datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_created (page_id, created_at, id),
            KEY source_revision (page_id, source_revision_hash)
        ) ENGINE=InnoDB {$charset};";

        $artifactsTable = self::pageArtifactsTableName();
        $sql .= "CREATE TABLE {$artifactsTable} (
            id                        bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            page_id                   bigint(20) unsigned NOT NULL,
            artifact_version          int unsigned NOT NULL DEFAULT 1,
            source_snapshot_id        bigint(20) unsigned NULL,
            source_revision_hash      varchar(128) NOT NULL,
            content_hash              char(64) NOT NULL,
            dependency_hash           char(64) NOT NULL,
            dependencies_json         longtext NOT NULL,
            page_section_count        int unsigned NOT NULL,
            title                     text NOT NULL,
            slug                      varchar(200) NOT NULL,
            shell_mode                varchar(32) NOT NULL,
            html                      longtext NOT NULL,
            css                       longtext NOT NULL,
            custom_javascript         longtext NOT NULL,
            assets_manifest_json      longtext NOT NULL,
            static_safety_status      varchar(32) NOT NULL,
            static_safety_report_json longtext NULL,
            created_by                bigint(20) unsigned NOT NULL,
            created_at                datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY page_created (page_id, created_at, id),
            KEY source_snapshot (source_snapshot_id),
            KEY dependency_hash (dependency_hash),
            KEY content_hash (content_hash)
        ) ENGINE=InnoDB {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        self::ensureTransactionalEngines();

        // dbDelta reports many failures only through wpdb and may return an
        // empty result even when a CREATE/ALTER did not complete. Never mark a
        // migration complete until the schema needed by runtime repositories
        // can be read back from the database.
        if (
            self::hasRequiredSchema()
            && self::initializeUnpublishedPageStates()
            && self::purgeOrphanedPageSourceRows()
        ) {
            update_option(self::OPTION_KEY, self::DB_VERSION);
        }
    }

    public static function ensureSchema(): void
    {
        $installed = get_option(self::OPTION_KEY, '');
        if ($installed !== self::DB_VERSION) {
            self::install();
        }
    }

    private static function hasRequiredSchema(): bool
    {
        global $wpdb;

        $requiredColumns = [
            self::tableName() => [
                'id', 'page_id', 'position', 'name', 'html', 'css',
                'element_styles', 'created_at', 'updated_at',
            ],
            self::globalSectionsTableName() => [
                'id', 'global_part_id', 'position', 'name', 'html', 'css',
                'element_styles', 'created_at', 'updated_at',
            ],
            self::operationsTableName() => [
                'id', 'scope_type', 'scope_id', 'actor_user_id', 'operation', 'label',
                'before_payload', 'after_payload', 'created_at', 'undone_at',
            ],
            self::pageStateTableName() => [
                'page_id', 'draft_title', 'draft_slug', 'published_artifact_id',
                'published_source_snapshot_id', 'draft_resume_policy',
                'updated_by', 'updated_at', 'published_by', 'published_at',
            ],
            self::pageSourceSnapshotsTableName() => [
                'id', 'page_id', 'snapshot_version', 'source_revision_hash',
                'source_content_hash', 'page_generation', 'source_json', 'created_by', 'created_at',
            ],
            self::pageArtifactsTableName() => [
                'id', 'page_id', 'artifact_version', 'source_snapshot_id', 'source_revision_hash',
                'content_hash', 'dependency_hash', 'dependencies_json',
                'page_section_count', 'title', 'slug', 'shell_mode', 'html',
                'css', 'custom_javascript', 'assets_manifest_json',
                'static_safety_status', 'static_safety_report_json',
                'created_by', 'created_at',
            ],
        ];

        foreach ($requiredColumns as $table => $columns) {
            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $actualColumns = $wpdb->get_col("SHOW COLUMNS FROM {$quotedTable}", 0);
            if (!is_array($actualColumns)) {
                return false;
            }

            $actual = array_fill_keys(array_map('strval', $actualColumns), true);
            foreach ($columns as $column) {
                if (!isset($actual[$column])) {
                    return false;
                }
            }

            if (strcasecmp(self::tableEngine($table), 'InnoDB') !== 0) {
                return false;
            }
        }

        return true;
    }

    private static function purgeOrphanedPageSourceRows(): bool
    {
        global $wpdb;

        $posts = $wpdb->posts;
        $sections = self::tableName();
        $operations = self::operationsTableName();

        $sectionsDeleted = $wpdb->query(
            "DELETE sections FROM {$sections} AS sections
             LEFT JOIN {$posts} AS posts ON posts.ID = sections.page_id
             WHERE posts.ID IS NULL"
        );
        if ($sectionsDeleted === false) {
            return false;
        }

        return $wpdb->query(
            "DELETE operations FROM {$operations} AS operations
             LEFT JOIN {$posts} AS posts ON posts.ID = operations.scope_id
             WHERE operations.scope_type = 'page' AND posts.ID IS NULL"
        ) !== false;
    }

    /**
     * dbDelta does not reliably convert an existing table's storage engine.
     * Source persistence relies on row locks and rollback, so a schema version
     * is complete only after every plugin-owned table is transactional.
     */
    private static function ensureTransactionalEngines(): void
    {
        global $wpdb;

        foreach (self::pluginTables() as $table) {
            if (strcasecmp(self::tableEngine($table), 'InnoDB') === 0) {
                continue;
            }

            $quotedTable = '`' . str_replace('`', '``', $table) . '`';
            $wpdb->query("ALTER TABLE {$quotedTable} ENGINE=InnoDB");
        }
    }

    /** @return string[] */
    private static function pluginTables(): array
    {
        return [
            self::tableName(),
            self::globalSectionsTableName(),
            self::operationsTableName(),
            self::pageStateTableName(),
            self::pageSourceSnapshotsTableName(),
            self::pageArtifactsTableName(),
        ];
    }

    /**
     * Existing editable work starts unpublished. INSERT IGNORE makes this safe
     * to retry without clearing a pointer created after the migration.
     */
    private static function initializeUnpublishedPageStates(): bool
    {
        global $wpdb;

        $postsTable = $wpdb->prefix . 'posts';
        $postmetaTable = $wpdb->prefix . 'postmeta';
        $sectionsTable = self::tableName();
        $pageStateTable = self::pageStateTableName();

        $initialized = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$pageStateTable}
                (page_id, draft_title, draft_slug, published_artifact_id, published_source_snapshot_id, draft_resume_policy, updated_by, updated_at, published_by, published_at)
             SELECT DISTINCT posts.ID, posts.post_title, posts.post_name, NULL, NULL, 'active', 0, CURRENT_TIMESTAMP, NULL, NULL
             FROM {$postsTable} AS posts
             LEFT JOIN {$postmetaTable} AS owned
               ON owned.post_id = posts.ID AND owned.meta_key = %s AND owned.meta_value = '1'
             LEFT JOIN {$sectionsTable} AS sections ON sections.page_id = posts.ID
             WHERE posts.post_type = 'page'
               AND (owned.post_id IS NOT NULL OR sections.page_id IS NOT NULL)",
            WpPageOwnershipRepository::META_OWNED,
        ));

        return $initialized !== false;
    }

    private static function tableEngine(string $table): string
    {
        global $wpdb;

        $status = $wpdb->get_row($wpdb->prepare('SHOW TABLE STATUS WHERE Name = %s', $table));

        return is_object($status) && isset($status->Engine) ? (string) $status->Engine : '';
    }
}
