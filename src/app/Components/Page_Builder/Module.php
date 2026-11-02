<?php
/**
 * Embedded Page Builder runtime host.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Components\Page_Builder;

use Uncanny_Automator\App\Presentation\Admin\Page_Builder_Notice;

use Uncanny_Automator\App\Application\Page_Builder\Can_Load_Page_Builder;
use Uncanny_Automator\App\Infrastructure\Page_Builder\Page_Builder_Settings;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;
use UncannyPageBuilder\Infrastructure\WordPress\WpCronWorkingCanvasRefreshQueue;

/**
 * Owns only the compatibility, handover, loading, and diagnostic boundary.
 *
 * This file must remain valid on Automator's PHP 7.4 floor. Page Builder
 * classes are not resolved until every compatibility check has passed.
 *
 * @since 7.4.1
 */
class Module {

	/**
	 * Embedded Page Builder release.
	 *
	 * @var string
	 */
	const MODULE_VERSION = '1.0.3';

	/**
	 * Oldest standalone bridge that can safely hand over runtime ownership.
	 *
	 * @var string
	 */
	const MINIMUM_BRIDGE_VERSION = '1.0.1';

	/**
	 * Site Health test identifier.
	 *
	 * @var string
	 */
	const SITE_HEALTH_TEST = 'automator_page_builder_module';

	/**
	 * Page Builder page-ownership marker.
	 *
	 * @var string
	 */
	const PAGE_OWNERSHIP_META_KEY = '_uncanny_page_builder_owned';

	/**
	 * User option that held the fingerprint of the notice this user dismissed
	 * before the notice center (per user, per site). Page_Builder_Notice still
	 * honours it; new dismissals go to the center.
	 *
	 * @var string
	 */
	const DISMISSED_META_KEY = 'automator_page_builder_notice_dismissed';

	/**
	 * The statuses in which Page Builder cannot run and the admin is told.
	 *
	 * @since 8.0.0
	 *
	 * @var string[]
	 */
	const UNAVAILABLE_STATUSES = array( 'standalone_runtime_active', 'standalone_bridge_incompatible', 'module_class_missing', 'boot_failed', 'dom_extension_missing' );

	/**
	 * Page Builder table suffixes, mirrored from SchemaManager so this file
	 * never resolves a Page Builder class. A test guards the two lists.
	 *
	 * @var string[]
	 */
	const PAGE_BUILDER_TABLES = array(
		'upb_sections',
		'upb_global_sections',
		'upb_operations',
		'upb_page_state',
		'upb_page_artifacts',
		'upb_page_source_snapshots',
	);

	/**
	 * Current request status.
	 *
	 * @var string
	 */
	private $status = 'pending';

	/**
	 * Human-readable status detail.
	 *
	 * @var string
	 */
	private $detail = '';

	/**
	 * Whether the boot failure came from a non-InnoDB database table.
	 *
	 * @var bool
	 */
	private $database_engine_failure = false;

	/**
	 * Compatibility policy.
	 *
	 * @var Compatibility
	 */
	private $compatibility;

	/**
	 * Constructor.
	 *
	 * The former Can_Load_Page_Builder argument remains accepted for binary
	 * compatibility, but presentation policy no longer controls runtime boot.
	 *
	 * @param Can_Load_Page_Builder|Compatibility|null $legacy_gate_or_compatibility Deprecated gate or compatibility policy.
	 * @param Compatibility|null                       $compatibility               Compatibility policy.
	 */
	public function __construct( $legacy_gate_or_compatibility = null, ?Compatibility $compatibility = null ) {
		if ( $legacy_gate_or_compatibility instanceof Compatibility ) {
			$this->compatibility = $legacy_gate_or_compatibility;
			return;
		}

		if ( null !== $legacy_gate_or_compatibility && ! $legacy_gate_or_compatibility instanceof Can_Load_Page_Builder ) {
			throw new \InvalidArgumentException( 'The Page Builder host received an unsupported dependency.' );
		}

		$this->compatibility = $compatibility ?? new Compatibility();
	}

	/**
	 * Register ownership and operator-diagnostic hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'plugins_loaded', array( $this, 'boot' ), 20 );
		add_filter( 'site_status_tests', array( $this, 'register_site_health_test' ) );
		add_filter( 'automator_system_report_get', array( $this, 'add_system_report' ) );

		if ( is_admin() && ! in_array( 'page-builder-unavailable', automator_notice_center()->ids(), true ) ) {
			automator_notice_center()->add( new Page_Builder_Notice( $this ) );
		}
	}

	/**
	 * Check the narrow URL contract for Page Builder runtime notices.
	 *
	 * Existing recipe and global-part editors use post.php?post=... and are
	 * intentionally excluded because they do not carry either post_type value.
	 *
	 * @return bool
	 */
	public function is_notice_route(): bool {
		$post_type = automator_request_input( 'post_type' );

		if ( in_array( $post_type, array( AUTOMATOR_POST_TYPE_RECIPE, 'upb_global_part' ), true ) ) {
			return true;
		}

		return 'uncanny-page-builder-settings' === automator_request_input( 'page' );
	}

	/**
	 * Claim and boot the module when this request satisfies its runtime floor.
	 *
	 * @return void
	 */
	public function boot(): void {
		try {
			if ( ! $this->should_boot_page_builder() ) {
				$this->set_status( 'inactive', 'Page Builder is disabled and no owned pages exist.' );
				return;
			}

			// Feature policy controls new-page affordances, never runtime ownership.
			// Existing editors and published pages must survive a policy transition.
			$compatibility = $this->compatibility->check( $this->environment() );
			if ( 'ready' !== $compatibility['status'] ) {
				$this->set_status( $compatibility['status'], $compatibility['detail'] );
				return;
			}

			if ( ! defined( 'AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME' ) ) {
				define( 'AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME', true );
			}

			if ( ! defined( 'AUTOMATOR_PAGE_BUILDER_MODULE_VERSION' ) ) {
				define( 'AUTOMATOR_PAGE_BUILDER_MODULE_VERSION', self::MODULE_VERSION );
			}
			if ( ! defined( 'UNCANNY_PB_VERSION' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Public constant of the embedded Page Builder runtime, kept for compatibility.
				define( 'UNCANNY_PB_VERSION', self::MODULE_VERSION );
			}
			if ( ! defined( 'UNCANNY_PB_PATH' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Public constant of the embedded Page Builder runtime, kept for compatibility.
				define( 'UNCANNY_PB_PATH', UA_ABSPATH . 'src/page-builder/' );
			}
			if ( ! defined( 'UNCANNY_PB_URL' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Public constant of the embedded Page Builder runtime, kept for compatibility.
				define( 'UNCANNY_PB_URL', plugin_dir_url( AUTOMATOR_BASE_FILE ) . 'src/page-builder/' );
			}

			$this->boot_page_builder();
			$this->set_status( 'active', 'Automator owns and booted the embedded Page Builder runtime.' );
		} catch ( Module_Missing $throwable ) {
			$this->set_status( 'module_class_missing', $throwable->getMessage() );
		} catch ( SourceTransactionsUnavailableException $throwable ) {
			$this->database_engine_failure = true;
			$this->set_status(
				'boot_failed',
				sprintf(
					'Embedded Page Builder boot failed: %s',
					$this->safe_exception_message( $throwable )
				)
			);
		} catch ( \Throwable $throwable ) {
			$this->set_status(
				'boot_failed',
				sprintf(
					'Embedded Page Builder boot failed: %s',
					$this->safe_exception_message( $throwable )
				)
			);
		}
	}

	/**
	 * Add the direct Site Health test.
	 *
	 * @param array $tests Existing tests.
	 * @return array
	 */
	public function register_site_health_test( $tests = null ): array {
		$tests = is_array( $tests ) ? $tests : array();

		$tests['direct'][ self::SITE_HEALTH_TEST ] = array(
			'label' => esc_html_x( 'Uncanny Page Builder runtime', 'Site Health test label', 'uncanny-automator' ),
			'test'  => array( $this, 'run_site_health_test' ),
		);

		return $tests;
	}

	/**
	 * Report the current ownership state through Site Health.
	 *
	 * @return array
	 */
	public function run_site_health_test(): array {
		$healthy  = $this->is_healthy();
		$inactive = 'inactive' === $this->status;
		$status   = $healthy || $inactive ? 'good' : 'recommended';

		if ( in_array( $this->status, array( 'boot_failed', 'module_class_missing', 'standalone_runtime_active', 'dom_extension_missing' ), true ) ) {
			$status = 'critical';
		}

		$description  = '<p>' . esc_html( $this->friendly_message() ) . '</p>';
		$description .= '<p class="description">' . esc_html( $this->detail ) . '</p>';

		return array(
			'label'       => $healthy
				? esc_html_x( 'Uncanny Page Builder is ready to use', 'Site Health result', 'uncanny-automator' )
				: ( $inactive
					? esc_html_x( 'Uncanny Page Builder is not using the embedded runtime', 'Site Health result', 'uncanny-automator' )
					: esc_html_x( 'Uncanny Page Builder is not available on this site', 'Site Health result', 'uncanny-automator' ) ),
			'status'      => $status,
			'badge'       => array(
				'label' => esc_html_x( 'Uncanny Automator', 'Site Health badge', 'uncanny-automator' ),
				'color' => 'blue',
			),
			'description' => $description,
			'actions'     => '',
			'test'        => self::SITE_HEALTH_TEST,
		);
	}

	/**
	 * Include Page Builder ownership in Automator's downloadable report.
	 *
	 * @param array $report Existing report.
	 * @return array
	 */
	public function add_system_report( $report = null ): array {
		$report = is_array( $report ) ? $report : array();
		$known  = $this->reported_table_engines( $report );

		$report['page_builder_module'] = array(
			'status'                 => $this->status,
			'healthy'                => $this->is_healthy(),
			'message'                => $this->friendly_message(),
			'detail'                 => $this->detail,
			'module_version'         => self::MODULE_VERSION,
			'minimum_bridge_version' => self::MINIMUM_BRIDGE_VERSION,
			'php_version'            => PHP_VERSION,
			'wordpress_version'      => $this->wordpress_version(),
			'dom_available'          => class_exists( 'DOMDocument' ),
			'table_engines'          => $this->wordpress_table_engines( $known ),
			'tables'                 => $this->page_builder_tables( $known ),
			'schema_version'         => (string) get_option( 'uncanny_page_builder_db_version', '' ),
			'refresh_queue'          => $this->refresh_queue_status(),
		);

		return $report;
	}

	/**
	 * Engines of the tables the host report already enumerated, so this
	 * filter adds no database round-trips on the Status tab or the usage
	 * report cron. Empty when the report did not include the database section.
	 *
	 * @param array $report Host report as passed to the filter.
	 * @return array<string,string> Table name => engine.
	 */
	private function reported_table_engines( array $report ): array {
		$groups = isset( $report['database']['database_tables'] ) && is_array( $report['database']['database_tables'] )
			? $report['database']['database_tables']
			: array();

		$engines = array();
		foreach ( $groups as $tables ) {
			if ( ! is_array( $tables ) ) {
				continue;
			}
			foreach ( $tables as $name => $info ) {
				if ( is_array( $info ) && isset( $info['engine'] ) ) {
					$engines[ (string) $name ] = (string) $info['engine'];
				}
			}
		}

		return $engines;
	}

	/**
	 * Storage engine of each WordPress table Page Builder writes inside a
	 * transaction. Falls back to plain SQL so a broken runtime still reports.
	 *
	 * @param array<string,string> $known Engines already known from the host report.
	 * @return array<string,string> Table name => engine ('' when unknown).
	 */
	private function wordpress_table_engines( array $known ): array {
		global $wpdb;

		$engines = array();
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return $engines;
		}

		foreach ( array( 'posts', 'postmeta', 'options' ) as $key ) {
			$table = isset( $wpdb->{$key} ) ? (string) $wpdb->{$key} : $wpdb->prefix . $key;

			if ( isset( $known[ $table ] ) ) {
				$engines[ $table ] = $known[ $table ];
				continue;
			}

			try {
				$row = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} catch ( \Throwable $throwable ) {
				$row = null;
			}

			$engines[ $table ] = is_object( $row ) && isset( $row->Engine ) ? (string) $row->Engine : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		return $engines;
	}

	/**
	 * Presence of every Page Builder table, without resolving a Page Builder class.
	 *
	 * @param array<string,string> $known Engines already known from the host report.
	 * @return array<string,bool> Table name => exists.
	 */
	private function page_builder_tables( array $known ): array {
		global $wpdb;

		$tables = array();
		if ( ! isset( $wpdb ) || ! is_object( $wpdb ) ) {
			return $tables;
		}

		foreach ( self::PAGE_BUILDER_TABLES as $suffix ) {
			$table = $wpdb->prefix . $suffix;

			if ( array() !== $known ) {
				$tables[ $table ] = isset( $known[ $table ] );
				continue;
			}

			try {
				$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			} catch ( \Throwable $throwable ) {
				$found = null;
			}

			$tables[ $table ] = $table === $found;
		}

		return $tables;
	}

	/**
	 * Background preview-refresh queue counts. Only read once the runtime
	 * booted, because the queue is a Page Builder class.
	 *
	 * @return array{available:bool,pending:int,failed:int}
	 */
	private function refresh_queue_status(): array {
		$empty = array(
			'available' => false,
			'pending'   => 0,
			'failed'    => 0,
		);

		if (
			! $this->is_healthy()
			|| ! class_exists( WpCronWorkingCanvasRefreshQueue::class )
		) {
			return $empty;
		}

		try {
			$queue = new WpCronWorkingCanvasRefreshQueue();

			return array(
				'available' => true,
				'pending'   => $queue->pendingCount(),
				'failed'    => count( $queue->terminalFailures() ),
			);
		} catch ( \Throwable $throwable ) {
			return $empty;
		}
	}

	/**
	 * Whether the embedded Page Builder cannot run here, for a reason a site
	 * manager should be told.
	 *
	 * @since 8.0.0
	 *
	 * @return bool
	 */
	public function is_unavailable(): bool {
		return in_array( $this->status, self::UNAVAILABLE_STATUSES, true );
	}

	/**
	 * Whether the embedded runtime is serving this request.
	 *
	 * @return bool
	 */
	private function is_healthy(): bool {
		return in_array( $this->status, array( 'active', 'already_owned' ), true );
	}

	/**
	 * Explain the current status in plain language for the admin notice and
	 * Site Health. The technical detail is kept alongside for support.
	 *
	 * @return string
	 */
	public function friendly_message(): string {
		return self::friendly_message_for( $this->status, $this->database_engine_failure );
	}

	/**
	 * The plain-language explanation of a status.
	 *
	 * @since 8.0.0
	 *
	 * @param string $status                  The module status.
	 * @param bool   $database_engine_failure Whether a boot failure came from a table's storage engine.
	 *
	 * @return string
	 */
	public static function friendly_message_for( string $status, bool $database_engine_failure = false ): string {
		if ( 'boot_failed' === $status && $database_engine_failure ) {
			return esc_html_x(
				'Page Builder needs your WordPress database tables to use the InnoDB storage engine, but at least one of them uses a different engine. Ask your host to convert the posts, postmeta and options tables to InnoDB, then reload this page.',
				'Page Builder module status',
				'uncanny-automator'
			);
		}

		switch ( $status ) {
			case 'active':
			case 'already_owned':
				return esc_html_x( 'Page Builder is running and ready to use.', 'Page Builder module status', 'uncanny-automator' );

			case 'inactive':
				return esc_html_x( 'Page Builder is disabled and has no draft or published pages that need its runtime.', 'Page Builder module status', 'uncanny-automator' );

			case 'disabled':
				return esc_html_x( 'Page Builder has been turned off on this site by a setting or a filter.', 'Page Builder module status', 'uncanny-automator' );

			case 'php_incompatible':
				return esc_html_x( 'Page Builder needs PHP 8.1 or newer. Ask your host to update PHP, then reload this page.', 'Page Builder module status', 'uncanny-automator' );

			case 'wordpress_incompatible':
				return esc_html_x( 'Page Builder needs WordPress 6.3 or newer. Update WordPress, then reload this page.', 'Page Builder module status', 'uncanny-automator' );

			case 'dom_extension_missing':
				return esc_html_x( 'Page Builder needs the PHP DOM extension, which is not installed on this server. Ask your host to enable it, then reload this page.', 'Page Builder module status', 'uncanny-automator' );

			case 'ownership_marker_conflict':
				return esc_html_x( 'Page Builder was told not to run before Uncanny Automator loaded, usually by an AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME constant in wp-config.php or a must-use plugin. Remove that override to let Automator run Page Builder.', 'Page Builder module status', 'uncanny-automator' );

			case 'standalone_runtime_active':
			case 'standalone_bridge_incompatible':
				return esc_html_x( 'An older standalone Uncanny Page Builder plugin is still active. Update or deactivate it so Uncanny Automator can run the built-in Page Builder instead.', 'Page Builder module status', 'uncanny-automator' );

			case 'module_class_missing':
				return esc_html_x( 'Some Page Builder files are missing from this Uncanny Automator installation. Reinstalling Uncanny Automator usually fixes this.', 'Page Builder module status', 'uncanny-automator' );

			case 'boot_failed':
				return esc_html_x( 'Page Builder could not start because of an unexpected error. If this keeps happening, please share the technical details below with our support team.', 'Page Builder module status', 'uncanny-automator' );
		}

		return esc_html_x( 'Page Builder has not finished loading yet.', 'Page Builder module status', 'uncanny-automator' );
	}

	/**
	 * Identify the current failure so a changed status or detail surfaces
	 * even after an operator dismissed an earlier notice.
	 *
	 * @return string
	 */
	public function notice_fingerprint(): string {
		return md5( $this->status . '|' . $this->detail );
	}

	/**
	 * Expose request-local status for tests and diagnostics.
	 *
	 * @return array{status:string,detail:string}
	 */
	public function get_status(): array {
		return array(
			'status' => $this->status,
			'detail' => $this->detail,
		);
	}

	/**
	 * Read the active WordPress release without touching Page Builder.
	 *
	 * @return string
	 */
	protected function wordpress_version(): string {
		global $wp_version;

		return is_string( $wp_version ) ? $wp_version : '0';
	}

	/**
	 * Boot when the setting is enabled or an owned page exists.
	 *
	 * A failed read must not hide an existing Page Builder page.
	 *
	 * @return bool
	 */
	private function should_boot_page_builder(): bool {
		try {
			if ( ( new Page_Builder_Settings() )->is_enabled( true ) ) {
				return true;
			}

			return $this->has_owned_pages();
		} catch ( \Throwable $throwable ) {
			unset( $throwable );

			return true;
		}
	}

	/**
	 * Check for an owned page without loading a Page Builder class.
	 *
	 * @return bool
	 * @throws \RuntimeException When WordPress cannot read the ownership state.
	 */
	protected function has_owned_pages(): bool {
		global $wpdb;

		if (
			! is_object( $wpdb )
			|| ! isset( $wpdb->posts, $wpdb->postmeta )
			|| ! method_exists( $wpdb, 'prepare' )
			|| ! method_exists( $wpdb, 'get_var' )
		) {
			throw new \RuntimeException( 'WordPress cannot read Page Builder page ownership.' );
		}

		$query = $wpdb->prepare(
			"SELECT pages.ID
			FROM {$wpdb->posts} AS pages
			INNER JOIN {$wpdb->postmeta} AS ownership ON ownership.post_id = pages.ID
			WHERE ownership.meta_key = %s
			AND ownership.meta_value = %s
			AND pages.post_status IN (%s, %s)
			LIMIT 1",
			self::PAGE_OWNERSHIP_META_KEY,
			'1',
			'draft',
			'publish'
		);

		if ( ! is_string( $query ) ) {
			throw new \RuntimeException( 'WordPress cannot prepare the Page Builder ownership query.' );
		}

		// This direct query runs before custom post types register on init.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$page_id = $wpdb->get_var( $query );

		if ( isset( $wpdb->last_error ) && '' !== trim( (string) $wpdb->last_error ) ) {
			throw new \RuntimeException( 'WordPress cannot read Page Builder page ownership.' );
		}

		return null !== $page_id;
	}

	/**
	 * Resolve and boot Page Builder only after ownership has been claimed.
	 *
	 * @return void
	 * @throws Module_Missing When Composer cannot resolve the module.
	 */
	protected function boot_page_builder(): void {
		if ( ! class_exists( '\UncannyPageBuilder\Plugin' ) ) {
			throw new Module_Missing(
				'The embedded Page Builder class could not be loaded from Automator Composer metadata.'
			);
		}

		\UncannyPageBuilder\Plugin::boot();
	}

	/**
	 * Avoid leaking paths and traces into operator-facing diagnostics.
	 *
	 * @param \Throwable $throwable Boot failure.
	 * @return string
	 */
	private function safe_exception_message( \Throwable $throwable ): string {
		$message = trim( $throwable->getMessage() );

		if ( '' === $message ) {
			return get_class( $throwable );
		}

		$paths = array(
			'UA_ABSPATH' => '[automator]/',
			'ABSPATH'    => '[wordpress]/',
		);
		foreach ( $paths as $constant => $replacement ) {
			if ( defined( $constant ) ) {
				$message = str_replace( (string) constant( $constant ), $replacement, $message );
			}
		}

		return $message;
	}

	/**
	 * Collect scalar runtime facts without resolving a Page Builder symbol.
	 *
	 * @return array
	 */
	private function environment(): array {
		return array(
			'disabled'                 => defined( 'AUTOMATOR_PAGE_BUILDER_DISABLED' ) && AUTOMATOR_PAGE_BUILDER_DISABLED,
			'php_version'              => PHP_VERSION,
			'wordpress_version'        => $this->wordpress_version(),
			'dom_available'            => class_exists( 'DOMDocument' ),
			'ownership_marker_defined' => defined( 'AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME' ),
			'owns_runtime'             => defined( 'AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME' )
				&& AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME,
			'legacy_runtime_constants' => defined( 'UNCANNY_PB_VERSION' )
				|| defined( 'UNCANNY_PB_PATH' )
				|| defined( 'UNCANNY_PB_URL' ),
			'bridge_version'           => defined( 'UNCANNY_PB_STANDALONE_BRIDGE_VERSION' )
				? (string) UNCANNY_PB_STANDALONE_BRIDGE_VERSION
				: '',
		);
	}

	/**
	 * Record one exact request outcome.
	 *
	 * @param string $status Status code.
	 * @param string $detail Operator detail.
	 * @return void
	 */
	private function set_status( string $status, string $detail ): void {
		$this->status = $status;
		$this->detail = $detail;
	}
}
