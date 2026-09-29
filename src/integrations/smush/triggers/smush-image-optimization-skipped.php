<?php

namespace Uncanny_Automator\Integrations\Smush;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Smush_Image_Optimization_Skipped
 *
 * @package Uncanny_Automator
 *
 * @property Smush_Helpers $item_helpers
 */
class Smush_Image_Optimization_Skipped extends Trigger {

	/**
	 * Opt this trigger into the lazy loading path.
	 *
	 * Listens on Smush_Skipped_Dispatcher's normalized event rather than on a
	 * Smush hook directly. `wp_smush_after_smush_file` only sees failures that
	 * happen *after* Smush's pre-checks pass; the pre-check skips (size limit,
	 * file not found, unsupported mime, excluded by filter, ignored, animated,
	 * third-party veto) return early from `Media_Item_Optimizer::optimize()` and
	 * never reach it. The dispatcher covers both paths — see its docblock.
	 *
	 * Payload: ( int $attachment_id, array $error, string $phase ), where `$error`
	 * is `array( 'code' => string, 'message' => string )` — the dispatcher flattens
	 * Smush's WP_Error before emitting, because `$hook_args` is persisted verbatim
	 * as `trigger_args`. See Smush_Skipped_Dispatcher::dispatch().
	 *
	 * This replaces the scope doc's `wp_smush_no_smushit`, which no longer exists
	 * in Smush 4.2.0.
	 */
	public static function definition() {
		return self::new_definition( 'SMUSH_IMAGE_OPTIMIZATION_SKIPPED', 'SMUSH' )
			->trigger_type( 'anonymous' )
			->trigger_meta( 'SMUSH_SKIPPED_IMAGE' )
			->hook( 'automator_smush_optimization_skipped', 10, 3 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_uses_api( false );
		$this->set_sentence( esc_html_x( 'An image optimization is skipped', 'Smush', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'An image optimization is skipped', 'Smush', 'uncanny-automator' ) );
	}

	/**
	 * Define trigger options.
	 *
	 * No options — the skip reason is a free-form string with no finite enum to
	 * select from, so it is surfaced as output tokens instead.
	 *
	 * @return array[]
	 */
	public function options() {
		return array();
	}

	/**
	 * Define available tokens.
	 *
	 * @param array $trigger The trigger settings.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->item_helpers->get_image_token_definitions(),
			array(
				array(
					'tokenId'   => 'ERROR_CODE',
					'tokenName' => esc_html_x( 'Error code', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'ERROR_MESSAGE',
					'tokenName' => esc_html_x( 'Error message', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
				array(
					'tokenId'   => 'SKIP_PHASE',
					'tokenName' => esc_html_x( 'Skip phase', 'Smush', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * The dispatcher only emits for genuine skips, so this is a shape check
	 * rather than a decision — but it stays strict so a malformed third-party
	 * `do_action()` on the same hook cannot fire recipes with empty tokens.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		if ( empty( $hook_args ) ) {
			return false;
		}

		if ( 0 === absint( $hook_args[0] ?? 0 ) ) {
			return false;
		}

		// Anonymous system event — the smush may run under cron or the bulk
		// background process, so there is no actor to attribute. Intentionally
		// no set_user_id() / current-user gate.
		return '' !== $this->error_code( $hook_args );
	}

	/**
	 * The skip reason's code, or '' when the payload carries none.
	 *
	 * @param array $hook_args The hook arguments.
	 *
	 * @return string
	 */
	private function error_code( $hook_args ) {

		$error = $hook_args[1] ?? array();

		return is_array( $error ) ? (string) ( $error['code'] ?? '' ) : '';
	}

	/**
	 * The skip reason's message, or '' when the payload carries none.
	 *
	 * @param array $hook_args The hook arguments.
	 *
	 * @return string
	 */
	private function error_message( $hook_args ) {

		$error = $hook_args[1] ?? array();

		return is_array( $error ) ? (string) ( $error['message'] ?? '' ) : '';
	}

	/**
	 * Hydrate token values from hook arguments.
	 *
	 * @param array $trigger   The completed trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		$attachment_id = absint( $hook_args[0] ?? 0 );
		$phase         = (string) ( $hook_args[2] ?? '' );

		return array_merge(
			$this->item_helpers->hydrate_image_tokens( $attachment_id ),
			array(
				'ERROR_CODE'    => $this->error_code( $hook_args ),
				'ERROR_MESSAGE' => $this->error_message( $hook_args ),
				'SKIP_PHASE'    => $phase,
			)
		);
	}
}
