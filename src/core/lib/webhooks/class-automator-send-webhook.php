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
	 * @var
	 */
	private $boundary;

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

		// Register hooks.
		add_filter( 'automator_field_values_before_save', array( $this, 'encrypt_authorization_values' ), 10, 2 );
	}

	/**
	 * Encrypt authorization vallues.
	 *
	 * @param mixed $meta_value
	 * @param mixed $item
	 *
	 * @return string
	 */
	public function encrypt_authorization_values( $meta_value, $item ) {

		if ( ! isset( $meta_value['WEBHOOK_AUTHORIZATIONS'] ) ) {
			return $meta_value;
		}

		$authorization = $meta_value['WEBHOOK_AUTHORIZATIONS'];
		$item_id       = $_POST['itemId'] ?? null; //phpcs:ignore

		if ( empty( $authorization ) ) {
			$this->delete_original_auth_value( $item_id );
			return $meta_value;
		}

		// Check if the string contains tokens.
		if ( preg_match( '/\{\{.*?\}\}/', $authorization ) ) {
			$this->delete_original_auth_value( $item_id );
			return $meta_value;
		}

		// Trim all * and see if a new string is entered
		$authorization = trim( $authorization, '*' );

		if ( strlen( $authorization ) > 3 ) { // check if trimmed string is over 3 chars
			$authorization = addslashes( $authorization );
			update_post_meta( $item_id, 'WEBHOOK_AUTHORIZATIONS_ORIGINAL', $authorization );
			$meta_value['WEBHOOK_AUTHORIZATIONS'] = $this->hide_string( $authorization );
		}

		return $meta_value;
	}

	/**
	 * Delete original authorization value.
	 *
	 * @param mixed $item_id
	 *
	 * @return void
	 */
	protected function delete_original_auth_value( $item_id ) {

		// Validate item ID.
		if ( empty( $item_id ) ) {
			return;
		}

		// Delete the original value if exists.
		delete_post_meta( $item_id, 'WEBHOOK_AUTHORIZATIONS_ORIGINAL' );
	}

	/**
	 * Hides a portion of string.
	 *
	 * @param mixed $string
	 *
	 * @return mixed
	 */
	protected function hide_string( $string ) {

		$length = strlen( $string );

		// If the string is shorter than or equal to 3 characters, return the original string
		if ( $length <= 3 ) {
			return $string;
		}

		// Replace characters except the last 3 with asterisks
		$hidden_part  = str_repeat( '*', $length - 3 );
		$visible_part = substr( $string, - 3 );

		return $hidden_part . $visible_part;
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
					nonce: UncannyAutomator._site.rest.nonce,
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
					nonce: UncannyAutomator._site.rest.nonce,
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

							// Get markdown HTML
							let $message = response.message;

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
			case 'PATCH':
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'PATCH', $data );
				break;
			case 'DELETE':
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'DELETE', $data );
				break;
			case 'HEAD':
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'HEAD', $data );
				break;
			case 'OPTIONS':
				$request_type = apply_filters( 'automator_outgoing_webhook_request_type', 'OPTIONS', $data );
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
				if ( 'POST' !== $data['ACTION_EVENT'] && isset( $data['ACTION_EVENT_custom'] ) ) {
					$request_type = apply_filters( 'automator_outgoing_webhook_request_type', $data['ACTION_EVENT_custom'], $data );
					break;
				}
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
			$type  = isset( $field['VALUE_TYPE'] ) ? $this->maybe_parse_tokens( $field['VALUE_TYPE'], $parsing_args ) : 'text';
			$value = isset( $field['VALUE'] ) ? $this->maybe_parse_tokens( $field['VALUE'], $parsing_args ) : null;

			// Do not allow empty key.
			if ( '' !== $key && ! is_null( $key ) && ! is_null( $value ) ) {
				switch ( $type ) {
					case 'null':
					case 'undefined':
						$value = null;
						break;
					case 'int':
						$value = absint( $value );
						break;
					case 'float':
						$value = floatval( $value );
						break;
					case 'bool':
						$value = str_replace( array( '"', '\'' ), '', html_entity_decode( $value ) );
						if ( 'true' === strtolower( $value ) || 'false' === strtolower( $value ) ) {
							$value = 'true' === strtolower( $value ) ? true : false;
						} elseif ( is_numeric( $value ) && ( 0 === absint( $value ) || 1 === absint( $value ) ) ) {
							$value = boolval( $value );
						} else {
							$value = (string) $value;
						}
						break;
					case 'text':
					default:
						/**
						 * Allows users to strip the quotes.
						 *
						 * @see <https://secure.helpscout.net/conversation/2067343003/45133?folderId=2122433>
						 */
						$should_strip_qoutes = apply_filters( 'automator_send_webhook_get_fields_should_strip_qoutes', false );

						if ( $should_strip_qoutes ) {
							// Decode HTML entities and replace " and '
							$value = str_replace( array( '"', '\'' ), '', html_entity_decode( $value ) );
						}

						$value = apply_filters( 'automator_outgoing_webhook_default_data_value', (string) $value, $key, $type, $this );

						break;

				}

				$prepared_data[ $key ] = apply_filters( 'automator_outgoing_webhook_value', $value, $key, $type, $this );
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
		for ( $i = 1; $i <= $number_of_keys; $i++ ) {
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
	 * @param string $data_type
	 * @param int $action_id
	 * @param array $parsing_args
	 *
	 * @return array
	 */
	public function get_headers( $data, $data_type, $action_id, $parsing_args = array() ) {

		$headers = array();

		// Get Webhook Headers from repeater field.
		$header_meta = isset( $data['WEBHOOK_HEADERS'] ) ? ! is_array( $data['WEBHOOK_HEADERS'] ) ? json_decode( $data['WEBHOOK_HEADERS'], true ) : $data['WEBHOOK_HEADERS'] : array();
		if ( ! empty( $header_meta ) ) {
			foreach ( $header_meta as $meta ) {
				$key = isset( $meta['NAME'] ) ? $this->maybe_parse_tokens( $meta['NAME'], $parsing_args ) : null;
				// remove colon if user added in NAME
				$key             = trim( str_replace( ':', '', $key ) );
				$value           = isset( $meta['VALUE'] ) ? $this->maybe_parse_tokens( $meta['VALUE'], $parsing_args ) : null;
				$headers[ $key ] = trim( $value );
			}
		}

		// Content Type.
		$headers = $this->get_content_type( $data_type, $headers );

		// Authorization.
		$headers = $this->get_authorization( $action_id, $headers, $data, $parsing_args );

		// Remove duplicate keys
		$final_headers = array();
		foreach ( $headers as $key => $value ) {
			if ( ! array_key_exists( $key, $final_headers ) ) {
				$final_headers[ $key ] = $value;
			}
		}

		return $final_headers;
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
			$type = array_search( $data_type, $supported_data_types, true );
			if ( 'form-data' === $data_type ) {
				$this->boundary = sha1( time() );
				$type           = $type . '; boundary=' . $this->boundary;
			}
			$headers['Content-Type'] = $type;

			return $headers;
		}

		$headers['Content-Type'] = 'application/x-www-form-urlencoded';

		return $headers;
	}

	/**
	 * @param $action_id
	 * @param $headers
	 * @param $data
	 * @param $parsing_args
	 *
	 * @return array
	 */
	public function get_authorization( $action_id, $headers, $data, $parsing_args = array() ) {

		$authorization         = isset( $data['WEBHOOK_AUTHORIZATIONS'] ) ? $data['WEBHOOK_AUTHORIZATIONS'] : null;
		$authorization_originl = get_post_meta( $action_id, 'WEBHOOK_AUTHORIZATIONS_ORIGINAL', true );
		$authorization         = ! empty( $authorization_originl ) && is_scalar( $authorization_originl ) ? $authorization_originl : $authorization;

		if ( empty( $authorization ) ) {
			return $headers;
		}

		$authorization            = $this->maybe_parse_tokens( $authorization, $parsing_args );
		$headers['Authorization'] = apply_filters( 'automator_outgoing_webhook_authorization_string', $authorization, $action_id, $headers, $this );

		return $headers;
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
			// Convert literal new lines and other into actual new lines.
			return stripcslashes( $value );
		}

		return trim( Automator()->parse->text( $value, $parsing_args['recipe_id'], $parsing_args['user_id'], $parsing_args['args'] ) );
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
			while ( null !== ( $key = array_pop( $tokens ) ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
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
				//$fields = http_build_query( $fields );
				$fields = $this->build_multipart_form_data( $fields );
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
						$fields = esc_html__( 'XML body wrapper cannot be empty. Please use `automator_send_webhook_xml_body` filter to define a wrapper.', 'uncanny-automator' );
						break;
					}
					$xml_data = new SimpleXMLElement( $xml_body_wrapper );
					$this->array_to_xml( $fields, $xml_data );
					$fields = $xml_data->asXML();

					if ( $is_check_sample ) {
						$dom                     = new DOMDocument( '1.0' );
						$dom->preserveWhiteSpace = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						$dom->formatOutput       = true; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
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
			$string .= pack( apply_filters( 'automator_send_webhook_binary_to_string_format', 'H*', $string ), dechex( bindec( $binary ) ) ); // phpcs:ignore PHPCompatibility.ParameterValues.NewPackFormat.NewFormatFound
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

		if ( ! self::validate_webhook_url( $webhook_url ) ) {
			return new \WP_Error(
				'invalid_webhook_url',
				sprintf(
					'The webhook URL "%s" is not valid. Please ensure it is a publicly accessible URL and does not point to a private or local network address.',
					esc_url( $webhook_url )
				)
			);
		}

		$request_type = sanitize_text_field( wp_unslash( $request_type ) );

		switch ( $request_type ) {
			case 'POST':
				$response = wp_safe_remote_post( $webhook_url, $args );
				break;
			case 'GET':
				$url      = add_query_arg( $args['body'], $webhook_url );
				$response = wp_safe_remote_get( $url, $args );
				break;
			case 'HEAD':
				$response = wp_safe_remote_head( $webhook_url, $args );
				break;
			case 'PUT':
			case 'PATCH':
			case 'DELETE':
			case 'OPTIONS':
				$args['method'] = $request_type;
				$response       = wp_safe_remote_request( $webhook_url, $args );
				break;
			default:
				$response = apply_filters(
					'automator_send_webhook_default_response',
					wp_safe_remote_post( $webhook_url, $args ),
					$webhook_url,
					$args
				);
				break;
		}

		return $response;
	}

	/**
	 * Validate webhook URL to prevent SSRF attacks
	 *
	 * @param string $url The URL to validate
	 * @return bool True if URL is valid and safe, false otherwise
	 */
	public static function validate_webhook_url( $url ) {
		// First validate the URL format and protocol
		if ( ! wp_http_validate_url( $url ) ) {
			return false;
		}

		// Parse the URL for host
		$parsed_url = wp_parse_url( $url );
		$host       = isset( $parsed_url['host'] ) ? strtolower( $parsed_url['host'] ) : '';
		if ( empty( $host ) ) {
			return false;
		}

		// Block localhost and common internal hostnames
		$default_blocked_hosts = array(
			'localhost',
			'127.0.0.1',
			'::1',
			'internal',
			'local',
			'[::1]', // IPv6 localhost in brackets
		);

		/**
		 * Filter to add additional blocked hostnames
		 * Note: Default blocked hosts cannot be removed for security
		 *
		 * @param array $additional_blocked_hosts Array of additional hostnames to block
		 * @param string $host The current hostname being checked
		 * @return array
		 */
		$additional_blocked_hosts = apply_filters(
			'automator_send_webhook_blocked_webhook_hosts',
			array(),
			$host
		);

		// Merge default and additional blocked hosts, ensuring defaults cannot be removed
		$blocked_hosts = array_merge( $default_blocked_hosts, (array) $additional_blocked_hosts );
		if ( in_array( $host, $blocked_hosts, true ) ) {
			return false;
		}

		// Resolve hostname to IP
		$ip = gethostbyname( $host );
		// returns the hostname on failure.
		if ( $ip === $host ) {
			return false;
		}

		// Block AWS metadata endpoint and link-local addresses
		if ( '169.254.169.254' === $ip || 0 === strpos( $ip, '169.254.' ) ) {
			return false;
		}

		// Block private and reserved IP ranges
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return false;
		}

		return true;
	}

	/**
	 * Build multipart/form-data
	 *
	 * @param mixed[] $data
	 *
	 * @return string
	 */
	public function build_multipart_form_data( $data ) {

		$boundary = $this->boundary;

		if ( empty( $boundary ) ) {
			$boundary = '--' . sha1( time() );
		}

		/**
		 * @see <https://proxyman.io/posts/2021-06-24-preview-multipart-formdata>
		 */
		$separator = "\r\n";

		// Build the form-data body.
		$body_start = 'Content-Type: multipart/form-data; boundary=' . $boundary . $separator . $separator;

		$form_data_body = $body_start;

		$data_count = count( $data );

		$counter = 1;

		foreach ( (array) $data as $key => $value ) {

			$form_data_body .=
				$boundary
				. $separator
				. 'Content-Disposition: form-data; name="' . $key . '"'
				. $separator
				. 'Content-Type: text/plain'
				. $separator
				. $separator
				. $value
				. $separator;

			// Proper line breaks. The last form-data should only have one space.
			if ( $data_count < $counter ) {
				$form_data_body .= PHP_EOL;
			}

			++$counter;
		}

		$form_data_body .= $boundary . '--';

		return trim( $form_data_body );
	}

	/**
	 * @param $array
	 * @param bool $add_data
	 *
	 * @return array
	 */
	public static function get_leafs( $array, $add_data = false ) {
		$leafs = array();

		if ( ! is_array( $array ) ) {
			return $leafs;
		}

		$array_iterator    = new \RecursiveArrayIterator( $array );
		$iterator_iterator = new \RecursiveIteratorIterator( $array_iterator, \RecursiveIteratorIterator::LEAVES_ONLY );
		foreach ( $iterator_iterator as $key => $value ) {
			$keys = array();
			for ( $i = 0; $i < $iterator_iterator->getDepth(); $i++ ) { // phpcs:ignore Generic.CodeAnalysis.ForLoopWithTestFunctionCall.NotAllowed
				$keys[] = $iterator_iterator->getSubIterator( $i )->key();
			}
			$keys[]   = $key;
			$leaf_key = implode( apply_filters( 'automator_outgoing_webhook_array_key_in_token_separator', '|' ), $keys );

			//$leafs[ $leaf_key ] = $value;
			$leafs[] = array(
				'key'  => $leaf_key,
				'type' => self::value_maybe_of_type( $leaf_key, $value ),
				'data' => true === $add_data ? $value : '',
			);
		}

		return $leafs;
	}

	/**
	 * @param $key
	 * @param $value
	 *
	 * @return mixed|null
	 */
	public static function value_maybe_of_type( $key, $value ) {
		$type = 'text';

		if ( is_array( $value ) || is_object( $value ) ) {
			return apply_filters( 'automator_outgoing_webhook_value_of_type_array', $type, $key, $value );
		}

		if ( is_email( $value ) ) {
			$type = 'email';

			return apply_filters( 'automator_outgoing_webhook_value_of_type_email', $type, $key, $value );
		}

		if ( is_float( $value ) ) {
			$type = 'float';

			return apply_filters( 'automator_outgoing_webhook_value_of_type_float', $type, $key, $value );
		}

		if ( is_numeric( $value ) ) {
			$type = 'int';

			return apply_filters( 'automator_outgoing_webhook_value_of_type_int', $type, $key, $value );
		}

		if ( wp_http_validate_url( $value ) ) {
			$type = 'url';

			return apply_filters( 'automator_outgoing_webhook_value_of_type_url', $type, $key, $value );
		}

		return apply_filters( 'automator_outgoing_webhook_value_of_type_text', $type, $key, $value );
	}

	/**
	 * @param mixed[] $raw
	 * @param mixed[] $response
	 *
	 * @return array
	 */
	public static function before_hydrate_tokens( $raw = array(), $response = array() ) {

		if ( empty( $raw ) ) {
			return array();
		}

		$hydration_data = array();

		foreach ( $raw as $action_token ) {
			$tag                    = strtoupper( $action_token['key'] );
			$hydration_data[ $tag ] = $action_token['data'];
		}

		// Hydrate the "WEBHOOK_RESPONSE_BODY" action token.
		$hydration_data['WEBHOOK_RESPONSE_BODY'] = (string) wp_remote_retrieve_body( $response );
		$hydration_data['WEBHOOK_RESPONSE_CODE'] = wp_remote_retrieve_response_code( $response );

		return $hydration_data;
	}

	/**
	 * @param \WpOrg\Requests\Utility\CaseInsensitiveDictionary $header
	 *
	 * @return array
	 */
	public static function parse_headers( $header ) {

		if ( ! $header instanceof \WpOrg\Requests\Utility\CaseInsensitiveDictionary ) {
			return array();
		}

		if ( empty( $header->getAll() ) ) {
			return array();
		}

		$tokens = array();
		foreach ( $header->getAll() as $k => $v ) {
			$tokens[] = array(
				'key'  => "header|$k",
				'data' => self::esc_html_string( $v ),
				'type' => 'text',
			);
		}

		return $tokens;
	}

	/**
	 * Escaping for HTML blocks.
	 *
	 * @param mixed $value The value to parse.
	 * @param bool $is_html Whether the value has HTML contents or not.
	 *
	 * @return string
	 */
	public static function esc_html_string( $value = '' ) {

		// Return the empty value if its not scalar.
		if ( ! is_scalar( $value ) || ! is_string( $value ) ) {
			return '';
		}

		return esc_html( $value );
	}

	/**
	 * @param $tokens
	 *
	 * @return array
	 */
	public static function clean_tokens_before_save( $tokens ) {
		if ( empty( $tokens ) ) {
			return array();
		}

		foreach ( $tokens as $k => $v ) {
			$tokens[ $k ]['data'] = '';
		}

		return $tokens;
	}
}
