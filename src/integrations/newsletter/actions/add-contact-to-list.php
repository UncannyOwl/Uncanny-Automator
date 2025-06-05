<?php

namespace Uncanny_Automator\Integrations\Newsletter;

/**
 * Class ADD_CONTACT_TO_LIST
 * @package Uncanny_Automator
 */
class ADD_CONTACT_TO_LIST extends \Uncanny_Automator\Recipe\Action {

	/**
	 * @return mixed
	 */
	protected function setup_action() {
		$this->set_integration( 'NEWSLETTER' );
		$this->set_action_code( 'NS_ADD_CONTACT_TO_LIST' );
		$this->set_action_meta( 'NS_CONTACT' );
		$this->set_requires_user( false );
		/* translators: Action - Newsletter */
		$this->set_sentence( sprintf( esc_attr_x( 'Add {{a subscriber:%1$s}} to {{a list:%2$s}}', 'Newsletter', 'uncanny-automator' ), $this->get_action_meta(), 'NS_LIST:' . $this->get_action_meta() ) );
		/* translators: Action - Newsletter */
		$this->set_readable_sentence( esc_attr_x( 'Add {{a subscriber}} to {{a list}}', 'Newsletter', 'uncanny-automator' ) );
	}

	/**
	 * options
	 *
	 *
	 * @return array
	 */
	public function options() {
		$lists = array();
		if ( class_exists( '\Newsletter' ) ) {
			$newsletter_lists = \Newsletter::instance()->get_lists();
			if ( ! empty( $newsletter_lists ) ) {
				foreach ( $newsletter_lists as $list ) {
					$list_id = sprintf( 'list_%d', $list->id );
					$lists[] = array(
						'text'  => $list->name,
						'value' => $list_id,
					);
				}
			}
		}

		return array(
			Automator()->helpers->recipe->field->text(
				array(
					'option_code' => $this->get_action_meta(),
					'input_type'  => 'email',
					'label'       => esc_attr_x( 'Email', 'Newsletter', 'uncanny-automator' ),
				)
			),
			Automator()->helpers->recipe->field->select(
				array(
					'option_code' => 'NS_LIST',
					'label'       => esc_attr_x( 'List', 'Newsletter', 'uncanny-automator' ),
					'options'     => $lists,
				)
			),
		);
	}

	/**
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return bool
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$email      = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_email( $parsed[ $this->get_action_meta() ] ) : '';
		$list       = isset( $parsed['NS_LIST'] ) ? sanitize_text_field( $parsed['NS_LIST'] ) : '';
		$newsletter = \Newsletter::instance();
		$user       = $newsletter->get_user( $email );

		if ( empty( $user ) ) {
			$user = (object) array(
				'email'  => $email,
				'status' => \TNP_User::STATUS_CONFIRMED,
			);
		}

		$user->$list = 1;
		$user        = $newsletter->save_user( $user );
		if ( null === $user ) {
			$this->add_log_error( 'Failed to add a contact to the selected list.' );

			return false;
		}

		$newsletter->add_user_log( $user, 'subscribe' );

		return true;
	}
}
