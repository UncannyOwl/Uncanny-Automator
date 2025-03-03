<?php

namespace Uncanny_Automator\Integrations\Edd_SL;

/**
 * Class EDD_SL_LICENSE_CREATED_FOR_DOWNLOAD
 *
 * @package Uncanny_Automator
 */
class EDD_SL_LICENSE_CREATED_FOR_DOWNLOAD extends \Uncanny_Automator\Recipe\Trigger {

	protected $helpers;

	/**
	 * @return mixed|void
	 */
	protected function setup_trigger() {
		$this->helpers = array_shift( $this->dependencies );
		$this->set_integration( 'EDD_SL' );
		$this->set_trigger_code( 'EDD_SL_LICENSE_CREATED' );
		$this->set_trigger_meta( 'EDD_SL_LICENSES' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - EDD - Software Licensing
		// translators: 1: Download name
		$this->set_sentence( sprintf( esc_attr_x( 'A license for {{a download:%1$s}} is created', 'EDD - Software Licensing', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A license for {{a download}} is created', 'EDD - Software Licensing', 'uncanny-automator' ) );
		$this->add_action( 'edd_sl_store_license', 20, 4 );
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			Automator()->helpers->recipe->field->select(
				array(
					'option_code'     => $this->get_trigger_meta(),
					'label'           => esc_attr_x( 'Download', 'EDD - Software Licensing', 'uncanny-automator' ),
					// Load the options from the helpers file
					'options'         => $this->helpers->get_all_downloads(),
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

		$download_id          = $hook_args[1];
		$selected_download_id = $trigger['meta'][ $this->get_trigger_meta() ];

		if ( intval( '-1' ) === intval( $selected_download_id ) || (int) $download_id === (int) $selected_download_id ) {
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
		$common_tokens = $this->helpers->get_common_tokens();

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
		list( $license_id, $download_id, $order_id, $type ) = $hook_args;

		return $this->helpers->parse_common_token_values( $license_id, $download_id );
	}
}
