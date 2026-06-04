<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Execution_Log_Store;
use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Database\Database;

/**
 * Trigger validation logic.
 *
 * Validates whether a trigger matches a recipe based on code, post_id, meta,
 * and plugin status. Shared checks are in validate_common() — each public
 * method adds only its divergent logic.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.2
 */
class Trigger_Validator {

	/**
	 * @var Execution_Log_Store
	 */
	private $log_store;

	/**
	 * @var Integration_Registry
	 */
	private $integrations;

	/**
	 * @var Recipe_Data_Provider
	 */
	private $data_provider;

	/**
	 * @var Error_Handler
	 */
	private $error_handler;

	/**
	 * @param Execution_Log_Store|null            $log_store     Optional log store instance.
	 * @param Integration_Registry|null $integrations  Optional integration registry.
	 * @param Recipe_Data_Provider|null $data_provider Optional data provider instance.
	 * @param Error_Handler|null        $error_handler Optional error handler instance.
	 */
	public function __construct( ?Execution_Log_Store $log_store = null, ?Integration_Registry $integrations = null, ?Recipe_Data_Provider $data_provider = null, ?Error_Handler $error_handler = null ) {
		$this->log_store     = $log_store ?? Database::get_execution_log_store();
		$this->integrations  = $integrations ?? new Integration_Registry();
		$this->data_provider = $data_provider ?? new Recipe_Data_Provider();
		$this->error_handler = $error_handler ?? new Error_Handler();
	}

	/**
	 * Route validation to the appropriate method based on ignore_post_id flag.
	 *
	 * @param array    $args               Normalized trigger args.
	 * @param array    $trigger             Trigger data with ID, meta, post_status.
	 * @param int      $recipe_id           The recipe ID.
	 * @param int|null $maybe_recipe_log_id The recipe log ID (may be simulated).
	 * @param bool     $ignore_post_id      Whether to skip post_id validation.
	 *
	 * @return array{result: bool, trigger_log_id?: int|null, error?: string}
	 */
	public function get_trigger_id( $args, $trigger, $recipe_id, $maybe_recipe_log_id, $ignore_post_id ): array {

		if ( $ignore_post_id ) {
			return $this->maybe_validate_trigger_without_postid( $args, $trigger, $recipe_id, $maybe_recipe_log_id );
		}

		return $this->maybe_validate_trigger( $args, $trigger, $recipe_id, $maybe_recipe_log_id );
	}

	/**
	 * Validate trigger with post_id matching.
	 *
	 * @param array    $args          Normalized trigger args.
	 * @param array    $trigger       Trigger data.
	 * @param int      $recipe_id     The recipe ID.
	 * @param int|null $recipe_log_id The recipe log ID.
	 *
	 * @return array
	 */
	public function maybe_validate_trigger( $args = array(), $trigger = null, $recipe_id = null, $recipe_log_id = null ): array {

		$common_result = $this->validate_common( $args, $trigger, $recipe_id, $recipe_log_id );

		if ( null !== $common_result ) {
			return $common_result;
		}

		$trigger_meta    = $args['meta'];
		$post_id         = $args['post_id'];
		$trigger_post_id = isset( $trigger['meta'][ $trigger_meta ] ) ? intval( $trigger['meta'][ $trigger_meta ] ) : 0;

		if ( ! $this->is_post_id_match( $trigger_post_id, $post_id ) ) {
			return $this->fail( 'Trigger not matched.' );
		}

		return $this->maybe_get_trigger_id( $args['user_id'], $trigger['ID'], $recipe_id, $recipe_log_id );
	}

	/**
	 * Validate trigger without post_id matching (used with ignore_post_id flag).
	 *
	 * @param array    $args          Normalized trigger args.
	 * @param array    $trigger       Trigger data.
	 * @param int      $recipe_id     The recipe ID.
	 * @param int|null $recipe_log_id The recipe log ID.
	 *
	 * @return array
	 */
	public function maybe_validate_trigger_without_postid( $args = array(), $trigger = null, $recipe_id = null, $recipe_log_id = null ): array {

		$common_result = $this->validate_common( $args, $trigger, $recipe_id, $recipe_log_id );

		if ( null !== $common_result ) {
			return $common_result;
		}

		$matched_recipe_id  = isset( $args['recipe_to_match'] ) ? (int) $args['recipe_to_match'] : null;
		$matched_trigger_id = isset( $args['trigger_to_match'] ) ? (int) $args['trigger_to_match'] : null;
		$trigger_id         = is_numeric( $matched_trigger_id ) ? (int) $matched_trigger_id : $trigger['ID'];

		$recipe_match_error = $this->validate_recipe_match( $args, $trigger, $recipe_id, $matched_recipe_id );

		if ( null !== $recipe_match_error ) {
			return $recipe_match_error;
		}

		return $this->maybe_get_trigger_id( $args['user_id'], $trigger_id, $recipe_id, $recipe_log_id );
	}

	/**
	 * Get or create a trigger log ID for the user/trigger/recipe combination.
	 *
	 * @param int      $user_id       The user ID.
	 * @param int      $trigger_id    The trigger post ID.
	 * @param int      $recipe_id     The recipe post ID.
	 * @param int|null $recipe_log_id The recipe log ID.
	 *
	 * @return array
	 */
	public function maybe_get_trigger_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id = null ): array {

		if ( null === $trigger_id || null === $recipe_id || null === $user_id || 0 === $trigger_id || 0 === $recipe_id ) {
			return $this->fail( 'One of the required field is missing.' );
		}

		$trigger_log_id = $this->data_provider->get_trigger_log_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );

		if ( null === $trigger_log_id && is_numeric( $recipe_log_id ) ) {
			$trigger_log_id = $this->log_store->add_trigger( $user_id, $trigger_id, $recipe_id, false, $recipe_log_id );
		}

		return array(
			'result'         => true,
			'trigger_log_id' => $trigger_log_id,
		);
	}

	/**
	 * Shared validation: required fields, plugin status, completion, and code match.
	 *
	 * @param array    $args          Normalized trigger args.
	 * @param array    $trigger       Trigger data.
	 * @param int      $recipe_id     The recipe ID.
	 * @param int|null $recipe_log_id The recipe log ID.
	 *
	 * @return array|null Error array if validation fails, null if all checks pass.
	 */
	protected function validate_common( $args, $trigger, $recipe_id, $recipe_log_id ): ?array {

		if ( empty( $args ) || null === $trigger || null === $recipe_id ) {
			return $this->fail( 'One of the required field is missing.' );
		}

		$trigger_integration = $trigger['meta']['integration'];

		if ( ! $this->integrations->is_plugin_active( $trigger_integration ) ) {
			$this->error_handler->add_error(
				'uap_do_trigger_log',
				'ERROR: You are trying to complete ' . $trigger['meta']['code'] . ' and the plugin ' . $trigger_integration . ' is not active. ',
				$this
			);

			return $this->fail( 'Plugin is not active.' );
		}

		// Coerce at the boundary — Log_Store is strict-typed; $args['user_id']
		// and $trigger['ID'] arrive as ints OR strings depending on caller.
		$user_id       = (int) $args['user_id'];
		$trigger_id    = (int) $trigger['ID'];
		$recipe_log_id = (int) $recipe_log_id;

		$process_recipe       = Dispatcher::filter( 'automator_get_trigger_log_id_process_recipe', false, $user_id, $trigger_id, $recipe_id, $recipe_log_id, $args );
		$is_trigger_completed = $this->log_store->is_trigger_completed( $user_id, $trigger_id, (int) $recipe_id, $recipe_log_id, (bool) $process_recipe, $args );

		if ( $is_trigger_completed ) {
			return $this->fail( 'Trigger is completed.' );
		}

		$check_trigger_code = $args['code'];
		$trigger_code       = $trigger['meta']['code'];

		if ( (string) $check_trigger_code !== (string) $trigger_code ) {
			return $this->fail(
				sprintf( '%s AND %s triggers not matched.', $check_trigger_code, $trigger_code )
			);
		}

		return null;
	}

	/**
	 * Validate recipe-specific matching (used only in without-postid path).
	 *
	 * @param array $args             Normalized trigger args.
	 * @param array $trigger          Trigger data.
	 * @param int   $recipe_id        The actual recipe ID.
	 * @param int   $matched_recipe_id The recipe ID to match against.
	 *
	 * @return array|null Error array if mismatch, null if OK.
	 */
	protected function validate_recipe_match( array $args, array $trigger, int $recipe_id, $matched_recipe_id ): ?array {

		$matched_recipe_id = (int) $matched_recipe_id;

		if ( 0 !== $matched_recipe_id && $recipe_id !== $matched_recipe_id ) {
			return $this->fail( 'Recipe not matched.' );
		}

		if ( $recipe_id !== $matched_recipe_id ) {
			return null;
		}

		$trigger_meta = $args['meta'];

		$is_meta_missing = ! isset( $trigger['meta'][ $trigger_meta ] ) && ! isset( $trigger['meta'][ $args['code'] ] );

		if ( $is_meta_missing || $trigger['meta']['code'] !== $args['code'] ) {
			return $this->fail( 'Trigger meta not found.' );
		}

		return null;
	}

	/**
	 * Check if the trigger's post_id matches the fired post_id.
	 *
	 * A trigger_post_id of -1 means "Any" and always matches.
	 *
	 * @param int|string $trigger_post_id The post ID configured on the trigger.
	 * @param int|string $fired_post_id   The post ID from the fired event.
	 *
	 * @return bool
	 */
	protected function is_post_id_match( $trigger_post_id, $fired_post_id ): bool {

		// "Any" (-1) always matches.
		if ( -1 === intval( $trigger_post_id ) ) {
			return true;
		}

		// Both numeric → compare as integers.
		if ( is_numeric( $trigger_post_id ) && is_numeric( $fired_post_id ) ) {
			return absint( $trigger_post_id ) === absint( $fired_post_id );
		}

		// Fallback string comparison.
		return (string) $trigger_post_id === (string) $fired_post_id;
	}

	/**
	 * Build a standardized failure result.
	 *
	 * @param string $message The error message.
	 *
	 * @return array{result: false, error: string}
	 */
	private function fail( string $message ): array {
		return array(
			'result' => false,
			'error'  => esc_html( $message ),
		);
	}
}
