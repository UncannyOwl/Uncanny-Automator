<?php

namespace Uncanny_Automator\Integrations\Duplicator;

/**
 * Class INITIATE_A_BACKUP
 * @package Uncanny_Automator
 */
class INITIATE_A_BACKUP extends \Uncanny_Automator\Recipe\Action {


	/**
	 * @return mixed
	 */
	protected function setup_action() {
		if ( ! defined( 'DUPLICATOR_PRO_VERSION' ) ) {
			return;
		}

		$this->set_integration( 'DUPLICATOR' );
		$this->set_action_code( 'DUP_CREATE_BACKUP' );
		$this->set_action_meta( 'CREATE_A_BACKUP' );
		$this->set_requires_user( false );
		$this->set_sentence( esc_attr_x( 'Initiate a backup', 'Duplicator', 'uncanny-automator' ) );
		$this->set_readable_sentence( esc_attr_x( 'Initiate a backup', 'Duplicator', 'uncanny-automator' ) );
	}


	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return mixed
	 */
	public function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		if ( ! class_exists( '\DUP_PRO_Package' ) ) {
			$this->add_log_error( esc_attr_x( 'Duplicator Pro not found.', 'Duplicator', 'uncanny-automator' ) );

			return false;
		}

		$package     = new \DUP_PRO_Package();
		$check_build = $package->run_build();

		if ( $check_build->build_progress->failed === true ) {
			$this->add_log_error( esc_attr_x( 'Failed to process build.', 'Duplicator', 'uncanny-automator' ) );

			return false;
		}

		return true;
	}

}
