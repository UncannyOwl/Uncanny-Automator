<?php
namespace Uncanny_Automator\Integrations\Google_Contacts;

use Exception;

/**
 * @since 5.2
 */
class CONTACT_GROUP_ADD_TO extends \Uncanny_Automator\Recipe\Action {

	/**
	 * Setups the action.
	 *
	 * @return void
	 */
	protected function setup_action() {

		$this->set_integration( 'GOOGLE_CONTACTS' );
		$this->set_action_code( 'GOOGLE_CONTACTS_CONTACT_GROUP_ADD_TO' );
		$this->set_action_meta( 'GOOGLE_CONTACTS_CONTACT_GROUP_ADD_TO_META' );
		$this->set_requires_user( false );
		$this->set_sentence(
			sprintf(
				/* translators: Action sentence */
				esc_attr_x(
					'Add {{a label:%2$s}} to {{a contact:%1$s}}',
					'Google Contacts',
					'uncanny-automator'
				),
				$this->get_action_meta(),
				'GROUP:' . $this->get_action_meta()
			)
		);
		$this->set_readable_sentence( esc_attr_x( 'Add {{a label}} to {{a contact}}', 'Google Contacts', 'uncanny-automator' ) );

	}

	/**
	 * Setups the options.
	 *
	 * @return mixed[]
	 */
	public function options() {

		$email = array(
			'option_code' => $this->get_action_meta(),
			'input_type'  => 'email',
			'label'       => _x( 'Email', 'Google Contacts', 'uncanny-automator' ),
			'required'    => true,
		);

		$label = array(
			'option_code' => 'GROUP',
			'input_type'  => 'select',
			'label'       => _x( 'Label', 'Google Contacts', 'uncanny-automator' ),
			'required'    => true,
			'ajax'        => array(
				'endpoint' => 'automator_google_contacts_fetch_labels',
				'event'    => 'on_load',
			),
		);

		return array( $email, $label );

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
				'action'       => 'contact_label_add',
				'access_token' => $helper->get_client(),
				'email'        => $parsed[ $this->get_action_meta() ],
				'group_id'     => $parsed['GROUP'],
			);

			$helper->api_call( $body, $action_data );

		} catch ( \Exception $e ) {

			$this->add_log_error( $e->getMessage() );

			return false;

		}

		return true;

	}



}
