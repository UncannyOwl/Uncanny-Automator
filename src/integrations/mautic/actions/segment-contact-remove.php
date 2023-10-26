<?php
namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\Api_Server;

/**
 * @since 5.0
 */
class SEGMENT_CONTACT_REMOVE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( Mautic_Integration::ID );
		$this->set_action_code( 'SEGMENT_CONTACT_REMOVE' );
		$this->set_action_meta( 'SEGMENT_CONTACT_REMOVE_META' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x(
					'Remove {{a contact:%1$s}} from {{a segment:%2$s}}',
					'Mautic',
					'uncanny-automator'
				),
				$this->get_action_meta(),
				'SEGMENT:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Remove {{a contact}} from {{a segment}}', 'Mautic', 'uncanny-automator' ) );

		$this->set_action_tokens(
			array(
				'SEGMENT_NAME' => array(
					'name' => _x( 'Segment name', 'Mautic', 'uncanny-automator' ),
					'type' => 'text',
				),
			),
			$this->get_action_code()
		);

	}

	/**
	 * @return mixed[]
	 */
	public function options() {

		$email = array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'email',
			'label'       => _x( 'Email', 'Mautic', 'uncanny-automator' ),
			'required'    => true,
		);

		$segment = array(
			'option_code' => 'SEGMENT',
			'input_type'  => 'select',
			'label'       => _x( 'Segment', 'Mautic', 'uncanny-automator' ),
			'token_name'  => _x( 'Segment ID', 'Mautic', 'uncanny-automator' ),
			'required'    => true,
			'ajax'        => array(
				'endpoint' => 'automator_mautic_segment_fetch',
				'event'    => 'on_load',
			),
		);

		return array(
			$email,
			$segment,
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

		$segment = ! empty( $parsed['SEGMENT'] ) ? absint( $parsed['SEGMENT'] ) : '';
		$email   = ! empty( $parsed[ $this->get_action_meta() ] ) ? $parsed[ $this->get_action_meta() ] : '';

		// Invalid email. Complete with error.
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \Exception( 'Invalid email. "' . $email . '"', 500 );
		}

		$auth->api_call(
			array(
				'action'      => 'segment_contact_remove',
				'segment_id'  => $segment,
				'contact'     => rawurlencode( $email ),
				'credentials' => $credentials,
			)
		);

		$this->hydrate_tokens(
			array(
				'SEGMENT_NAME' => $args['action_meta']['SEGMENT_readable'],
			),
			$action_data
		);

		return true;

	}

}
