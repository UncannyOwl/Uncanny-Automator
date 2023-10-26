<?php
namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\Api_Server;

/**
 * @since 5.0
 */
class CONTACT_UPSERT extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( Mautic_Integration::ID );
		$this->set_action_code( 'CONTACT_UPSERT' );
		$this->set_action_meta( 'CONTACT_UPSERT_META' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x(
					'Create or update {{a contact:%1$s}}',
					'Mautic',
					'uncanny-automator'
				),
				'NON_EXISTING:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create or update {{a contact}}', 'Mautic', 'uncanny-automator' ) );

	}

	/**
	 * @return mixed[]
	 */
	public function options() {

		$email = array(
			'option_code' => 'EMAIL',
			'input_type'  => 'email',
			'label'       => _x( 'Email', 'Mautic', 'uncanny-automator' ),
			'required'    => true,
		);

		$fields = array(
			'option_code'   => 'FIELDS',
			'input_type'    => 'repeater',
			'label'         => _x( 'Field', 'Mautic', 'uncanny-automator' ),
			'description'   => '',
			'required'      => true,
			'default_value' => array(
				array(
					'ALIAS' => _x( 'Loading fields...', 'Mautic', 'uncanny-automator' ),
					'VALUE' => _x( 'Loading values...', 'Mautic', 'uncanny-automator' ),
				),
			),
			'fields'        => array(
				array(
					'option_code' => 'ALIAS',
					'label'       => _x( 'Field', 'Mautic', 'uncanny-automator' ),
					'input_type'  => 'text',
					'read_only'   => true,
				),
				array(
					'option_code' => 'VALUE',
					'label'       => _x( 'Value', 'Mautic', 'uncanny-automator' ),
					'input_type'  => 'text',
				),
			),
			'ajax'          => array(
				'event'          => 'on_load',
				'endpoint'       => 'automator_mautic_render_contact_fields',
				'mapping_column' => 'ALIAS',
			),
			'hide_actions'  => true,
		);

		return array(
			$email,
			$fields,
		);

	}

	/**
	 * @param int $user_id
	 * @param mixed[] $action_data
	 * @param int $recipe_id
	 * @param mixed[] $args
	 * @param array{FIELDS:string,EMAIL:string} $parsed
	 *
	 * @throws \Exception
	 *
	 * @return bool True if the action is successful. Returns false, otherwise.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {

		$auth = new Mautic_Client_Auth( Api_Server::get_instance() );

		$credentials = $auth->get_credentials();

		$fields = ! empty( $parsed['FIELDS'] ) ? (array) json_decode( $parsed['FIELDS'], true ) : array();
		$email  = ! empty( $parsed['EMAIL'] ) ? $parsed['EMAIL'] : '';

		// Invalid email. Complete with error.
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( 'Invalid email. "' . $email . '"', 500 );
		}

		// Empty fields. Complete with error.
		if ( empty( $fields ) ) {
			throw new \Exception( 'Fields should not be empty', 500 );
		}

		$fields_item = array(
			'email'              => $email,
			'overwriteWithBlank' => false, // Skip blank fields
		);

		foreach ( $fields as $field ) {
			$field = (array) $field;
			if ( isset( $field['ALIAS'] ) && isset( $field['VALUE'] ) ) {
				$fields_item[ $field['ALIAS'] ] = $field['VALUE'];
			}
		}

		$fields_item_json = wp_json_encode( $fields_item );

		$auth->api_call(
			array(
				'action'      => 'upsert',
				'fields'      => $fields_item_json,
				'credentials' => $credentials,
			),
			$action_data
		);

		return true;

	}

}
