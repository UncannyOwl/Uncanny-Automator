<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Providers;

use Uncanny_Automator\App\Bridge\Automator_Email_Tester_Bridge;
use Uncanny_Automator\App\Bridge\Email_Tester_Bridge;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Test_Context;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Test_Provider;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services\Field_Values_Flattener;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects\Node_Test;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects\Run_Result;

/**
 * EMAILS/SENDEMAIL launch test: one required test-send with an ephemeral
 * recipient field. The body comes from node_values (dirty edits included);
 * tokens ship raw — tests run with no trigger context.
 */
final class Send_Email_Tests_Provider implements Node_Test_Provider {

	/**
	 * Email tester seam.
	 *
	 * @var Email_Tester_Bridge
	 */
	private Email_Tester_Bridge $bridge;

	/**
	 * Lazy-default DI per Rule 9c.
	 *
	 * @param Email_Tester_Bridge|null $bridge Tester seam (test seam).
	 */
	public function __construct( ?Email_Tester_Bridge $bridge = null ) {
		$this->bridge = $bridge ?? new Automator_Email_Tester_Bridge();
	}

	/**
	 * The node kind.
	 *
	 * @return string
	 */
	public function kind(): string {
		return 'item';
	}

	/**
	 * The owning integration.
	 *
	 * @return string
	 */
	public function integration_code(): string {
		return 'EMAILS';
	}

	/**
	 * The item code.
	 *
	 * @return string
	 */
	public function code(): string {
		return 'SENDEMAIL';
	}

	/**
	 * The single launch test.
	 *
	 * @param Node_Test_Context $context Node context.
	 * @return Node_Test[]
	 */
	public function get_tests( Node_Test_Context $context ): array {
		return array(
			new Node_Test(
				array(
					'id'           => 'send-email',
					'name'         => esc_html_x( 'Send test email', 'Node tests', 'uncanny-automator' ),
					'description'  => esc_html_x( 'Ensure everything looks perfect before sending it out! **Send a test email to preview** your content and make any **necessary adjustments**.', 'Node tests', 'uncanny-automator' )
						. ' ' . esc_html_x( 'Token values are not replaced during tests. Placeholders like {{user email}} are sent as-is.', 'Node tests', 'uncanny-automator' ),
					'required'     => true,
					'run_mode'     => 'request',
					'button_label' => esc_html_x( 'Send email', 'Node tests', 'uncanny-automator' ),
					'fields'       => array(
						array(
							'option_code' => 'EMAIL_ADDRESS',
							'input_type'  => 'email',
							'label'       => esc_html_x( 'Email address', 'Node tests', 'uncanny-automator' ),
							'required'    => true,
						),
					),
				)
			),
		);
	}

	/**
	 * Send the test email.
	 *
	 * @param string            $test_id     Test id.
	 * @param array             $node_values FieldsOutput node config (body, content type, attachment).
	 * @param array             $test_values FieldsOutput test fields (recipient).
	 * @param Node_Test_Context $context     Node context.
	 * @return Run_Result
	 */
	public function run( string $test_id, array $node_values, array $test_values, Node_Test_Context $context ): Run_Result {
		$config = Field_Values_Flattener::flatten( $node_values );
		$to     = (string) ( Field_Values_Flattener::flatten( $test_values )['EMAIL_ADDRESS'] ?? '' );

		if ( ! is_email( $to ) ) {
			return Run_Result::failed(
				esc_html_x( 'A valid email address is required to send the test.', 'Node tests', 'uncanny-automator' )
			);
		}

		$args = array(
			'to'      => $to,
			'subject' => esc_html_x( 'Uncanny Automator Test', 'Node tests', 'uncanny-automator' ),
			'body'    => (string) ( $config['EMAILBODY'] ?? '' ),
		);

		if ( 'plain' === ( $config['EMAILCONTENTTYPE'] ?? '' ) ) {
			$args['content-type'] = 'text/plain; charset=UTF-8';
			$args['body']         = wp_strip_all_tags( preg_replace( '/<br\s*\/?>/', PHP_EOL, $args['body'] ) );
		}

		// FILE_ATTACHMENT_URL is a JSON-encoded array on the wire ("[]" when
		// empty); the legacy tester decoded it and plucked url keys — a raw
		// pass-through would feed the JSON string to the attachment validator.
		foreach ( $this->attachment_urls( (string) ( $config['FILE_ATTACHMENT_URL'] ?? '' ) ) as $attachment_url ) {
			$args['attachments'][] = $attachment_url;
		}

		try {
			if ( ! $this->bridge->send( $args ) ) {
				return Run_Result::failed(
					esc_html_x( 'The test email could not be sent.', 'Node tests', 'uncanny-automator' ),
					esc_html_x( 'Ensure that your email server settings, such as SMTP configuration, are accurate.', 'Node tests', 'uncanny-automator' )
				);
			}
		} catch ( \Throwable $e ) {
			// Raw like every other content path here: the FE's uas-markdown
			// (remark/rehype-sanitize) escapes at render time, so pre-escaping
			// would double-encode entities in the exception message.
			return Run_Result::failed(
				esc_html_x( 'The test email could not be sent.', 'Node tests', 'uncanny-automator' ),
				$e->getMessage()
			);
		}

		return Run_Result::passed(
			esc_html_x( 'Test email sent successfully!', 'Node tests', 'uncanny-automator' ),
			esc_html_x( 'Check your inbox to review the preview.', 'Node tests', 'uncanny-automator' )
		);
	}

	/**
	 * Request-mode provider: status() is never called by the transport.
	 *
	 * @param string            $test_id Test id.
	 * @param Node_Test_Context $context Node context.
	 * @return Run_Result
	 */
	public function status( string $test_id, Node_Test_Context $context ): Run_Result {
		return Run_Result::not_run();
	}

	/**
	 * Request-mode provider: cancel() is never called by the transport.
	 *
	 * @param string            $test_id Test id.
	 * @param string            $reason  Cancellation reason.
	 * @param Node_Test_Context $context Node context.
	 * @return Run_Result
	 */
	public function cancel( string $test_id, string $reason, Node_Test_Context $context ): Run_Result {
		return Run_Result::not_run();
	}

	/**
	 * Extract attachment URLs from the file field's wire value.
	 *
	 * The field stores a JSON array — "[]" when empty; entries are either
	 * URL strings or {url: ...} objects (the legacy tester read [0]['url']).
	 * Anything undecodable yields no attachments.
	 *
	 * @param string $wire_value Raw field value.
	 * @return string[]
	 */
	private function attachment_urls( string $wire_value ): array {
		$decoded = json_decode( $wire_value, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$urls = array();
		foreach ( $decoded as $entry ) {
			$url = is_array( $entry ) ? (string) ( $entry['url'] ?? '' ) : (string) $entry;
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}

		return $urls;
	}
}
