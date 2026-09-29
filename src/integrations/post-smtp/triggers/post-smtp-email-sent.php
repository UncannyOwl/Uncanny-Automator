<?php

namespace Uncanny_Automator\Integrations\Post_Smtp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Post_Smtp_Email_Sent
 *
 * Fires after Post SMTP successfully delivers an email, via any transport. The
 * hook is raised from two places — `Postman/PostmanWpMail.php:346` (non-PHPMailer
 * path) and `Postman/Phpmailer/PostsmtpMailer.php:152` (PHPMailer path) — with an
 * identical signature, so one listener covers both.
 *
 * Anonymous: `wp_mail()` runs in cron, AJAX, REST and admin contexts, so there
 * may be no logged-in user.
 *
 * @package Uncanny_Automator\Integrations\Post_Smtp
 *
 * @property Post_Smtp_Helpers $item_helpers
 */
class Post_Smtp_Email_Sent extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'POST_SMTP_EMAIL_SENT', 'POST_SMTP' )
			->trigger_meta( 'POST_SMTP_TRANSPORT' )
			->trigger_type( 'anonymous' )
			->hook( 'post_smtp_on_success', 10, 4 );
	}

	/**
	 * Setup trigger.
	 *
	 * @return void
	 */
	protected function setup_trigger() {
		$this->set_is_pro( false );
		$this->set_is_login_required( false );
		$this->set_sentence(
			sprintf(
				/* translators: %1$s: the mail transport selector. */
				esc_html_x( 'An email is sent via {{a transport:%1$s}}', 'Post SMTP', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'An email is sent via {{a transport}}', 'Post SMTP', 'uncanny-automator' ) );
	}

	/**
	 * Trigger options.
	 *
	 * @return array
	 */
	public function options() {
		return array(
			array(
				'option_code'     => $this->get_trigger_meta(),
				'label'           => esc_html_x( 'Transport', 'Post SMTP', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				// The transport actually used is exposed as its own token; the
				// field's auto-token would read "-1" whenever "Any" is selected.
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'transports' ),
			),
		);
	}

	/**
	 * Validate — the send used the selected transport.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return bool
	 */
	public function validate( $trigger, $hook_args ) {

		$selected = (string) ( $trigger['meta'][ $this->get_trigger_meta() ] ?? Post_Smtp_Helpers::ANY );

		if ( Post_Smtp_Helpers::ANY === $selected ) {
			return true;
		}

		$transport = $hook_args[3] ?? null;

		return $selected === $this->item_helpers->transport_slug( $transport );
	}

	/**
	 * Define tokens.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge( $tokens, $this->item_helpers->get_email_tokens() );
	}

	/**
	 * Hydrate tokens.
	 *
	 * @param array $trigger
	 * @param array $hook_args
	 *
	 * @return array
	 */
	public function hydrate_tokens( $trigger, $hook_args ) {

		list( $log, , $transcript, $transport ) = array_pad( $hook_args, 4, null );

		return $this->item_helpers->hydrate_email_tokens( $log, $transcript, $transport );
	}
}
