<?php
//phpcs:disable PHPCompatibility.Operators.NewOperators.t_coalesceFound

namespace Uncanny_Automator\Integrations\Aweber;

use Exception;

/**
 * Class AWEBER_SUBSCRIBER_ADD
 *
 * @package Uncanny_Automator
 */
class AWEBER_SUBSCRIBER_ADD extends \Uncanny_Automator\Recipe\Action {

	public $prefix = 'AWEBER_SUBSCRIBER_ADD';

	/**
	 * Spins up new action inside "AWEBER" integration.
	 *
	 * @return void
	 */
	public function setup_action() {

		$this->helpers = array_shift( $this->dependencies );

		$this->set_integration( 'AWEBER' );
		$this->set_action_code( $this->prefix . '_CODE' );
		$this->set_action_meta( $this->prefix . '_META' );
		$this->set_is_pro( false );
		$this->set_support_link( Automator()->get_author_support_link( $this->action_code, 'knowledge-base/aweber/' ) );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s Contact Email, %2$s List*/
				esc_attr_x( 'Add {{a subscriber:%1$s}} to a {{list:%2$s}}', 'AWeber', 'uncanny-automator' ),
				'NON_EXISTING:' . $this->get_action_meta(),
				'LIST:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a subscriber}} to a {{list}}', 'AWeber', 'uncanny-automator' ) );
		$this->set_background_processing( true );
	}

	/**
	 * Define options.
	 *
	 * @return array
	 */
	public function options() {

		return array(
			array(
				'option_code' => 'ACCOUNT',
				'label'       => _x( 'Account', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'required'    => true,
				'ajax'        => array(
					'endpoint' => 'automator_aweber_accounts_fetch',
					'event'    => 'on_load',
				),
			),
			array(
				'option_code' => 'LIST',
				'label'       => _x( 'List', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'select',
				'options'     => array(),
				'required'    => true,
				'ajax'        => array(
					'endpoint'      => 'automator_aweber_list_fetch',
					'event'         => 'parent_fields_change',
					'listen_fields' => array( 'ACCOUNT' ),
				),
			),
			array(
				'option_code' => $this->get_action_meta(),
				'label'       => _x( 'Name', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'text',
				'required'    => true,
			),
			array(
				'option_code' => 'EMAIL',
				'label'       => _x( 'Email', 'AWeber', 'uncanny-automator' ),
				'input_type'  => 'email',
				'required'    => true,
			),
			array(
				'option_code'     => 'CUSTOM_FIELDS',
				'input_type'      => 'repeater',
				'relevant_tokens' => array(),
				'label'           => esc_html__( 'Custom fields', 'uncanny-automator' ),
				'required'        => false,
				'fields'          => array(
					array(
						'input_type'  => 'text',
						'option_code' => 'FIELD_ID',
						'label'       => esc_html__( 'ID', 'uncanny-automator' ),
						'read_only'   => true,
					),
					array(
						'input_type'  => 'text',
						'option_code' => 'FIELD_NAME',
						'label'       => esc_html__( 'Name', 'uncanny-automator' ),
						'read_only'   => true,
					),
					array(
						'input_type'  => 'text',
						'option_code' => 'FIELD_VALUE',
						'label'       => esc_html__( 'Name', 'uncanny-automator' ),
						'read_only'   => false,
					),

				),
				'hide_actions'    => true,
				'ajax'            => array(
					'event'          => 'parent_fields_change',
					'listen_fields'  => array( 'LIST' ),
					'endpoint'       => 'automator_aweber_custom_fields_fetch',
					'mapping_column' => 'FIELD_ID',
				),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int $user_id
	 * @param array $action_data
	 * @param int $recipe_id
	 * @param array $args
	 * @param array $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$account_id    = $parsed['ACCOUNT'] ?? '';
		$list_id       = $parsed['LIST'] ?? '';
		$name          = $parsed[ $this->get_action_meta() ] ?? '';
		$email         = $parsed['EMAIL'] ?? '';
		$custom_fields = (array) json_decode( $action_data['maybe_parsed']['CUSTOM_FIELDS'], true );

		$custom_fields_processed = array();

		foreach ( $custom_fields as $custom_field ) {
			$custom_fields_processed[ $custom_field['FIELD_NAME'] ] = $custom_field['FIELD_VALUE'];
		}

		try {

			if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
				/* translators: Email address */
				throw new Exception(
					sprintf(
						// translators: 1. Email address
						esc_html__( 'The email address [%s] is invalid', 'uncanny-automator' ),
						esc_html( $email )
					)
				);
			}

			$body = array(
				'action'        => 'add_subscriber',
				'account_id'    => $account_id,
				'list_id'       => $list_id,
				'name'          => sanitize_text_field( $name ),
				'email'         => $email,
				'custom_fields' => wp_json_encode( $custom_fields_processed ),
			);

			$this->helpers->api_request( $body, $action_data );

			return true;

		} catch ( Exception $e ) {
			throw new Exception( esc_html( $e->getMessage() ) );
		}
	}
}
