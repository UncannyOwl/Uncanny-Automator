<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth;

/**
 * Exposes internal-token configuration readiness to WordPress operators.
 *
 * @since 7.4.1
 */
class Internal_Token_Configuration_Monitor {

	/**
	 * Site Health test identifier.
	 *
	 * @var string
	 */
	const SITE_HEALTH_TEST = 'automator_mcp_internal_token_configuration';

	/**
	 * Complete recoverable-token encryption readiness boundary.
	 *
	 * @var Internal_Token_Cache_Cipher
	 */
	private $cipher;

	/**
	 * Constructor.
	 *
	 * @param Internal_Token_Configuration|null $configuration Configuration policy.
	 * @param callable|null                     $feature_enabled_callback Legacy feature callback.
	 * @param Internal_Token_Cache_Cipher|null  $cipher Complete encryption readiness boundary.
	 */
	public function __construct(
		?Internal_Token_Configuration $configuration = null,
		?callable $feature_enabled_callback = null,
		?Internal_Token_Cache_Cipher $cipher = null
	) {
		unset( $feature_enabled_callback );

		$configuration = $configuration ?? new Internal_Token_Configuration();
		$this->cipher  = $cipher ?? new Internal_Token_Cache_Cipher( $configuration );
	}

	/**
	 * Register the Site Health diagnostic.
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
			'label' => esc_html_x( 'Uncanny Agent internal token encryption', 'Site Health test label', 'uncanny-automator' ),
			'test'  => array( $this, 'run_site_health_test' ),
		);

		return $tests;
	}

	/**
	 * Report whether the active Agent can safely persist its internal bearer.
	 *
	 * @return array Site Health result.
	 */
	public function run_site_health_test(): array {
		if ( $this->cipher->is_ready() ) {
			return $this->build_site_health_result(
				'good',
				esc_html_x( 'Uncanny Agent internal token encryption is configured', 'Site Health result', 'uncanny-automator' ),
				esc_html_x( 'A strong external secret is available for the recoverable internal bearer token.', 'Site Health result', 'uncanny-automator' )
			);
		}

		return $this->build_site_health_result(
			'recommended',
			esc_html_x( 'Uncanny Agent internal token encryption is not configured', 'Site Health result', 'uncanny-automator' ),
			$this->cipher->get_configuration_error()->get_error_message()
		);
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
