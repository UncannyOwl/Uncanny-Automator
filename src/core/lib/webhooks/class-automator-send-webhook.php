<?php

namespace Uncanny_Automator;

use DOMDocument;
use SimpleXMLElement;

/**
 * Automator_Send_Webhook Main Class
 */
class Automator_Send_Webhook {
	/**
	 * Automator_Send_Webhook Instance
	 *
	 * @var
	 */
	public static $instance;

	/**
	 * Automator_Send_Webhook_Fields Holder
	 *
	 * @var Automator_Send_Webhook_Fields
	 */
	public $fields;

	/**
	 * Array nested field separator in UI
	 *
	 * @var mixed|void
	 */
	private $field_separator;

	/**
	 * Instance of Automator_Send_Webhook
	 *
	 * @return Automator_Send_Webhook
	 */
	public static function get_instance() {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Automator_Send_Webhook Constructor
	 */
	public function __construct() {
		$this->field_separator = apply_filters( 'automator_send_webhook_field_separator', '/' );
		require_once __DIR__ . '/class-automator-send-webhook-fields.php';
		$this->fields = Automator_Send_Webhook_Fields::get_instance();
	}

	/**
	 * Anonymous JS function invoked as callback when clicking
	 * the custom button "Send test". The JS function requires
	 * the JS module "markdown". Make sure it's included in
	 * the "modules" array
	 *
	 * @return string The JS code
	 */
	public function send_test_js() {
		// Start output
		ob_start();

		// It's optional to add the <script> tags
		// This must have only one anonymous function
		?>

		<script>

			// Do when the user clicks on send test
			function ($button, data, modules) {
				// Add loading animation to the button
				$button.addClass('uap-btn--loading uap-btn--disabled');

				// Get the data we're going to send to the AJAX request
				let dataToBeSent = {
					action: 'automator_webhook_send_test_data',
					nonce: UncannyAutomator.nonce,
					integration_id: data.item.integrationCode,
					item_id: data.item.id,
					values: data.values
				}

				// Do AJAX
				jQuery.ajax({
					method: 'POST',
					dataType: 'json',
					url: ajaxurl,
					data: dataToBeSent,
					success: function (response) {
						// Remove loading animation from the button
						$button.removeClass('uap-btn--loading uap-btn--disabled');

						// Create notice
						// But first check if the message is defined
						if (typeof response.message !== 'undefined') {
							// Get notice type
							let noticeType = typeof response.type !== 'undefined' ? response.type : 'gray';
							let $message = response.message;
							// Create notice
							let $notice = jQuery('<div/>', {
								'class': 'item-options__notice item-options__notice--' + noticeType
							});

							// Add message to the notice container
							$notice.html($message);

							// Get the notices container
							let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');

							// Add notice
							$noticesContainer.html($notice);
						}
					},

					statusCode: {
						403: function () {
							location.reload();
						}
					},

					fail: function (response) {
					}
				});
			}

		</script>

		<?php

		// Get output
		// Return output
		return ob_get_clean();
	}

	/**
	 * Output Sample data on Recipe page
	 *
	 * @return false|string
	 */
	public function build_sample_data() {
		// Start output
		ob_start();

		// It's optional to add the <script> tags
		// This must have only one anonymous function
		?>

		<script>
			// Do when the user clicks on send test
			function ($button, data, modules) {
				// Add loading animation to the button
				$button.addClass('uap-btn--loading uap-btn--disabled');

				// Get the data we're going to send to the AJAX request
				let dataToBeSent = {
					action: 'automator_webhook_build_test_data',
					nonce: UncannyAutomator.nonce,
					integration_id: data.item.integrationCode,
					item_id: data.item.id,
					values: data.values
				}

				// Do AJAX
				jQuery.ajax({
					method: 'POST',
					dataType: 'json',
					url: ajaxurl,
					data: dataToBeSent,
					success: function (response) {
						// Remove loading animation from the button
						$button.removeClass('uap-btn--loading uap-btn--disabled');
						// Create notice
						// But first check if the message is defined
						if (typeof response.message !== 'undefined') {
							// Get notice type
							let noticeType = typeof response.type !== 'undefined' ? response.type : 'gray';

							// Create notice
							let $notice = jQuery('<div/>', {
								'class': 'item-options__notice item-options__notice--' + noticeType
							});
							// Parse message using markdown
							let markdown = new modules.Markdown(response.message);

							// Get markdown HTML
							//let $message = response.message;
							let $message = markdown.getHTML();

							// Add message to the notice container
							$notice.html("<pre>" + $message + "<pre>");

							// Get the notices container
							let $noticesContainer = jQuery('.item[data-id="' + data.item.id + '"] .item-options__notices');

							// Add notice
							$noticesContainer.html($notice);
						}
					},

					statusCode: {
						403: function () {
							location.reload();
						}
					},

					fail: function (response) {
					}
				});
			}

		</script>

		<?php

		// Get output
		// Return output
		return ob_get_clean();
	}

	/**
	 * Outgoing webhook request type
	 *
	 * @param $data
	 *
	 * @return mixed|void
	 */
	public function request_type( $data ) {
		switch ( $data['ACTION_EVENT'] ) {
			case 'GET':
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'GET', $data );
				break;
			case 'PUT':
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'PUT', $data );
				break;
			case 'DELETE':
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'DELETE', $data );
				break;
			case 'HEAD':
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'HEAD', $data );
				break;
			case 'automator_custom_value':
				if ( isset( $data['ACTION_EVENT_custom'] ) ) {
					$request_type = apply_filters( 'automator_outgoing_webhook_request_type', $data['ACTION_EVENT_custom'], $data );
					break;
				}
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'POST', $data );
				break;
			case 'POST':
			case 'CUSTOM':
			default:
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'POST', $data );
				break;
		}

		return $request_type;
	}

	/**
	 * Get date type
	 *
	 * @param $data
	 *
	 * @return mixed|string
	 */
	public function get_data_type( $data ) {
		if ( ! isset( $data['DATA_FORMAT'] ) ) {
			return 'x-www-form-urlencoded';
		}

		return $data['DATA_FORMAT'];
	}

	/**
	 * Outgoing webhook Content Type
	 *
	 * @return string|array
	 */
	public function get_content_type( $data_type, $headers ) {
		$supported_data_types = array(
			'application/x-www-form-urlencoded' => 'x-www-form-urlencoded',
			'multipart/form-data'               => 'form-data',
			'application/json'                  => 'json',
			'text/plain'                        => 'plain',
			'application/binary'                => 'binary',
			'text/html'                         => 'html',
			'xml'                               => 'xml',
			'GraphQL'                           => 'GraphQL',
			'raw'                               => 'raw',
		);
		if ( 'none' === $data_type ) {
			$data_type = 'raw';
		}
		if ( in_array( $data_type, $supported_data_types, true ) ) {
			$type                    = array_search( $data_type, $supported_data_types, true );
			$headers['Content-Type'] = $type;

			return $headers;
		}

		$headers['Content-Type'] = 'application/x-www-form-urlencoded';

		return $headers;
	}

	/**
	 * Get outgoing URL
	 *
	 * @param $data
	 * @param bool $legacy
	 * @param array $parsed_args
	 *
	 * @return string|null
	 */
	public function get_url( $data, $legacy = false, $parsed_args = array() ) {
		if ( $legacy ) {
			return esc_url_raw( isset( $data['WEBHOOKURL'] ) ? $this->maybe_parse_tokens( $data['WEBHOOKURL'], $parsed_args ) : '' );
		}

		return esc_url_raw( isset( $data['WEBHOOK_URL'] ) ? $this->maybe_parse_tokens( $data['WEBHOOK_URL'], $parsed_args ) : '' );
	}

	/**
	 * Get outgoing Fields and data
	 *
	 * @param $data
	 * @param bool $legacy
	 * @param string $data_type
	 * @param array $parsing_args
	 * @param bool $is_check_sample
	 *
	 * @return array|mixed
	 * @throws \Exception
	 */
	public function get_fields( $data, $legacy = false, $data_type = '', $parsing_args = array(), $is_check_sample = false ) {
		$prepared_data = array();
		if ( $legacy ) {
			return $this->prepare_legacy_fields( $data, $parsing_args );
		}
		if ( ! isset( $data['WEBHOOK_FIELDS'] ) ) {
			return $prepared_data;
		}
		$fields = ! is_array( $data['WEBHOOK_FIELDS'] ) ? json_decode( $data['WEBHOOK_FIELDS'], true ) : $data['WEBHOOK_FIELDS'];
		if ( empty( $fields ) ) {
			return $prepared_data;
		}
		foreach ( $fields as $field ) {
			$key   = isset( $field['KEY'] ) ? $this->maybe_parse_tokens( $field['KEY'], $parsing_args ) : null;
			$value = isset( $field['VALUE'] ) ? $this->maybe_parse_tokens( $field['VALUE'], $parsing_args ) : null;
			if ( ! is_null( $key ) && ! is_null( $value ) ) {
				$prepared_data[ $key ] = $value;
			}
		}
		$prepared_data = $this->create_tree( $prepared_data, $data_type );

		return $this->format_outgoing_data( $prepared_data, $data_type, $is_check_sample );
	}

	/**
	 * Legacy data. Used in v1~2.1 of Pro
	 *
	 * @param $data
	 * @param array $parsing_args
	 *
	 * @return array
	 */
	private function prepare_legacy_fields( $data, $parsing_args = array() ) {
		$key_values     = array();
		$number_of_keys = 7;
		for ( $i = 1; $i <= $number_of_keys; $i ++ ) {
			$key                = $this->maybe_parse_tokens( $data[ 'KEY' . $i ], $parsing_args );
			$value              = $this->maybe_parse_tokens( $data[ 'VALUE' . $i ], $parsing_args );
			$key_values[ $key ] = $value;
		}

		return $key_values;
	}

	/**
	 * Get outgoing headers
	 *
	 * @param $data
	 * @param array $parsing_args
	 *
	 * @return array
	 */
	public function get_headers( $data, $parsing_args = array() ) {
		$headers     = array();
		$header_meta = isset( $data['WEBHOOK_HEADERS'] ) ? ! is_array( $data['WEBHOOK_HEADERS'] ) ? json_decode( $data['WEBHOOK_HEADERS'], true ) : $data['WEBHOOK_HEADERS'] : array();
		if ( empty( $header_meta ) ) {
			return $headers;
		}

		//$header_fields = count( $header_meta );
		foreach ( $header_meta as $meta ) {
			$key = isset( $meta['NAME'] ) ? $this->maybe_parse_tokens( $meta['NAME'], $parsing_args ) : null;
			// remove colon if user added in NAME
			$key             = str_replace( ':', '', $key );
			$value           = isset( $meta['VALUE'] ) ? $this->maybe_parse_tokens( $meta['VALUE'], $parsing_args ) : null;
			$headers[ $key ] = $value;
		}

		return array_unique( $headers );
	}

	/**
	 * Parse values in to key/value fields
	 *
	 * @param $value
	 * @param $parsing_args
	 *
	 * @return string|null
	 */
	public function maybe_parse_tokens( $value, $parsing_args ) {
		if ( empty( $parsing_args ) ) {
			return sanitize_text_field( $value );
		}

		return Automator()->parse->text( $value, $parsing_args['recipe_id'], $parsing_args['user_id'], $parsing_args['args'] );
	}

	/**
	 * Prepare outgoing field data
	 *
	 * @param $prepared_data
	 * @param $data_type
	 *
	 * @return array|mixed
	 */
	private function create_tree( $prepared_data, $data_type ) {
		if ( ! $this->is_tree_required( $data_type ) ) {
			return $prepared_data;
		}
		$json  = wp_json_encode( $prepared_data );
		$array = json_decode( $json, true );

		// init an array to hold the final result
		$tree = array();

		// iterate over the array of values
		// explode the key into an array 'path' tokens
		// pop each off and build a multidimensional array
		// finally 'merge' the result into the result array

		foreach ( $array as $path => $value ) {
			$tokens = explode( $this->field_separator, $path );
			while ( null !== ( $key = array_pop( $tokens ) ) ) { //phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
				$current = array( $key => $value );
				$value   = $current;
			}
			$tree = array_replace_recursive( $tree, $value );
		}

		return $tree;
	}

	/**
	 * Should Automator prepare nested array?
	 *
	 * @param $data_type
	 *
	 * @return bool
	 */
	private function is_tree_required( $data_type ) {
		$required_for = apply_filters(
			'automator_send_webhook_data_tree_required',
			array(
				'json',
				'graph',
				'xml',
			),
			$data_type
		);
		if ( in_array( $data_type, $required_for, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Format outgoing data in to appropriate data type
	 *
	 * @param $fields
	 * @param $data_type
	 * @param bool $is_check_sample
	 *
	 * @return false|mixed|string
	 * @throws \Exception
	 */
	private function format_outgoing_data( $fields, $data_type, $is_check_sample = false ) {
		$original = $fields;
		switch ( $data_type ) {
			case 'json':
			case 'graph':
				if ( $is_check_sample ) {
					$fields = wp_json_encode( $fields, JSON_PRETTY_PRINT );
				} else {
					$fields = wp_json_encode( $fields );
				}
				break;
			case 'form-data':
				$fields = http_build_query( $fields );
				if ( $is_check_sample ) {
					$fields = html_entity_decode(
						str_replace(
							array(
								'%2F',
								'%7B',
								'%7D',
								'%3A',
								'&',
							),
							array(
								'/',
								'{',
								'}',
								':',
								"\n" . '&',
							),
							$fields
						)
					);
				}
				break;
			case 'plain':
				$fields = implode( apply_filters( 'automator_send_webhook_plain_text_separator', ',', $fields ), $fields );
				break;
			case 'binary':
				$fields = implode( apply_filters( 'automator_send_webhook_binary_separator', ',', $fields ), $fields );
				$fields = $this->string_to_binary_conversion( $fields );
				break;
			case 'html':
				$fields = $this->build_html_table( $fields, $is_check_sample );
				break;
			case 'xml':
				try {
					$xml_body_wrapper = apply_filters( 'automator_send_webhook_xml_body', '<body></body>', $fields );
					if ( empty( $xml_body_wrapper ) ) {
						$fields = __( 'XML body wrapper cannot be empty. Please use `automator_send_webhook_xml_body` filter to define a wrapper.', 'uncanny-automator' );
						break;
					}
					$xml_data = new SimpleXMLElement( $xml_body_wrapper );
					$this->array_to_xml( $fields, $xml_data );
					$fields = $xml_data->asXML();

					if ( $is_check_sample ) {
						$dom                     = new DOMDocument( '1.0' );
						$dom->preserveWhiteSpace = true; //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$dom->formatOutput       = true; //phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$dom->loadXML( trim( $fields ) );
						$fields = $dom->saveXML();
					} else {
						$fields = str_replace( PHP_EOL, '', $fields );
					}
				} catch ( \Exception $e ) {
					$fields = $e->getMessage();
				}
				break;
			case 'x-www-form-urlencoded':
				if ( $is_check_sample ) {
					$fields = wp_json_encode( $fields, JSON_PRETTY_PRINT );
				} else {
					$fields = $original;
				}
				break;
		}

		return apply_filters( 'automator_send_webhook_data_format', $fields, $original, $data_type );
	}

	/**
	 * Convert comma separated array values in to binary
	 *
	 * @param $string
	 *
	 * @return string
	 */
	private function string_to_binary_conversion( $string ) {
		$characters = str_split( $string );

		$binary = array();
		foreach ( $characters as $character ) {
			$data     = unpack( apply_filters( 'automator_send_webhook_string_to_binary_format', 'H*', $string ), $character );
			$binary[] = base_convert( $data[1], 16, 2 );
		}

		return implode( ' ', $binary );
	}

	/**
	 * Convert binary back to text
	 *
	 * @param $binary
	 *
	 * @return string|null
	 */
	private function binary_to_text( $binary ) {
		$binaries = explode( ' ', $binary );

		$string = null;
		foreach ( $binaries as $binary ) {
			$string .= pack( apply_filters( 'automator_send_webhook_binary_to_string_format', 'H*', $string ), dechex( bindec( $binary ) ) ); //phpcs:ignore PHPCompatibility.ParameterValues.NewPackFormat.NewFormatFound
		}

		return $string;
	}

	/**
	 * Basic two column table
	 *
	 * @param $array
	 *
	 * @return string
	 */
	private function build_html_table( $array, $is_sample = false ) {
		$n      = '';
		$spaces = '';
		if ( $is_sample ) {
			$n      = "\n";
			$spaces = "\t";
		}
		// start table
		$html = '<table>' . $n;
		// data rows
		foreach ( $array as $key => $value ) {
			$html .= $spaces . '<tr>' . $n;
			$html .= $spaces . $spaces . '<td>' . htmlspecialchars( $key ) . '</td>' . $n;
			$html .= $spaces . $spaces . '<td>' . htmlspecialchars( $value ) . '</td>' . $n;
			$html .= $spaces . '</tr>' . $n;
		}

		// finish table and return it

		$html .= '</table>';

		return $html;
	}


	/**
	 * Convert nested array in to XML
	 *
	 * @param $data
	 * @param $xml_data
	 *
	 * @return void
	 */
	private function array_to_xml( $data, &$xml_data ) {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( is_numeric( $key ) ) {
					$key = 'item' . $key; //dealing with <0/>..<n/> issues
				}
				$subnode = $xml_data->addChild( $key );
				$this->array_to_xml( $value, $subnode );
			} else {
				$xml_data->addChild( "$key", htmlspecialchars( "$value" ) );
			}
		}
	}


	/**
	 * @param $webhook_url
	 * @param $args
	 * @param $request_type
	 *
	 * @return array|mixed|void|\WP_Error
	 */
	public static function call_webhook( $webhook_url, $args, $request_type = 'POST' ) {
		switch ( sanitize_text_field( wp_unslash( $request_type ) ) ) {
			case 'PUT':
			case 'POST':
			case 'DELETE':
				$response = wp_remote_post( $webhook_url, $args );
				break;
			case 'GET':
				$response = wp_remote_get( $webhook_url, $args );
				break;
			case 'HEAD':
				$response = wp_remote_head( $webhook_url, $args );
				break;
			default:
				$response = apply_filters( 'automator_send_webhook_default_response', wp_remote_post( $webhook_url, $args ), $webhook_url, $args );
				break;
		}

		return $response;
	}
}
