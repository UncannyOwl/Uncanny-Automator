<?php

namespace Uncanny_Automator\Integrations\Uncanny_Toolkit;

/**
 * Trigger: A user is imported by the Import Users module.
 *
 * @property \Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers $item_helpers
 */
class UT_USER_IMPORTED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'UTUSERIMPORTED', 'UNCANNYTOOLKIT' )
			->trigger_meta( 'UOUSERIMPORTED' )
			->hook( 'uo_after_user_row_imported', 20, 4 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: Logged-in trigger sentence.
		$this->set_sentence( esc_html_x( 'A user is imported by the Import Users module', 'Uncanny Toolkit', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_html_x( 'A user is imported by the Import Users module', 'Uncanny Toolkit', 'uncanny-automator' ) );
	}

	/**
	 * Check if Uncanny Toolkit Pro is active.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return defined( 'UNCANNY_TOOLKIT_PRO_VERSION' );
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

		$new_tokens = array(
			array(
				'tokenId'   => 'user_id',
				'tokenName' => esc_html_x( 'Imported user ID', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'user_login',
				'tokenName' => esc_html_x( 'Imported user login', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'user_email',
				'tokenName' => esc_html_x( 'Imported user email', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'first_name',
				'tokenName' => esc_html_x( 'Imported user first name', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'last_name',
				'tokenName' => esc_html_x( 'Imported user last name', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'display_name',
				'tokenName' => esc_html_x( 'Imported user display name', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'wp_role',
				'tokenName' => esc_html_x( 'Imported user WordPress role', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);

		if ( defined( 'LEARNDASH_VERSION' ) ) {
			$new_tokens[] = array(
				'tokenId'   => 'learndash_course_ids',
				'tokenName' => esc_html_x( 'Course ID(s)', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
			$new_tokens[] = array(
				'tokenId'   => 'learndash_course_titles',
				'tokenName' => esc_html_x( 'Course title(s)', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
			$new_tokens[] = array(
				'tokenId'   => 'learndash_group_ids',
				'tokenName' => esc_html_x( 'Group ID(s)', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
			$new_tokens[] = array(
				'tokenId'   => 'learndash_group_titles',
				'tokenName' => esc_html_x( 'Group title(s)', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'text',
			);
		}

		return array_merge( $new_tokens, $tokens );
	}

	/**
	 * Validate trigger against hook arguments.
	 *
	 * @param array $trigger   The trigger settings.
	 * @param array $hook_args The hook arguments.
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		list( $user_id ) = $hook_args;

		if ( ! is_numeric( $user_id ) ) {
			return false;
		}

		return true;
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

		list( $user_id, $csv_data, $csv_header, $key_location ) = $hook_args;

		$meta_value = Ut_Helpers::build_token_data( $csv_data, $csv_header, $key_location, $user_id );

		$tokens = array();

		if ( is_array( $meta_value ) ) {
			foreach ( $meta_value as $key => $val ) {
				if ( is_array( $val ) ) {
					$tokens[ $key ] = implode( ' | ', $val );
				} else {
					$tokens[ $key ] = wp_strip_all_tags( $val );
				}
			}
		}

		return $tokens;
	}
}
