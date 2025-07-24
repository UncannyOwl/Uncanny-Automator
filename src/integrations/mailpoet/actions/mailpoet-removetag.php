<?php
// phpcs:ignoreFile -- Ignored due to false positive on translation comment detection (Uncanny_Automator.Strings.TranslatorComment.MissingTranslatorComment).
/**
 * MailPoet Remove Tag
 *
 * Removes a tag from a subscriber in MailPoet
 *
 * @class   MAILPOET_REMOVETAG
 */

namespace Uncanny_Automator;

/**
 * Class MAILPOET_REMOVETAG
 */
class MAILPOET_REMOVETAG extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	/**
	 * Setup action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->helpers = new Mailpoet_Helpers();
		$this->set_integration( 'MAILPOET' );
		$this->set_action_code( 'MAILPOET_REMOVE_TAG' );
		$this->set_action_meta( 'MAILPOET_REMOVE_TAG_META' );
		$this->set_requires_user( false );
				$this->set_sentence(
					sprintf(
					/* translators: $1%s: tag to remove from subscriber */
						esc_attr_x( 'Remove {{a tag:%1$s}} from the subscriber', 'MailPoet', 'uncanny-automator' ),
						$this->get_action_meta()
					)
				);

		$this->set_readable_sentence( esc_attr_x( 'Remove {{a tag}} from the subscriber', 'MailPoet', 'uncanny-automator' ) );
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => 'MAILPOET_SUBSCRIBER_EMAIL_REMOVE_TAG',
				'label'           => esc_html_x( 'Subscriber email', 'MailPoet', 'uncanny-automator' ),
				'input_type'      => 'email',
				'required'        => true,
				'supports_tokens' => true,
				'relevant_tokens' => array(
					'MAILPOETFORMS_EMAIL' =>  esc_html_x( 'Form email', 'MailPoet', 'uncanny-automator' ),
				),
			),
			array(
				'option_code'     => $this->get_action_meta(),
				'label'           => esc_html_x( 'Tag', 'MailPoet', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->helpers->get_mailpoet_tag_options(),
				'relevant_tokens' => array(),
			),
		);
	}

	/**
	 * Process the action.
	 *
	 * @param int   $user_id     User ID.
	 * @param array $action_data Action meta data.
	 * @param int   $recipe_id   Recipe ID.
	 * @param array $args        Trigger args.
	 * @param array $parsed      Parsed data.
	 *
	 * @return bool|null
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		if ( ! class_exists( '\MailPoet\API\API' ) ) {
			$this->add_log_error( 'MailPoet API is not available.' );
			return false;
		}

		$tag_id = (int) $action_data['meta'][ $this->get_action_meta() ];
		
		// Get subscriber email from multiple sources
		$subscriber_email = '';
		
		// First, try to get email from action data if provided
		if ( isset( $action_data['meta']['MAILPOET_SUBSCRIBER_EMAIL_REMOVE_TAG'] ) && ! empty( $action_data['meta']['MAILPOET_SUBSCRIBER_EMAIL_REMOVE_TAG'] ) ) {
			$subscriber_email = Automator()->parse->text( $action_data['meta']['MAILPOET_SUBSCRIBER_EMAIL_REMOVE_TAG'], $recipe_id, $user_id, $args );
		}
		
		// If no email from action data, try to get from WordPress user
		if ( empty( $subscriber_email ) && $user_id > 0 ) {
			$wp_user = get_user_by( 'id', $user_id );
			if ( $wp_user ) {
				$subscriber_email = $wp_user->user_email;
			}
		}
		
		// If still no email, try to get from trigger tokens
		if ( empty( $subscriber_email ) && isset( $args['trigger_log_id'] ) ) {
			$trigger_log_entry = Automator()->db->trigger->get( $args['trigger_log_id'] );
			if ( $trigger_log_entry ) {
				$subscriber_email = Automator()->db->token->get( 'MAILPOETFORMS_EMAIL', $trigger_log_entry );
			}
		}
		
		// If no email found, return error
		if ( empty( $subscriber_email ) ) {
			$this->add_log_error( 'No subscriber email found. Please provide an email address or ensure the trigger provides an email.' );
			return false;
		}
		
		$mailpoet_api = \MailPoet\API\API::MP( 'v1' );
		$subscriber   = $mailpoet_api->getSubscriber( $subscriber_email );

		if ( ! $subscriber ) {
			$this->add_log_error( 'Subscriber not found for email: ' . $subscriber_email );
			return false;
		}

		$subscriber_id = (int) $subscriber['id'];

		if ( $this->helpers->check_tag_exists( $subscriber, $tag_id ) ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'mailpoet_subscriber_tag';

			$result = $wpdb->delete(
				$table_name,
				array(
					'subscriber_id' => $subscriber_id,
					'tag_id'        => $tag_id,
				),
				array( '%d', '%d' )
			);

			if ( false === $result ) {
				$this->add_log_error( 'Failed to remove tag from subscriber due to DB error.' );
				return false;
			}
		}

		return true;
	}
}
