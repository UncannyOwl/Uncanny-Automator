<?php

namespace Uncanny_Automator;

use DOMDocument;
use SimpleXMLElement;
use Uncanny_Automator\App\Infrastructure\Http\Non_Public_Ranges;
use Uncanny_Automator\App\Infrastructure\Http\Redirect_Hop_Check;

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
	 * The multipart boundary of the current form-data request.
	 *
	 * get_content_type() sets a new one for each request, and the body is built after the
	 * headers, so its parts are delimited by the boundary the Content-Type header names.
	 *
	 * @var string|null
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
		add_action( 'automator_event_recipe_changed', array( $this, 'mask_saved_authorization' ) );
		add_filter( 'automator_item_structural_meta_keys', array( $this, 'add_structural_keys' ) );
		add_filter( 'automator_item_copy_meta_keys', array( $this, 'add_copy_meta_keys' ) );
	}

	/**
	 * Keep the webhook action's item metadata off every read of its fields: the
	 * response-token cache test sends write, and the stored Authorization secret,
	 * which only the run reads.
	 *
	 * @param mixed $keys Structural meta keys collected so far.
	 * @return array
	 */
	public function add_structural_keys( $keys ) {
		$keys[] = 'webhook_response_tokens';
		$keys[] = 'WEBHOOK_AUTHORIZATIONS_ORIGINAL';
		return $keys;
	}

	/**
	 * Keep the stored Authorization secret on a same-site copy of a webhook action:
	 * the copy's field holds the same masked value, and the run sends the secret
	 * behind it. An export never carries it.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $keys Copy meta keys collected so far.
	 * @return array
	 */
	public function add_copy_meta_keys( $keys ) {
		$keys   = (array) $keys;
		$keys[] = 'WEBHOOK_AUTHORIZATIONS_ORIGINAL';
		return $keys;
	}

	/**
	 * Store a webhook action's new Authorization masked, keeping the secret in
	 * `WEBHOOK_AUTHORIZATIONS_ORIGINAL`, which the run sends.
	 *
	 * Listens to `automator_event_recipe_changed`, so it runs for builder, MCP and
	 * automator_*() saves alike, and a refused save, which fires no event, writes
	 * nothing. An emptied or tokenised field drops the stored secret so the field's
	 * own value is sent; the masked value the builder echoes back is left alone.
	 *
	 * @param object $event The recipe_changed event.
	 *
	 * @return void
	 */
	public function mask_saved_authorization( $event ) {
		if ( 'action' !== $event->item_type() || ! in_array( $event->operation(), array( 'created', 'updated' ), true ) ) {
			return;
		}

		$action_id = (int) $event->item_id();
		if ( ! metadata_exists( 'post', $action_id, 'WEBHOOK_AUTHORIZATIONS' ) ) {
			return;
		}

		$authorization = (string) get_post_meta( $action_id, 'WEBHOOK_AUTHORIZATIONS', true );
		if ( '' === $authorization || preg_match( '/\{\{.*?\}\}/', $authorization ) ) {
			delete_post_meta( $action_id, 'WEBHOOK_AUTHORIZATIONS_ORIGINAL' );
			return;
		}

		// The builder echoes the masked value back, and an import carries it without its secret.
		if ( $this->is_masked_authorization( $authorization, (string) get_post_meta( $action_id, 'WEBHOOK_AUTHORIZATIONS_ORIGINAL', true ) ) ) {
			return;
		}

		$secret = trim( $authorization, '*' );
		update_post_meta( $action_id, 'WEBHOOK_AUTHORIZATIONS_ORIGINAL', addslashes( $secret ) );
		update_post_meta( $action_id, 'WEBHOOK_AUTHORIZATIONS', addslashes( $this->hide_string( $secret ) ) );
	}

	/**
	 * Whether a stored Authorization is a masked value rather than one typed in.
	 *
	 * With a stored secret, only that secret's exact masked form counts. Without one,
	 * asterisks followed by at most three characters is a mask whose secret stayed behind.
	 *
	 * @since 8.0.0
	 *
	 * @param string $authorization The field's stored value.
	 * @param string $original      The stored secret, or '' when there is none.
	 *
	 * @return bool
	 */
	private function is_masked_authorization( string $authorization, string $original ): bool {
		if ( '' !== $original ) {
			return $this->hide_string( $original ) === $authorization;
		}

		return 1 === preg_match( '/^\*+.{0,3}$/s', $authorization );
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
	 * Outgoing webhook request type
	 *
	 * @param $data
	 *
	 * @return mixed|void
	 */
	public function request_type( $data ) {
		// A webhook saved by Pro 1.x–2.1 stored no method: it was always a POST.
		$method = isset( $data['ACTION_EVENT'] ) ? $data['ACTION_EVENT'] : 'POST';

		switch ( $method ) {
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
				if ( 'POST' !== $method && isset( $data['ACTION_EVENT_custom'] ) ) {
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
		// GET sends the Body as URL-encoded query parameters. The builder hides "Data format" for GET,
		// so a format stored before the owner chose GET does not apply.
		if ( isset( $data['ACTION_EVENT'] ) && 'GET' === $data['ACTION_EVENT'] ) {
			return 'x-www-form-urlencoded';
		}

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
		// Data format => the Content-Type it is sent with.
		$supported_data_types = array(
			'x-www-form-urlencoded' => 'application/x-www-form-urlencoded',
			'form-data'             => 'multipart/form-data',
			'json'                  => 'application/json',
			'plain'                 => 'text/plain',
			'binary'                => 'application/binary',
			'html'                  => 'text/html',
			'xml'                   => 'application/xml',
			// GraphQL over HTTP: a JSON object of the query, its variables and the operation name.
			'GraphQL'               => 'application/json',
			'raw'                   => 'text/plain',
		);
		if ( 'none' === $data_type ) {
			$data_type = 'raw';
		}
		// A Raw body is sent as typed, so a Content-Type header the owner added describes it.
		if ( 'raw' === $data_type && $this->has_header( $headers, 'Content-Type' ) ) {
			return $headers;
		}
		if ( is_string( $data_type ) && isset( $supported_data_types[ $data_type ] ) ) {
			$type = $supported_data_types[ $data_type ];
			if ( 'form-data' === $data_type ) {
				$this->boundary = $this->new_boundary();
				$type           = $type . '; boundary=' . $this->boundary;
			}
			$headers['Content-Type'] = $type;

			return $headers;
		}

		$headers['Content-Type'] = 'application/x-www-form-urlencoded';

		return $headers;
	}

	/**
	 * Whether the headers hold one with this name, in any letter case.
	 *
	 * @since 8.0.1
	 *
	 * @param array  $headers Header name => value.
	 * @param string $name    Header name.
	 *
	 * @return bool
	 */
	private function has_header( $headers, $name ) {
		foreach ( array_keys( (array) $headers ) as $key ) {
			if ( 0 === strcasecmp( (string) $key, $name ) ) {
				return true;
			}
		}

		return false;
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

		$authorization = isset( $data['WEBHOOK_AUTHORIZATIONS'] ) ? $data['WEBHOOK_AUTHORIZATIONS'] : null;
		$original      = get_post_meta( $action_id, 'WEBHOOK_AUTHORIZATIONS_ORIGINAL', true );
		$original      = is_scalar( $original ) ? (string) $original : '';

		// The stored secret stands in for the field only while the field holds its masked form: a save that
		// failed before the masker ran leaves the new value in the field. A mask with no secret behind it
		// (an imported action) sends nothing until the owner enters one.
		if ( is_string( $authorization ) && '' !== $authorization && $this->is_masked_authorization( $authorization, $original ) ) {
			$authorization = $original;
		}

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
				'GraphQL',
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
			case 'GraphQL':
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
				// A wrapper that is empty or not XML fails the request with the reason (SimpleXMLElement throws
				// for one that is not XML); it is never sent as the body.
				$xml_body_wrapper = apply_filters( 'automator_send_webhook_xml_body', '<body></body>', $fields );
				if ( empty( $xml_body_wrapper ) ) {
					throw new \Exception( esc_html__( 'XML body wrapper cannot be empty. Please use `automator_send_webhook_xml_body` filter to define a wrapper.', 'uncanny-automator' ) );
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
				break;
			case 'x-www-form-urlencoded':
				if ( $is_check_sample ) {
					$fields = wp_json_encode( $fields, JSON_PRETTY_PRINT );
				} else {
					$fields = $original;
				}
				break;
			case 'raw':
			case 'none':
				// The values as typed, one per line, without their keys.
				$fields = implode( "\n", $fields );
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

		// Redirects are followed, each one checked: a public URL must not hand the webhook on to an
		// address validate_webhook_url() would refuse.
		return Redirect_Hop_Check::around(
			static function () use ( $webhook_url, $args, $request_type ) {
				return self::send_webhook_request( $webhook_url, $args, $request_type );
			}
		);
	}

	/**
	 * Send the webhook request with the method the action chose.
	 *
	 * @since 8.0.0
	 *
	 * @param string $webhook_url  The checked webhook URL.
	 * @param array  $args         Request args.
	 * @param string $request_type HTTP method.
	 *
	 * @return array|mixed|\WP_Error
	 */
	private static function send_webhook_request( $webhook_url, $args, $request_type ) {

		switch ( $request_type ) {
			case 'POST':
				$response = wp_safe_remote_post( $webhook_url, $args );
				break;
			case 'GET':
				// A GET has no body: its fields go in the query string, URL-encoded once.
				$fields = isset( $args['body'] ) ? $args['body'] : null;
				if ( null !== $fields && '' !== $fields && ! is_array( $fields ) ) {
					return new \WP_Error(
						'invalid_get_body',
						esc_html__( 'A GET request sends the webhook data as key/value pairs in the URL, so it cannot send a body formatted as text.', 'uncanny-automator' )
					);
				}
				$url          = self::add_query_fields( $webhook_url, is_array( $fields ) ? $fields : array() );
				$args['body'] = null;
				$response     = wp_safe_remote_get( $url, $args );
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
	 * The URL with the fields added to its query string, URL-encoded the way WordPress encodes a
	 * form body. A field whose name the URL already carries replaces that parameter.
	 *
	 * @since 8.0.1
	 *
	 * @param string  $url    Webhook URL.
	 * @param mixed[] $fields Body fields.
	 *
	 * @return string
	 */
	private static function add_query_fields( $url, array $fields ) {
		$query = http_build_query( $fields, '', '&' );
		if ( '' === $query ) {
			return $url;
		}

		// add_query_arg() leaves what it is given unencoded, so it gets the pairs already encoded.
		$pairs = array();
		foreach ( explode( '&', $query ) as $pair ) {
			list( $key, $value ) = array_pad( explode( '=', $pair, 2 ), 2, '' );
			$pairs[ $key ]       = $value;
		}

		return add_query_arg( $pairs, $url );
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

		// An IP address needs no lookup: gethostbyname() returns it unchanged, which reads as a failed
		// lookup. It goes straight to the range checks below.
		if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$ip = $host;
		} else {
			// Resolve hostname to IP
			$ip = gethostbyname( $host );
			// returns the hostname on failure.
			if ( $ip === $host ) {
				return false;
			}
		}

		// Refuse every range that is not public: private, loopback, link-local (the cloud metadata address
		// included), carrier-grade NAT, multicast, documentation, benchmarking and reserved, IPv4 and IPv6.
		return ! Non_Public_Ranges::includes( $ip );
	}

	/**
	 * Build multipart/form-data
	 *
	 * @param mixed[] $data
	 *
	 * @return string
	 */
	public function build_multipart_form_data( $data ) {

		// The parts are delimited by the boundary the request's Content-Type header names. A body built
		// without headers (the builder's payload preview) gets a boundary of its own.
		if ( empty( $this->boundary ) ) {
			$this->boundary = $this->new_boundary();
		}

		$delimiter = '--' . $this->boundary;

		/**
		 * @see <https://proxyman.io/posts/2021-06-24-preview-multipart-formdata>
		 */
		$separator = "\r\n";

		// Build the form-data body.
		$body_start = 'Content-Type: multipart/form-data; boundary=' . $this->boundary . $separator . $separator;

		$form_data_body = $body_start;

		$data_count = count( $data );

		$counter = 1;

		foreach ( (array) $data as $key => $value ) {

			$form_data_body .=
				$delimiter
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

		$form_data_body .= $delimiter . '--';

		return trim( $form_data_body );
	}

	/**
	 * A multipart boundary no other request shares.
	 *
	 * @since 8.0.1
	 *
	 * @return string
	 */
	private function new_boundary() {
		return sha1( uniqid( '', true ) );
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
