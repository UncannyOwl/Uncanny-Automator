<?php

namespace Uncanny_Automator\Integrations\Post_Smtp;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Post_Smtp_Helpers
 *
 * Shared logic for the Post SMTP integration. Both triggers fire from the same
 * pair of hooks (`post_smtp_on_success` / `post_smtp_on_failed`) with the same
 * leading payload, so the transport dropdown and the email token define/hydrate
 * pair live here and are reused by both.
 *
 * @package Uncanny_Automator\Integrations\Post_Smtp
 */
class Post_Smtp_Helpers extends Abstract_Helpers {

	/**
	 * The "Any" sentinel for the transport dropdown.
	 *
	 * @var string
	 */
	const ANY = '-1';

	// =========================================================================
	// Remote-data handlers.
	//
	// Resolved via REST: POST /wp-json/uap/v2/remote-data/post_smtp/{segment}.
	// Triggers consume the bare segment (incl. "Any transport" -> -1); actions
	// and conditions would consume the `_strict` segment.
	// =========================================================================

	/**
	 * Remote-data handler: transports for triggers (incl. "Any transport").
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_transports( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_transport_options( true ) );
	}

	/**
	 * Remote-data handler: transports without the "Any" sentinel.
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_transports_strict( $request ): array {
		unset( $request );
		return $this->remote_data_success( $this->build_transport_options( false ) );
	}

	/**
	 * Transport options, read from Post SMTP's live registry rather than a
	 * hardcoded slug list, so Pro transports (Microsoft 365, Amazon SES, Zoho)
	 * and any future additions appear without an integration change.
	 *
	 * @param bool $include_any Prepend the "Any transport" sentinel.
	 *
	 * @return array
	 */
	public function build_transport_options( $include_any = true ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any transport', 'Post SMTP', 'uncanny-automator' ),
			);
		}

		foreach ( $this->registered_transports() as $slug => $transport ) {

			if ( ! is_object( $transport ) || ! method_exists( $transport, 'getName' ) ) {
				continue;
			}

			$options[] = array(
				'value' => (string) $slug,
				'text'  => (string) $transport->getName(),
			);
		}

		return $options;
	}

	/**
	 * Every transport registered with Post SMTP, keyed by slug.
	 *
	 * @return array
	 */
	public function registered_transports() {

		if ( ! class_exists( '\PostmanTransportRegistry' ) ) {
			return array();
		}

		$registry = \PostmanTransportRegistry::getInstance();

		if ( ! is_object( $registry ) || ! method_exists( $registry, 'getTransports' ) ) {
			return array();
		}

		return (array) $registry->getTransports();
	}

	// =========================================================================
	// Hook payload readers.
	// =========================================================================

	/**
	 * The slug of the transport that handled the send.
	 *
	 * Both hook sites pass the object returned by
	 * `PostmanTransportRegistry::getActiveTransport()`, so getSlug() is the only
	 * reliable source. `$log->transportUri` is deliberately NOT used as a
	 * fallback: it holds `getPublicTransportUri()` (a display URI such as
	 * `smtp://user@host:587`, not a slug) and is written by
	 * PostmanEmailLogService on the same hook at the same priority, so it may
	 * still be empty when the trigger runs.
	 *
	 * @param mixed $transport The 4th hook arg.
	 *
	 * @return string
	 */
	public function transport_slug( $transport ) {

		if ( ! is_object( $transport ) || ! method_exists( $transport, 'getSlug' ) ) {
			return '';
		}

		return (string) $transport->getSlug();
	}

	/**
	 * Human-readable transport URI for the output token.
	 *
	 * @param mixed $transport The 4th hook arg.
	 * @param mixed $log       The 1st hook arg.
	 *
	 * @return string
	 */
	public function transport_uri( $transport, $log ) {

		if ( is_object( $transport ) && method_exists( $transport, 'getPublicTransportUri' ) ) {
			return (string) $transport->getPublicTransportUri();
		}

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PostmanEmailLog declares camelCase properties; renaming is not ours to do.
		if ( is_object( $log ) && isset( $log->transportUri ) ) {
			return (string) $log->transportUri;
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

		return '';
	}

	// =========================================================================
	// Shared email tokens — every field on PostmanEmailLog that is useful in a
	// recipe, plus the SMTP transcript from the 3rd hook arg.
	// =========================================================================

	/**
	 * Token definitions shared by both triggers.
	 *
	 * @return array
	 */
	public function get_email_tokens() {
		return array(
			array(
				'tokenId'   => 'POST_SMTP_SENDER',
				'tokenName' => esc_html_x( 'Sender email', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'email',
			),
			array(
				'tokenId'   => 'POST_SMTP_TO',
				'tokenName' => esc_html_x( 'To recipients', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_SMTP_CC',
				'tokenName' => esc_html_x( 'CC recipients', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_SMTP_BCC',
				'tokenName' => esc_html_x( 'BCC recipients', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_SMTP_SUBJECT',
				'tokenName' => esc_html_x( 'Subject', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_SMTP_BODY',
				'tokenName' => esc_html_x( 'Body', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_SMTP_REPLY_TO',
				'tokenName' => esc_html_x( 'Reply-to', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_SMTP_TRANSPORT_URI',
				'tokenName' => esc_html_x( 'Transport', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_SMTP_STATUS_MESSAGE',
				'tokenName' => esc_html_x( 'Status message', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'POST_SMTP_TRANSCRIPT',
				'tokenName' => esc_html_x( 'SMTP transcript', 'Post SMTP', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Hydrate the shared tokens. Returns the full keyset even when the log
	 * object is missing, so a recipe never resolves a partial map.
	 *
	 * @param mixed  $log        The 1st hook arg (PostmanEmailLog).
	 * @param string $transcript The 3rd hook arg.
	 * @param mixed  $transport  The 4th hook arg.
	 *
	 * @return array
	 */
	public function hydrate_email_tokens( $log, $transcript, $transport ) {
		return array(
			'POST_SMTP_SENDER'         => $this->log_value( $log, 'sender' ),
			'POST_SMTP_TO'             => $this->log_value( $log, 'toRecipients' ),
			'POST_SMTP_CC'             => $this->log_value( $log, 'ccRecipients' ),
			'POST_SMTP_BCC'            => $this->log_value( $log, 'bccRecipients' ),
			'POST_SMTP_SUBJECT'        => $this->log_value( $log, 'subject', 'originalSubject' ),
			'POST_SMTP_BODY'           => $this->log_value( $log, 'body', 'originalMessage' ),
			'POST_SMTP_REPLY_TO'       => $this->log_value( $log, 'replyTo' ),
			'POST_SMTP_TRANSPORT_URI'  => $this->transport_uri( $transport, $log ),
			'POST_SMTP_STATUS_MESSAGE' => $this->log_value( $log, 'statusMessage' ),
			'POST_SMTP_TRANSCRIPT'     => is_scalar( $transcript ) ? (string) $transcript : '',
		);
	}

	/**
	 * Read a property off the log object, with an optional fallback property.
	 *
	 * Values on PostmanEmailLog are plain strings, but `originalHeaders` can be
	 * an array and `success` is mixed, so non-scalars are flattened rather than
	 * emitted as "Array".
	 *
	 * @param mixed  $log      The log object.
	 * @param string $property Primary property name.
	 * @param string $fallback Optional fallback property name.
	 *
	 * @return string
	 */
	private function log_value( $log, $property, $fallback = '' ) {

		if ( ! is_object( $log ) ) {
			return '';
		}

		$value = isset( $log->$property ) ? $log->$property : '';

		if ( '' === $value && '' !== $fallback && isset( $log->$fallback ) ) {
			$value = $log->$fallback;
		}

		if ( is_array( $value ) ) {
			return implode( ', ', array_map( 'strval', $value ) );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
