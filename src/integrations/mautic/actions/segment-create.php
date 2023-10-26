<?php
namespace Uncanny_Automator\Integrations\Mautic;

use Uncanny_Automator\Api_Server;

/**
 * Class SEGMENT_CREATE
 *
 * @since 5.0
 */
class SEGMENT_CREATE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( Mautic_Integration::ID );
		$this->set_action_code( 'SEGMENT_CREATE' );
		$this->set_action_meta( 'SEGMENT_CREATE_META' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x(
					'Create {{a segment:%1$s}}',
					'Mautic',
					'uncanny-automator'
				),
				$this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create {{a segment}}', 'Mautic', 'uncanny-automator' ) );

	}

	/**
	 * @return mixed[]
	 */
	public function options() {

		$name = array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'text',
			'label'       => _x( 'Name', 'Mautic', 'uncanny-automator' ),
			'required'    => true,
		);

		$alias = array(
			'option_code' => 'ALIAS',
			'input_type'  => 'text',
			'label'       => _x( 'Alias', 'Mautic', 'uncanny-automator' ),
		);

		$description = array(
			'option_code' => 'DESCRIPTION',
			'input_type'  => 'textarea',
			'label'       => _x( 'Description', 'Mautic', 'uncanny-automator' ),
		);

		return array(
			$name,
			$alias,
			$description,
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

		$name        = ! empty( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$alias       = ! empty( $parsed['ALIAS'] ) ? sanitize_text_field( $parsed['ALIAS'] ) : '';
		$description = ! empty( $parsed['DESCRIPTION'] ) ? sanitize_textarea_field( $parsed['DESCRIPTION'] ) : '';

		$body = array(
			'action'      => 'segment_create',
			'credentials' => $credentials,
			'name'        => $name,
			'alias'       => $alias,
			'description' => $description,
		);

		$auth->api_call( $body, $action_data );

		return true;

	}

}
