<?php

namespace Uncanny_Automator\Integrations\Post_Smtp;

use Uncanny_Automator\Recipe\Trigger;

/**
 * Class Post_Smtp_Email_Failed
 *
 * Fires when Post SMTP catches a delivery exception, via any transport. Raised
 * from `Postman/PostmanWpMail.php:374` and
 * `Postman/Phpmailer/PostsmtpMailer.php:163` with an identical signature.
 *
 * Note: this hook fires BEFORE any fallback transport is attempted. If a
 * fallback is configured and succeeds, `post_smtp_on_success` also fires for the
 * same email — so one message can legitimately run both triggers.
 *
 * Anonymous: `wp_mail()` runs in cron, AJAX, REST and admin contexts.
 *
 * @package Uncanny_Automator\Integrations\Post_Smtp
 *
 * @property Post_Smtp_Helpers $item_helpers
 */
class Post_Smtp_Email_Failed extends Trigger {

	/**
	 * Static definition — opts the trigger into lazy loading.
	 *
	 * @return \Uncanny_Automator\Recipe\Trigger_Definition
	 */
	public static function definition() {
		return self::new_definition( 'POST_SMTP_EMAIL_FAILED', 'POST_SMTP' )
			->trigger_meta( 'POST_SMTP_FAILED_TRANSPORT' )
			->trigger_type( 'anonymous' )
			->hook( 'post_smtp_on_failed', 10, 5 );
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
				esc_html_x( 'An email fails to send via {{a transport:%1$s}}', 'Post SMTP', 'uncanny-automator' ),
				$this->get_trigger_meta()
			)
		);
		$this->set_readable_sentence( esc_html_x( 'An email fails to send via {{a transport}}', 'Post SMTP', 'uncanny-automator' ) );
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
				'relevant_tokens' => array(),
				'remote_data'     => $this->item_helpers->remote_data_load_config( 'transports' ),
			),
		);
	}

	/**
	 * Validate — the failed send used the selected transport.
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
	 * Define tokens — the shared email set plus the caught error message.
	 *
	 * @param array $trigger
	 * @param array $tokens
	 *
	 * @return array
	 */
	public function define_tokens( $trigger, $tokens ) {
		return array_merge(
			$tokens,
			$this->item_helpers->get_email_tokens(),
			array(
				array(
					'tokenId'   => 'POST_SMTP_ERROR_MESSAGE',
					'tokenName' => esc_html_x( 'Error message', 'Post SMTP', 'uncanny-automator' ),
					'tokenType' => 'text',
				),
			)
		);
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

		list( $log, , $transcript, $transport, $error_message ) = array_pad( $hook_args, 5, null );

		return array_merge(
			$this->item_helpers->hydrate_email_tokens( $log, $transcript, $transport ),
			array(
				'POST_SMTP_ERROR_MESSAGE' => is_scalar( $error_message ) ? (string) $error_message : '',
			)
		);
	}
}
