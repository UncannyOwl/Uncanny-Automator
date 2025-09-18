<?php

namespace Uncanny_Automator\Integrations\Edd_SL;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class EDD_SL_USERS_LICENSE_EXPIRED_FOR_DOWNLOAD
 *
 * @package Uncanny_Automator
 * @method Uncanny_Automator\Integrations\Edd_SL\Edd_Sl_Helpers get_item_helpers()
 */
class EDD_SL_USERS_LICENSE_EXPIRED_FOR_DOWNLOAD extends Trigger {

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->set_integration( 'EDD_SL' );
		$this->set_trigger_code( 'EDD_SL_USERS_LICENSE_EXPIRED' );
		$this->set_trigger_meta( 'EDD_SL_LICENSES' );
		// Trigger sentence - EDD - Software Licensing
		$this->set_sentence(
			// translators: 1: Download name
			sprintf( esc_html_x( "A user's license for {{a download:%1\$s}} expires", 'EDD - Software Licensing', 'uncanny-automator' ), $this->get_trigger_meta() )
		);
		$this->set_readable_sentence( esc_html_x( "A user's license for {{a download}} expires", 'EDD - Software Licensing', 'uncanny-automator' ) );
		// Use a hook that triggers when license status changes to expired
		$this->add_action( 'edd_sl_post_set_status', 20, 2 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->select(
				array(
					'option_code'     => $this->get_trigger_meta(),
					'label'           => esc_html_x( 'Download', 'EDD - Software Licensing', 'uncanny-automator' ),
					// Load the options from the helpers file
					'options'         => $this->get_item_helpers()->get_all_downloads(),
					'relevant_tokens' => array(),
				)
			),
		);
	}

	/**
	 * @param $trigger
	 * @param $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ] ) ) {
			return false;
		}

		$license_id = $hook_args[0];
		$status     = $hook_args[1];

		// Only trigger when license status changes to 'expired'
		if ( 'expired' !== $status ) {
			return false;
		}

		$download_id          = edd_software_licensing()->get_download_id( $license_id );
		$selected_download_id = $trigger['meta'][ $this->get_trigger_meta() ];

		// Check if this is the selected download or any download
		if ( intval( '-1' ) === intval( $selected_download_id ) || (int) $download_id === (int) $selected_download_id ) {
			$user_id = $this->get_item_helpers()->get_user_id_from_license( $license_id );

			if ( $user_id ) {
				$this->set_user_id( $user_id );
			}

			return true;
		}

		return false;
	}

	/**
	 * Define Tokens.
	 *
	 * @param array $tokens
	 * @param array $trigger - options selected in the current recipe/trigger
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		$common_tokens = $this->get_item_helpers()->get_common_tokens();

		return array_merge( $tokens, $common_tokens );
	}

	/**
	 * Hydrate Tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {
		$license_id  = $hook_args[0];
		$download_id = edd_software_licensing()->get_download_id( $license_id );

		return $this->get_item_helpers()->parse_common_token_values( $license_id, $download_id );
	}
}
