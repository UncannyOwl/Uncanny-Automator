<?php
// phpcs:ignoreFile -- Ignored due to false positive on translation comment detection (Uncanny_Automator.Strings.TranslatorComment.MissingTranslatorComment).
/**
 * MailPoet Add Tag
 *
 * Adds a tag to a subscriber in MailPoet
 * 
 * 
 *
 * @class   MAILPOET_ADDTAG
 */

namespace Uncanny_Automator;

/**
 * Class MAILPOET_ADDTAG
 *
 * @package Uncanny_Automator
 */
class MAILPOET_ADDTAG extends \Uncanny_Automator\Recipe\Action {

	protected $helpers;

	/**
	 * Setup action
	 *
	 * @return void
	 */
	protected function setup_action() {
		$this->helpers = new Mailpoet_Helpers();
		$this->set_integration( 'MAILPOET' );
		$this->set_action_code( 'MAILPOET_ADD_TAG' );
		$this->set_action_meta( 'MAILPOET_ADD_TAG_META' );
		$this->set_requires_user( false );
				$this->set_sentence(
					sprintf(
					/* translators: $1%s: new tag for subscriber */
						esc_attr_x( 'Add {{a tag:%1$s}} to the subscriber', 'MailPoet', 'uncanny-automator' ),
						$this->get_action_meta()
					)
				);

		$this->set_readable_sentence( esc_attr_x( 'Add {{a tag}} to the subscriber', 'MailPoet', 'uncanny-automator' ) );
	}

	/**
	 * Define options
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => 'MAILPOET_SUBSCRIBER_EMAIL_ADD_TAG',
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
	 * Processes the action of adding a tag to a MailPoet subscriber.
	 *
	 * MailPoet's API currently does not support updating subscriber tags via `updateSubscriber`,
	 * and there is no official method provided to manage tags directly.
	 *
	 * As a workaround, this function inserts the tag into the `mailpoet_subscriber_tag` table
	 * using direct database access to ensure the tag is added.
	 *
	 * @param int   $user_id     The ID of the WordPress user.
	 * @param array $action_data Data related to the action being processed.
	 * @param int   $recipe_id   The ID of the recipe associated with the action.
	 * @param array $args        Additional arguments for processing the action.
	 * @param array $parsed      Parsed information relevant to the action.
	 *
	 * @return bool True on success, false on failure.
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		// Check if MailPoet is available.
		if ( ! class_exists( '\MailPoet\API\API' ) ) {
			$this->add_log_error( 'MailPoet API is not available.' );
			return false;
		}

		$tag_id = (int) $action_data['meta'][ $this->get_action_meta() ];
		
		// Get subscriber email from multiple sources
		$subscriber_email = '';
		
		// First, try to get email from action data if provided
		if ( isset( $action_data['meta']['MAILPOET_SUBSCRIBER_EMAIL_ADD_TAG'] ) && ! empty( $action_data['meta']['MAILPOET_SUBSCRIBER_EMAIL_ADD_TAG'] ) ) {
			$subscriber_email = Automator()->parse->text( $action_data['meta']['MAILPOET_SUBSCRIBER_EMAIL_ADD_TAG'], $recipe_id, $user_id, $args );
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

		if ( $this->helpers->check_tag_exists( $subscriber, $tag_id ) ) {
			return true;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'mailpoet_subscriber_tag';

		$result = $wpdb->insert(
			$table_name,
			array(
				'subscriber_id' => $subscriber['id'],
				'tag_id'        => $tag_id,
				'created_at'    => current_time( 'mysql' ),
				'updated_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			$this->add_log_error( 'Failed to assign tag to subscriber.' );
			return false;
		}

		return true;
	}
}
