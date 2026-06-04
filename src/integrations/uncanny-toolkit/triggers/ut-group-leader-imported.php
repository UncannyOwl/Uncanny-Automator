<?php

namespace Uncanny_Automator\Integrations\Uncanny_Toolkit;

/**
 * Trigger: A Group Leader is imported to a LearnDash group.
 *
 * @property \Uncanny_Automator\Integrations\Uncanny_Toolkit\Ut_Helpers $item_helpers
 */
class UT_GROUP_LEADER_IMPORTED extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'UTGROUPLEADERIMPORTED', 'UNCANNYTOOLKIT' )
			->trigger_meta( 'UOGROUPLEADERIMPORTED' )
			->hook( 'uo_after_user_row_imported', 20, 4 );
	}

	/**
	 * Setup trigger configuration.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		// integration / code / trigger_meta / trigger_type are auto-applied from definition().
		// translators: %1$s is a LearnDash group.
		$this->set_sentence( sprintf( esc_html_x( 'A Group Leader is imported to {{a LearnDash group:%1$s}}', 'Uncanny Toolkit', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_html_x( 'A Group Leader is imported to {{a LearnDash group}}', 'Uncanny Toolkit', 'uncanny-automator' ) );
	}

	/**
	 * Check if LearnDash and Toolkit Pro are active.
	 *
	 * @return bool
	 */
	public function requirements_met() {
		return defined( 'LEARNDASH_VERSION' ) && defined( 'UNCANNY_TOOLKIT_PRO_VERSION' );
	}

	/**
	 * Define trigger options.
	 *
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'option_code' => $this->get_trigger_meta(),
				'label'       => esc_html_x( 'Group', 'Uncanny Toolkit', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => true,
				'remote_data' => $this->item_helpers->remote_data_load_config( 'groups' ),
				'options'     => array(),
			),
		);
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
			array(
				'tokenId'   => 'learndash_group_id',
				'tokenName' => esc_html_x( 'Group ID', 'Uncanny Toolkit', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);

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

		list( $user_id, $csv_data, $csv_header, $key_location ) = $hook_args;

		if ( ! is_numeric( $user_id ) ) {
			return false;
		}

		$meta_value = Ut_Helpers::build_token_data( $csv_data, $csv_header, $key_location, $user_id );

		if ( ! isset( $meta_value['learndash_group_leader_ids'] ) || empty( $meta_value['learndash_group_leader_ids'] ) ) {
			return false;
		}

		$selected_group = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		// "Any group" sentinel.
		if ( '-1' === $selected_group ) {
			return true;
		}

		foreach ( $meta_value['learndash_group_leader_ids'] as $group_id ) {
			if ( (int) $selected_group === (int) $group_id ) {
				return true;
			}
		}

		return false;
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

		$tokens         = array();
		$selected_group = $trigger['meta'][ $this->get_trigger_meta() ] ?? '';

		if ( is_array( $meta_value ) ) {
			foreach ( $meta_value as $key => $val ) {
				if ( is_array( $val ) ) {
					$tokens[ $key ] = implode( ' | ', $val );
				} else {
					$tokens[ $key ] = wp_strip_all_tags( $val );
				}
			}
		}

		$group_id = 0;
		if ( '-1' === $selected_group && ! empty( $meta_value['learndash_group_leader_ids'] ) ) {
			$group_id = reset( $meta_value['learndash_group_leader_ids'] );
		} elseif ( ! empty( $selected_group ) ) {
			$group_id = $selected_group;
		}

		$tokens['learndash_group_id']        = $group_id;
		$tokens[ $this->get_trigger_meta() ] = get_the_title( $group_id );

		return $tokens;
	}
}
