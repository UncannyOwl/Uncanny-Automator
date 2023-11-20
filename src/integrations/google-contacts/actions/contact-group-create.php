<?php
namespace Uncanny_Automator\Integrations\Google_Contacts;

use Exception;

/**
 * @since 5.2
 */
class CONTACT_GROUP_CREATE extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setups the action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'GOOGLE_CONTACTS' );
		$this->set_action_code( 'GOOGLE_CONTACTS_CONTACT_GROUP_CREATE' );
		$this->set_action_meta( 'GOOGLE_CONTACTS_CONTACT_GROUP_CREATE_META' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x(
					'Create {{a label:%1$s}}',
					'Google Contacts',
					'uncanny-automator'
				),
				$this->get_action_meta(),
				'GROUP:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Create {{a label}}', 'Google Contacts', 'uncanny-automator' ) );

	}

	/**
	 * Setups the options.
	 *
	 * @return mixed[]
	 */
	public function options() {

		$label = array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'text',
			'label'       => _x( 'Label', 'Google Contacts', 'uncanny-automator' ),
			'required'    => true,
		);

		return array( $label );

	}

	/**
	 * Setups the action's process callback.
	 *
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

		try {

			$helper = new Google_Contacts_Helpers();

			$body = array(
				'action'       => 'label_create',
				'access_token' => $helper->get_client(),
				'label'        => $parsed[ $this->get_action_meta() ], // No need to sanitize as Google handles special characters well.
			);

			$helper->api_call( $body, $action_data );

		} catch ( \Exception $e ) {

			$this->add_log_error( $e->getMessage() );

			return false;

		}
	}



}
