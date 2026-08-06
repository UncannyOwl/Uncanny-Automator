<?php
/**
 * WordPress site signing-key diagnostics.
 *
 * @since 7.5.1
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Authentication;

use WP_Error;

/**
 * Exposes site-identity readiness to WordPress administrators.
 */
class Site_Signing_Key_Monitor {

	/**
	 * Site Health test identifier.
	 *
	 * @var string
	 */
	const SITE_HEALTH_TEST = 'automator_mcp_site_signing_key';

	/**
	 * Site signing-key manager.
	 *
	 * @var Site_Signing_Key_Manager
	 */
	private Site_Signing_Key_Manager $key_manager;

	/**
	 * Cached readiness error for this request.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $error = null;

	/**
	 * Whether readiness was checked for this request.
	 *
	 * @var bool
	 */
	private bool $checked = false;

	/**
	 * Constructor.
	 *
	 * @param Site_Signing_Key_Manager|null $key_manager Site signing-key manager.
	 */
	public function __construct( ?Site_Signing_Key_Manager $key_manager = null ) {
		$this->key_manager = $key_manager ?? new Site_Signing_Key_Manager();
	}

	/**
	 * Register operator-facing diagnostics.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'site_status_tests', array( $this, 'register_site_health_test' ) );
	}

	/**
	 * Register the direct Site Health diagnostic.
	 *
	 * @param array $tests Existing Site Health tests.
	 * @return array Filtered tests.
	 */
	public function register_site_health_test( array $tests ): array {
		$tests['direct'][ self::SITE_HEALTH_TEST ] = array(
			'label' => esc_html_x( 'Uncanny Agent site identity', 'Site Health test label', 'uncanny-automator' ),
			'test'  => array( $this, 'run_site_health_test' ),
		);

		return $tests;
	}

	/**
	 * Report whether the Agent can use its site identity.
	 *
	 * @return array Site Health result.
	 */
	public function run_site_health_test(): array {
		$error = $this->get_readiness_error();

		if ( ! $error instanceof WP_Error ) {
			return $this->build_site_health_result(
				'good',
				esc_html_x( 'Uncanny Agent site identity prerequisites are available', 'Site Health result', 'uncanny-automator' ),
				esc_html_x( 'WordPress can read the stored key, or the required cryptography and wrapping secret are available.', 'Site Health result', 'uncanny-automator' )
			);
		}

		return $this->build_site_health_result(
			'recommended',
			esc_html_x( 'Uncanny Agent site identity is unavailable', 'Site Health result', 'uncanny-automator' ),
			$error->get_error_message()
		);
	}

	/**
	 * Return the cached readiness error.
	 *
	 * @return WP_Error|null Readiness error or null.
	 */
	private function get_readiness_error(): ?WP_Error {
		if ( ! $this->checked ) {
			$this->error   = $this->key_manager->get_readiness_error();
			$this->checked = true;
		}

		return $this->error;
	}

	/**
	 * Build a consistent Site Health result.
	 *
	 * @param string $status Site Health status.
	 * @param string $label Result label.
	 * @param string $description Result description.
	 * @return array Site Health result.
	 */
	private function build_site_health_result( string $status, string $label, string $description ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => esc_html_x( 'Uncanny Automator', 'Site Health badge', 'uncanny-automator' ),
				'color' => 'blue',
			),
			'description' => '<p>' . esc_html( $description ) . '</p>',
			'actions'     => '',
			'test'        => self::SITE_HEALTH_TEST,
		);
	}
}
