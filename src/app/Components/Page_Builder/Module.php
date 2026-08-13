<?php
/**
 * Embedded Page Builder runtime host.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Components\Page_Builder;

use Uncanny_Automator\App\Application\Page_Builder\Can_Load_Page_Builder;

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
	const MODULE_VERSION = '1.0.1';

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
	 * Compatibility policy.
	 *
	 * @var Compatibility
	 */
	private $compatibility;

	/**
	 * Page Builder availability use case.
	 *
	 * @var Can_Load_Page_Builder
	 */
	private $can_load_page_builder;

	/**
	 * Constructor.
	 *
	 * @param Can_Load_Page_Builder $can_load_page_builder Availability use case.
	 * @param Compatibility|null    $compatibility         Compatibility policy.
	 */
	public function __construct( Can_Load_Page_Builder $can_load_page_builder, ?Compatibility $compatibility = null ) {
		$this->can_load_page_builder = $can_load_page_builder;
		$this->compatibility         = $compatibility ?? new Compatibility();
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
		add_action( 'admin_notices', array( $this, 'render_admin_notice' ) );
	}

	/**
	 * Claim and boot the module when this request satisfies its runtime floor.
	 *
	 * @return void
	 */
	public function boot(): void {
		try {
			if ( ! $this->can_load_page_builder->execute() ) {
				return;
			}

			$compatibility = $this->compatibility->check( $this->environment() );
			if ( 'ready' !== $compatibility['status'] ) {
				$this->set_status( $compatibility['status'], $compatibility['detail'] );
				return;
			}

			define( 'AUTOMATOR_PAGE_BUILDER_OWNS_RUNTIME', true );

			if ( ! defined( 'AUTOMATOR_PAGE_BUILDER_MODULE_VERSION' ) ) {
				define( 'AUTOMATOR_PAGE_BUILDER_MODULE_VERSION', self::MODULE_VERSION );
			}
			if ( ! defined( 'UNCANNY_PB_VERSION' ) ) {
				define( 'UNCANNY_PB_VERSION', self::MODULE_VERSION );
			}
			if ( ! defined( 'UNCANNY_PB_PATH' ) ) {
				define( 'UNCANNY_PB_PATH', UA_ABSPATH . 'src/page-builder/' );
			}
			if ( ! defined( 'UNCANNY_PB_URL' ) ) {
				define( 'UNCANNY_PB_URL', plugin_dir_url( AUTOMATOR_BASE_FILE ) . 'src/page-builder/' );
			}

			$this->boot_page_builder();
			$this->set_status( 'active', 'Automator owns and booted the embedded Page Builder runtime.' );
		} catch ( Module_Missing $throwable ) {
			$this->set_status( 'module_class_missing', $throwable->getMessage() );
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
		$healthy = in_array( $this->status, array( 'active', 'already_owned' ), true );
		$status  = $healthy ? 'good' : 'recommended';

		if ( in_array( $this->status, array( 'boot_failed', 'module_class_missing', 'standalone_runtime_active', 'dom_extension_missing' ), true ) ) {
			$status = 'critical';
		}

		return array(
			'label'       => $healthy
				? esc_html_x( 'Uncanny Page Builder is active', 'Site Health result', 'uncanny-automator' )
				: esc_html_x( 'Uncanny Page Builder is not using the embedded runtime', 'Site Health result', 'uncanny-automator' ),
			'status'      => $status,
			'badge'       => array(
				'label' => esc_html_x( 'Uncanny Automator', 'Site Health badge', 'uncanny-automator' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $this->detail ) . '</p>',
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

		$report['page_builder_module'] = array(
			'status'                 => $this->status,
			'detail'                 => $this->detail,
			'module_version'         => self::MODULE_VERSION,
			'minimum_bridge_version' => self::MINIMUM_BRIDGE_VERSION,
			'php_version'            => PHP_VERSION,
			'wordpress_version'      => $this->wordpress_version(),
			'dom_available'          => class_exists( 'DOMDocument' ),
		);

		return $report;
	}

	/**
	 * Show an actionable warning for unsafe handover or embedded boot failure.
	 *
	 * @return void
	 */
	public function render_admin_notice(): void {
		try {
			if (
				! current_user_can( 'manage_options' )
				|| ! in_array(
					$this->status,
					array( 'standalone_runtime_active', 'standalone_bridge_incompatible', 'module_class_missing', 'boot_failed', 'dom_extension_missing' ),
					true
				)
			) {
				return;
			}

			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html_x( 'Uncanny Page Builder runtime unavailable.', 'Page Builder module admin notice', 'uncanny-automator' ),
				esc_html( $this->detail )
			);
		} catch ( \Throwable $throwable ) {
			return;
		}
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
