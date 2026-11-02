<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Node_Tests\Providers;

use Uncanny_Automator\App\Bridge\Automator_Send_Webhook_Bridge;
use Uncanny_Automator\App\Bridge\Send_Webhook_Bridge;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Test_Context;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Node_Test_Provider;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Services\Field_Values_Flattener;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects\Node_Test;
use Uncanny_Automator\App\Recipe_Builder\Node_Tests\Value_Objects\Run_Result;

/**
 * Send-webhook launch tests: optional payload preview plus the required
 * live send. Serves the whole send-webhook family — the bootstrap
 * registers this class once per family identity
 * ({@see Node_Tests_Bootstrap::SEND_WEBHOOK_FAMILY}); every member
 * renders the same shared field group, so execution is identity-agnostic.
 * The identity methods below return the canonical WEBHOOKS/WPSENDWEBHOOK
 * pair, used only by instance-based registration. Tokens ship raw
 * ({{...}} strings are sent as-is — tests run with no trigger context).
 */
final class Send_Webhook_Tests_Provider implements Node_Test_Provider {

	/**
	 * Send-webhook facade seam.
	 *
	 * @var Send_Webhook_Bridge
	 */
	private Send_Webhook_Bridge $bridge;

	/**
	 * Lazy-default DI per Rule 9c.
	 *
	 * @param Send_Webhook_Bridge|null $bridge Facade seam (test seam).
	 */
	public function __construct( ?Send_Webhook_Bridge $bridge = null ) {
		$this->bridge = $bridge ?? new Automator_Send_Webhook_Bridge();
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
		return 'WEBHOOKS';
	}

	/**
	 * The item code.
	 *
	 * @return string
	 */
	public function code(): string {
		return 'WPSENDWEBHOOK';
	}

	/**
	 * The two launch tests.
	 *
	 * @param Node_Test_Context $context Node context.
	 * @return Node_Test[]
	 */
	public function get_tests( Node_Test_Context $context ): array {
		$token_note = esc_html_x( 'Token values are not replaced during tests. Placeholders like {{user email}} are sent as-is.', 'Node tests', 'uncanny-automator' );

		return array(
			new Node_Test(
				array(
					'id'           => 'validate-format',
					'name'         => esc_html_x( 'Validate data format', 'Node tests', 'uncanny-automator' ),
					'description'  => esc_html_x( 'Preview the exact payload that will be sent, formatted per the selected data format.', 'Node tests', 'uncanny-automator' ) . ' ' . $token_note,
					'required'     => false,
					'run_mode'     => 'request',
					'button_label' => esc_html_x( 'Validate data format', 'Node tests', 'uncanny-automator' ),
					'fields'       => array(),
				)
			),
			new Node_Test(
				array(
					'id'           => 'send-webhook',
					'name'         => esc_html_x( 'Send webhook', 'Node tests', 'uncanny-automator' ),
					'description'  => $token_note,
					'required'     => true,
					'run_mode'     => 'request',
					'button_label' => esc_html_x( 'Send webhook', 'Node tests', 'uncanny-automator' ),
					'fields'       => array(),
				)
			),
		);
	}

	/**
	 * Execute one of the two request-mode tests.
	 *
	 * @param string            $test_id     Test id.
	 * @param array             $node_values FieldsOutput node config.
	 * @param array             $test_values FieldsOutput test fields (unused; both tests declare none).
	 * @param Node_Test_Context $context     Node context.
	 * @return Run_Result
	 */
	public function run( string $test_id, array $node_values, array $test_values, Node_Test_Context $context ): Run_Result {
		$data = Field_Values_Flattener::flatten( $node_values );

		if ( 'validate-format' === $test_id ) {
			return $this->validate_format( $data );
		}

		return $this->send_webhook( $data, $context );
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
	 * The optional payload preview.
	 *
	 * @param array $data Flat field map.
	 * @return Run_Result
	 */
	private function validate_format( array $data ): Run_Result {
		$data_type = $this->bridge->get_data_type( $data );
		$preview   = $this->bridge->get_fields( $data, $data_type, true );
		$preview   = is_string( $preview ) ? stripcslashes( $preview ) : (string) wp_json_encode( $preview, JSON_PRETTY_PRINT );

		return Run_Result::passed(
			esc_html_x( 'Payload preview generated.', 'Node tests', 'uncanny-automator' ),
			"```{$data_type}\n{$preview}\n```"
		);
	}

	/**
	 * The required live send.
	 *
	 * @param array             $data    Flat field map.
	 * @param Node_Test_Context $context Node context.
	 * @return Run_Result
	 */
	private function send_webhook( array $data, Node_Test_Context $context ): Run_Result {
		$url = $this->bridge->get_url( $data );
		if ( '' === $url ) {
			return Run_Result::failed(
				esc_html_x( 'The webhook URL is missing or invalid.', 'Node tests', 'uncanny-automator' ),
				esc_html_x( 'Enter a webhook URL and try again.', 'Node tests', 'uncanny-automator' )
			);
		}

		$data_type    = $this->bridge->get_data_type( $data );
		$request_type = $this->bridge->request_type( $data );

		$args = array(
			'method'  => $request_type,
			'body'    => $this->bridge->get_fields( $data, $data_type ),
			'timeout' => 30,
		);

		$headers = $this->bridge->get_headers( $data, $data_type, $context->get_node_id() );
		if ( ! empty( $headers ) ) {
			$args['headers'] = $headers;
		}

		$response = $this->bridge->call_webhook( $url, $args, $request_type );

		if ( is_wp_error( $response ) ) {
			// Raw inside code spans like every other content path here: the FE's
			// uas-markdown (remark/rehype-sanitize) escapes code content itself,
			// so pre-escaping would double-encode entities in error messages.
			return Run_Result::failed(
				esc_html_x( 'The webhook could not be reached.', 'Node tests', 'uncanny-automator' ),
				'`' . implode( '` `', $response->get_error_messages() ) . '`'
			);
		}

		// Keep the legacy side effect: captured response leafs feed the action tokens.
		$tokens = $this->bridge->collect_response_tokens( $response );
		Database::get_action_store()->update_meta( $context->get_node_id(), 'webhook_response_tokens', wp_json_encode( $tokens ) );

		$status_code = absint( wp_remote_retrieve_response_code( $response ) );
		$body        = (string) wp_remote_retrieve_body( $response );
		$content     = "```\n" . $body . "\n```";

		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			return Run_Result::failed(
				sprintf(
					/* translators: %d: HTTP status code */
					esc_html_x( 'The server responded with error code %d.', 'Node tests', 'uncanny-automator' ),
					$status_code
				),
				$content
			);
		}

		return Run_Result::passed(
			esc_html_x( 'Data was sent to the webhook successfully.', 'Node tests', 'uncanny-automator' ),
			$content
		);
	}
}
