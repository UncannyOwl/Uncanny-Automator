<?php

namespace Uncanny_Automator\Integrations\Duplicator;

/**
 * Class BACKUP_COMPLETES_WITH_STATUS
 *
 * @package Uncanny_Automator
 */
class BACKUP_COMPLETES_WITH_STATUS extends \Uncanny_Automator\Recipe\Trigger {

	/**
	 * @return mixed
	 */
	protected function setup_trigger() {
		$this->set_integration( 'DUPLICATOR' );
		$this->set_trigger_code( 'BACKUP_COMPLETES_WITH_STATUS' );
		$this->set_trigger_meta( 'DUPLICATOR_BACKUP_STATUS' );
		$this->set_trigger_type( 'anonymous' );
		// Trigger sentence - Duplicator
		// translators: 1: Status
		$this->set_sentence( sprintf( esc_attr_x( 'A backup completes with {{a specific status:%1$s}}', 'Duplicator', 'uncanny-automator' ), $this->get_trigger_meta() ) );
		$this->set_readable_sentence( esc_attr_x( 'A backup completes with {{a specific status}}', 'Duplicator', 'uncanny-automator' ) );
		$this->add_action(
			array(
				'duplicator_package_after_set_status',
				'duplicator_pro_package_after_set_status',
			),
			10,
			2
		);
	}

	/**
	 * @return array[]
	 */
	public function options() {
		return array(
			array(
				'input_type'      => 'select',
				'option_code'     => $this->get_trigger_meta(),
				'label'           => _x( 'Status', 'Duplicator', 'uncanny-automator' ),
				'default_value'   => null,
				'required'        => true,
				'options'         => array(
					array(
						'value' => '100',
						'text'  => esc_attr_x( 'Package build completed', 'Duplicator', 'uncanny-automator' ),
					),
					array(
						'value' => '-1',
						'text'  => esc_attr_x( 'Failed with error', 'Duplicator', 'uncanny-automator' ),
					),
				),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * validate
	 *
	 * @param mixed $trigger
	 * @param mixed $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {
		if ( ! isset( $trigger['meta'][ $this->get_trigger_meta() ], $hook_args[1] ) ) {
			return false;
		}

		$status          = $hook_args[1];
		$selected_status = $trigger['meta'][ $this->get_trigger_meta() ];

		return (int) $selected_status === (int) $status;
	}

	/**
	 * define_tokens
	 *
	 * Override this method if you want to add recipe-specific tokens such as form fields etc.
	 *
	 * @param mixed $tokens
	 * @param mixed $args
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array(
			array(
				'tokenId'   => 'BACKUP_STATUS',
				'tokenName' => esc_html__( 'Status', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'BACKUP_FILENAME',
				'tokenName' => esc_html__( 'Backup name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			//          array(
			//              'tokenId'   => 'BACKUP_ERROR',
			//              'tokenName' => esc_html__( 'Error', 'uncanny-automator' ),
			//              'tokenType' => 'text',
			//          ),
		);
	}

	/**
	 * hydrate_tokens
	 *
	 * @param mixed $completed_trigger
	 * @param mixed $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $completed_trigger, $hook_args ) {
		/** @var \DUP_Package $package */
		list( $package, $status ) = $hook_args;
		$status                   = ( 100 === $status ) ? 'Backup completed' : 'Failed with error';

		return array(
			'BACKUP_STATUS'   => $status,
			'BACKUP_FILENAME' => $package->Archive->File,
			//          'BACKUP_ERROR'        => '',
		);
	}
}
