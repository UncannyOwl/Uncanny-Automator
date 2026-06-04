<?php

namespace Uncanny_Automator\Integrations\Wp;

/**
 * Class WP_LOGIN
 *
 * Fires when a user logs in to the site.
 *
 * @package Uncanny_Automator\Integrations\Wp
 *
 * @property Wp_Helpers $item_helpers
 */
class WP_LOGIN extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'LOGIN', 'WP' )
			->trigger_meta( 'WPLOGIN' )
			->hook( 'wp_login', 99, 2 );
	}

	/**
	 * Sets up the trigger properties and action hook.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().

		// translators: %1$s is a number of times.
		$this->set_sentence(
			sprintf(
				esc_html_x( 'A user logs in to the site {{a number of:%1$s}} time(s)', 'WordPress', 'uncanny-automator' ),
				'NUMTIMES:' . $this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence(
			esc_html_x( 'A user logs in to the site', 'WordPress', 'uncanny-automator' )
		);
	}

	/**
	 * Define trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'            => 'NUMTIMES',
				'label'                  => esc_html_x( 'Number of times', 'WordPress', 'uncanny-automator' ),
				'show_label_in_sentence' => false,
				'placeholder'            => esc_html_x( 'Example: 1', 'WordPress', 'uncanny-automator' ),
				'input_type'             => 'int',
				'default_value'          => 1,
				'min_number'             => 1,
				'required'               => true,
			),
		);
	}

	/**
	 * Define trigger tokens.
	 *
	 * @param array $trigger The trigger data.
	 * @param array $tokens  Existing tokens.
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			Wp_Shared_Tokens::user_tokens(),
			Wp_Shared_Tokens::numtimes_token()
		);
	}

	/**
	 * Validate the trigger.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_login, $user ) = $hook_args;

		if ( ! $user instanceof \WP_User || empty( $user->ID ) ) {
			return false;
		}

		$this->set_user_id( $user->ID );

		return true;
	}

	/**
	 * Hydrate trigger tokens with runtime values.
	 *
	 * @param array $trigger   The trigger data.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $user_login, $user ) = $hook_args;

		return array_merge(
			Wp_Shared_Tokens::hydrate_user_tokens( (int) $user->ID ),
			Wp_Shared_Tokens::hydrate_numtimes_token( $trigger )
		);
	}
}
