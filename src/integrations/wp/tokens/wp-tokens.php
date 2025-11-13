<?php

namespace Uncanny_Automator;

/**
 * Class WP_Anon_Tokens
 *
 * @package Uncanny_Automator
 */
class Wp_Tokens {

	/**
	 * Integration code
	 *
	 * @var string
	 */
	public static $integration = 'WP';

	/**
	 * Previous error handler.
	 *
	 * @var callable|null
	 */
	private $previous_error_handler;

	/**
	 * Wp_Tokens constructor.
	 */
	public function __construct() {
		// Hide error for Automator Pro until Pro 3.1 is released.
		if ( PHP_MAJOR_VERSION >= 7 ) {
			// To support error handler chaining, we need to set our error handler with E_ALL error_levels.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler
			$this->previous_error_handler = set_error_handler( array( $this, 'error_handler' ) );
		}

		add_filter( 'automator_maybe_parse_token', array( $this, 'parse_wproles_token' ), 20, 6 );
	}

	/**
	 * Error handler.
	 *
	 * @param int    $level   Error level.
	 * @param string $message Error message.
	 * @param string $file    File produced an error.
	 * @param int    $line    Line number.
	 *
	 * @return bool
	 * @noinspection PhpTernaryExpressionCanBeReplacedWithConditionInspection
	 */
	public function error_handler( int $level, string $message, string $file, int $line ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$ua_error =
			$level & E_WARNING &&
			strpos( $file, '/tokens/wp-anon-tokens.php' ) !== false &&
			strpos( $message, 'Declaration of' ) === 0;

		// phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
		return $ua_error ? true : $this->fallback_error_handler( func_get_args() );
	}

	/**
	 * Fallback error handler.
	 *
	 * @param array $args Arguments.
	 *
	 * @return bool
	 * @noinspection PhpTernaryExpressionCanBeReplacedWithConditionInspection
	 */
	private function fallback_error_handler( array $args ): bool {
		return null === $this->previous_error_handler ?
			// Use standard error handler.
			false :
			(bool) call_user_func_array( $this->previous_error_handler, $args );
	}

	/**
	 * @param     $value
	 * @param     $pieces
	 * @param     $recipe_id
	 * @param     $trigger_data
	 *
	 * @param int $user_id
	 * @param     $replace_args
	 *
	 * @return mixed
	 */
	public function parse_wproles_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$piece = 'WPROLE';
		if ( $pieces ) {
			if ( in_array( $piece, $pieces ) ) {

				if ( $trigger_data ) {
					foreach ( $trigger_data as $trigger ) {
						global $wpdb;
						$meta_field = $trigger['ID'] . ':' . $pieces[1] . ':' . $pieces[2];
						$trigger_id = $trigger['ID'];
						$meta_value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}uap_trigger_log_meta WHERE meta_key LIKE %s AND automator_trigger_id = %d ORDER BY ID DESC LIMIT 0,1", "%%$meta_field%%", $trigger_id ) );
						if ( ! empty( $meta_value ) ) {
							$value = maybe_unserialize( $meta_value );
						}
					}
				}
			}
		}

		return $value;
	}
}
