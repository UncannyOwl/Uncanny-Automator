<?php
/**
 * Live REST discovery and checked native dispatch for MCP.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest;

use Uncanny_Automator\App\Events\Media_Upload_Events;
use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Input;
use WP_Error;
use WP_REST_Request;

/**
 * Uses the registered native handlers; it never sends credential-bearing HTTP.
 */
final class Rest_Operation_Service {

	/** Contract and discovery output stay bounded independently of upload input. */
	private const MAX_INPUT_BYTES         = Rest_Operation_Policy::DEFAULT_MAX_INPUT_BYTES;
	private const MAX_INLINE_SCHEMA_BYTES = 65536;
	private const SCHEMA_CHUNK_BYTES      = 16384;


	/**
	 * Find short operation summaries, or inspect one registered/concrete path.
	 */
	public function discover( User_Context $context, array $params ) {
		$server = rest_get_server();
		$policy = new Rest_Operation_Policy();
		$path   = $params['path'] ?? '';
		$search = $params['search'] ?? '';
		$method = $params['method'] ?? '';
		$limit  = $params['limit'] ?? 20;
		$offset = $params['offset'] ?? 0;
		if ( isset( $params['schema_offset'] ) || isset( $params['schema_hash'] ) ) {
			if ( '' === $path || '' === $method || 0 !== $offset ) {
				return $this->error( 'rest_invalid_parameters', 'Schema reads require path, method and operation offset 0.', 400 );
			}
			if ( ( $params['schema_offset'] ?? 0 ) > 0 && ! isset( $params['schema_hash'] ) ) {
				return $this->error( 'rest_invalid_parameters', 'Continue a schema read with the schema_hash returned by its first chunk.', 400 );
			}
		}
		$routes = $server->get_routes();
		if ( '' !== $path && ! isset( $routes[ $path ] ) && ! self::valid_path( $path ) ) {
			return $this->error( 'rest_invalid_path', 'Use a registered route pattern or a concrete site-relative REST path.', 400 );
		}
		// A concrete path must describe the handler native dispatch will execute,
		// rather than every registered pattern that happens to match it.
		$selected = null;
		if ( '' !== $path && self::valid_path( $path ) ) {
			$selected = array();
			foreach ( $method ? array( $method ) : array( 'GET', 'HEAD', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $verb ) {
				$operation = $this->resolve( $path, $verb );
				if ( ! is_wp_error( $operation ) ) {
					$selected[ $verb ] = $operation;
				}
			}
		}
		// Native abilities share one route pattern. Offer concrete names so the
		// model gets the selected ability's inputs instead of an untyped runner.
		$ability_route = Rest_Operation_Policy::ABILITY_ROUTE;
		$ability_paths = array();
		if ( isset( $routes[ $ability_route ] ) && function_exists( 'wp_get_abilities' ) ) {
			foreach ( wp_get_abilities() as $ability ) {
				if ( $ability instanceof \WP_Ability ) {
					$ability_path = '/wp-abilities/v1/abilities/' . $ability->get_name() . '/run';
					if ( self::valid_path( $ability_path ) ) {
						$ability_paths[ $ability_path ] = $ability->get_name();
						$routes[ $ability_path ]        = $routes[ $ability_route ];
					}
				}
			}
			unset( $routes[ $ability_route ] );
		}
		$terms = preg_split( '/[\s|]+/u', trim( $search ), -1, PREG_SPLIT_NO_EMPTY );
		$terms = array_unique( array_map( 'strtolower', $terms ) );
		ksort( $routes, SORT_STRING );
		$operations = array();
		foreach ( $routes as $route => $handlers ) {
			$pattern = isset( $ability_paths[ $route ] ) ? $ability_route : $route;
			$options = $server->get_route_options( $pattern );
			if ( false === ( $options['show_in_index'] ?? true ) ) {
				continue;
			}
			if ( '' !== $path && null === $selected && $path !== $route ) {
				continue;
			}
			$search_score = 0;
			foreach ( $terms as $term ) {
				if ( false !== stripos( $route, $term ) ) {
					++$search_score;
				}
			}
			if ( ! empty( $terms ) && 0 === $search_score ) {
				continue;
			}
			// The first handler for a method is the one WordPress dispatches.
			$seen = array();
			foreach ( $handlers as $handler ) {
				if ( ! is_array( $handler ) || ! isset( $handler['methods'] ) ) {
					continue;
				}
				$methods = $handler['methods'];
				if ( ! empty( $methods['GET'] ) ) {
					$methods['HEAD'] = true;
				}
				foreach ( array_keys( array_filter( $methods ) ) as $verb ) {
					if ( isset( $seen[ $verb ] ) ) {
						continue;
					}
					$seen[ $verb ] = true;
					if ( '' !== $method && $method !== $verb ) {
						continue;
					}
					if ( null !== $selected && ( ! isset( $selected[ $verb ] ) || $selected[ $verb ]['route'] !== $pattern || $selected[ $verb ]['handler'] !== $handler || ( isset( $ability_paths[ $route ] ) && $path !== $route ) ) ) {
						continue;
					}
					$exposure = $policy->describe( $pattern, $verb, $handler, isset( $ability_paths[ $route ] ) ? array( 'name' => $ability_paths[ $route ] ) : array() );
					if ( null === $exposure ) {
						continue;
					}
					$reason       = $policy->unavailable_reason( $exposure, $context, 'rest_discover' );
					$entry        = array(
						'route'           => $route,
						'method'          => $verb,
						'namespace'       => $options['namespace'] ?? '',
						'read_only'       => $exposure['read_only'],
						'available'       => null === $reason,
						'reason'          => $reason,
						'max_input_bytes' => $exposure['max_input_bytes'],
					);
					$operations[] = array(
						'search_score' => $search_score,
						'order'        => count( $operations ),
						'entry'        => $entry,
						'handler'      => $handler,
						'policy'       => $exposure,
						'pattern'      => $pattern,
					);
				}
			}
		}
		if ( '' !== $path && empty( $operations ) ) {
			return $this->error( 'rest_operation_unavailable', 'No reviewed registered operation matches this path and method.', 404 );
		}
		if ( '' === $path && count( $terms ) > 1 ) {
			// Rank before paging; ties retain the existing route/handler order.
			usort(
				$operations,
				static function ( array $left, array $right ): int {
					$score_order = $right['search_score'] <=> $left['search_score'];
					return 0 !== $score_order ? $score_order : ( $left['order'] <=> $right['order'] );
				}
			);
		}
		$total = count( $operations );
		$page  = array();
		foreach ( array_slice( $operations, $offset, $limit ) as $operation ) {
			$entry = $operation['entry'];
			if ( '' !== $path ) {
				$contract = $this->contract( $operation['pattern'], $entry['method'], $operation['handler'], $operation['policy'] );
				if ( is_wp_error( $contract ) ) {
					return $contract;
				}
				$entry = $this->schema_entry( $entry, $contract, $params );
				if ( is_wp_error( $entry ) ) {
					return $entry;
				}
			}
			$page[] = $entry;
		}
		if ( strlen( (string) wp_json_encode( $page ) ) > self::MAX_INPUT_BYTES ) {
			return $this->error( 'rest_schema_too_large', 'This discovery page exceeds 256 KiB. Use a smaller limit or select one method. No partial schema was returned.', 413 );
		}
		return array(
			'operations'       => $page,
			'total'            => $total,
			'has_more'         => $offset + $limit < $total,
			'next_offset'      => $offset + $limit < $total ? $offset + $limit : null,
			'permission_check' => 'Native permissions are checked when the request runs.',
		);
	}

	/**
	 * Read one complete contract inline or bounded pieces of its JSON document.
	 *
	 * Chunks contain ASCII JSON (including escaped Unicode), so byte offsets
	 * never split UTF-8. Reassemble the strings before decoding the document.
	 */
	private function schema_entry( array $entry, array $contract, array $params ) {
		if ( isset( $params['schema_hash'] ) && $params['schema_hash'] !== $contract['schema_hash'] ) {
			return $this->error( 'rest_schema_changed', 'The operation definition changed. Restart discovery before combining schema chunks.', 409 );
		}
		$encoded = wp_json_encode( $contract );
		if ( false === $encoded ) {
			return $this->error( 'rest_invalid_schema', 'The operation definition could not be encoded. No partial schema was returned.', 500 );
		}
		$size = strlen( $encoded );
		if ( ! isset( $params['schema_offset'] ) && $size <= self::MAX_INLINE_SCHEMA_BYTES ) {
			return array_merge( $entry, $contract, array( 'schema_complete' => true ) );
		}
		if ( isset( $contract['upload'] ) ) {
			// The available memory can change between chunk requests. Return the
			// current transfer details separately so reassembled schema bytes stay stable.
			$entry['upload'] = $contract['upload'];
			unset( $contract['upload']['max_file_bytes'] );
			$encoded = wp_json_encode( $contract );
			if ( false === $encoded ) {
				return $this->error( 'rest_invalid_schema', 'The operation definition could not be encoded. No partial schema was returned.', 500 );
			}
			$size = strlen( $encoded );
		}
		$offset = $params['schema_offset'] ?? 0;
		if ( $offset >= $size ) {
			return $this->error( 'rest_invalid_parameters', 'schema_offset must be less than the schema total_bytes.', 400 );
		}
		$chunk                             = substr( $encoded, $offset, self::SCHEMA_CHUNK_BYTES );
		$next                              = $offset + strlen( $chunk );
		$entry['schema_hash']              = $contract['schema_hash'];
		$entry['schema_complete']          = false;
		$entry['schema_chunk']             = array(
			'encoding'    => 'json',
			'content'     => $chunk,
			'offset'      => $offset,
			'total_bytes' => $size,
			'next_offset' => $next < $size ? $next : null,
		);
		$entry['schema_read_instructions'] = 'Repeat rest_discover with the same site, path and method, schema_offset=next_offset and schema_hash. Concatenate content strings in offset order, then decode JSON only after next_offset is null. Each response is a fragment, not a complete schema.';
		if ( isset( $entry['upload'] ) ) {
			$entry['schema_read_instructions'] .= ' The separate upload field has the current transfer details and file limit.';
		}
		return $entry;
	}

	/**
	 * Resolve exactly the first registered native handler WordPress will use.
	 */
	public function resolve( string $path, string $method ) {
		if ( ! self::valid_path( $path ) ) {
			return $this->error( 'rest_invalid_path', 'Use a concrete site-relative REST path, without a URL, query string or encoded separators.', 400 );
		}
		// Mirror native namespace precedence, including overlapping namespaces.
		$server = rest_get_server();
		$sets   = array();
		foreach ( $server->get_namespaces() as $namespace ) {
			if ( 0 === strpos( trailingslashit( ltrim( $path, '/' ) ), $namespace ) ) {
				$sets[] = $server->get_routes( $namespace );
			}
		}
		$routes = $sets ? array_merge( ...$sets ) : $server->get_routes();
		foreach ( $routes as $route => $handlers ) {
			if ( ! $this->matches( $route, $path ) ) {
				continue;
			}
			foreach ( $handlers as $handler ) {
				if ( ! is_array( $handler ) ) {
					continue;
				}
				$checked_method = 'HEAD' === $method && empty( $handler['methods']['HEAD'] ) ? 'GET' : $method;
				if ( empty( $handler['methods'][ $checked_method ] ) ) {
					continue;
				}
				$options = $server->get_route_options( $route );
				if ( false === ( $options['show_in_index'] ?? true ) ) {
					return $this->error( 'rest_forbidden', 'This registered operation is not exposed through MCP.', 403 );
				}
				preg_match( '@^' . $route . '$@i', $path, $matches );
				$path_params = array_filter( $matches, 'is_string', ARRAY_FILTER_USE_KEY );
				$policies    = new Rest_Operation_Policy();
				$policy      = $policies->describe( $route, $method, $handler, $path_params );
				if ( null === $policy ) {
					// WordPress runs an ability with one method, set by its annotations.
					$exposed = Rest_Operation_Policy::ABILITY_ROUTE === $route ? $this->exposed_ability_method( $policies, $route, $handler, $path_params ) : null;
					return null === $exposed
						? $this->error( 'rest_forbidden', 'This registered operation is not exposed through MCP.', 403 )
						: $this->ability_method_error( ...$exposed );
				}
				return array(
					'route'       => $route,
					'handler'     => $handler,
					'policy'      => $policy,
					'path_params' => $path_params,
				);
			}
		}
		return $this->error( 'rest_no_route', 'No registered operation matches this path and method.', 404 );
	}

	/**
	 * Invoke native validation, permissions and callbacks as the actual executor.
	 *
	 * @param User_Context $context Verified executor and access.
	 * @param array $params Operation parameters.
	 * @param bool|null $dispatch_started Set when the native callback ran without refusing first, for any upload, or when dispatch threw.
	 * @return array|WP_Error Native result or a refusal.
	 */
	public function request( User_Context $context, array $params, ?bool &$dispatch_started = null ) {
		$dispatch_started = false;
		$operation        = $this->resolve( $params['path'], $params['method'] );
		if ( is_wp_error( $operation ) ) {
			return $operation;
		}
		$reason = ( new Rest_Operation_Policy() )->unavailable_reason( $operation['policy'], $context, 'rest_request' );
		if ( null !== $reason ) {
			return $this->error( 'rest_forbidden', $reason, 403 );
		}
		$contract = $this->contract( $operation['route'], $params['method'], $operation['handler'], $operation['policy'] );
		if ( is_wp_error( $contract ) ) {
			return $contract;
		}
		if ( isset( $params['schema_hash'] ) && $params['schema_hash'] !== $contract['schema_hash'] ) {
			return $this->error( 'rest_schema_changed', 'The operation inputs changed. Discover them again before retrying.', 409 );
		}
		$request = isset( $operation['policy']['upload'] )
			? new Media_Upload_Request( $params['method'], $params['path'] )
			: new WP_REST_Request( $params['method'], $params['path'] );
		$request->set_query_params( (array) ( $params['query'] ?? array() ) );
		if ( isset( $params['body'] ) ) {
			if ( isset( $operation['policy']['upload'] ) ) {
				$request->set_body_params( (array) $params['body'] );
			}
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( (object) $params['body'] ) );
		}
		$request->set_url_params( $operation['path_params'] );
		if ( isset( $operation['policy']['ability_name'] ) ) {
			self::normalize_ability_input( $request, $operation['policy']['ability_name'] );
		}
		if ( isset( $params['upload_reference'] ) && ! isset( $operation['policy']['upload'] ) ) {
			return Media_Upload_Transfer::error( 'upload_wrong_operation', 400 );
		}
		if ( isset( $operation['policy']['upload'] ) ) {
			$error = Media_Upload_Transfer::validate_metadata( $request );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
			$error = self::validate_request( $request, true, $operation['policy']['max_input_bytes'] );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
			$file = ( new Media_Upload_Transfer() )->take( $context, $params['upload_reference'] ?? '' );
			if ( is_wp_error( $file ) ) {
				return $file;
			}
			$request->set_body_params( (array) ( $params['body'] ?? array() ) );
			$request->set_header( 'Content-Type', $file['mime_type'] );
			$request->set_header( 'Content-Disposition', 'attachment; filename="' . $file['filename'] . '"' );
			$request->set_body( $file['bytes'] );
		}
		$error = self::validate_request( $request, true, $operation['policy']['max_input_bytes'], isset( $operation['policy']['upload'] ) );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		$error = self::validate_operation_request( $request, $operation['policy'] );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		$attachment_id    = 0;
		$may_have_changed = false;
		// Stays true if dispatch throws, and for uploads, whose single-use file is already taken.
		$dispatch_started = true;
		if ( $request instanceof Media_Upload_Request ) {
			$upload        = Media_Upload_Events::capture(
				$request,
				static function () use ( $request, $context, $operation ) {
					return Rest_Request_Scope::dispatch( $request, $context, $operation['handler'], $operation['policy'] );
				}
			);
			$response      = $upload['response'];
			$attachment_id = $upload['attachment_id'];
			if ( is_wp_error( $response ) ) {
				$response = rest_convert_error_to_response( $response );
			}
		} else {
			$response         = Rest_Request_Scope::dispatch( $request, $context, $operation['handler'], $operation['policy'], $may_have_changed );
			$dispatch_started = $may_have_changed;
		}
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		if ( isset( $operation['policy']['upload'] ) && ( $response->get_status() < 200 || $response->get_status() >= 300 ) ) {
			$data = $response->get_data();
			$response->set_data( Media_Upload_Transfer::native_error( is_array( $data ) ? $data : array(), $response->get_status(), $attachment_id ) );
		}
		if ( isset( $operation['policy']['response_fields'] ) ) {
			$data = $response->get_data();
			if ( $response->get_status() >= 200 && $response->get_status() < 300 ) {
				if ( ! is_array( $data ) ) {
					return $this->error( 'rest_invalid_response', 'WordPress returned an unexpected settings response.', 502 );
				}
				$response->set_data( array_intersect_key( Site_Editor_Operations::settings_fields( $data ), array_flip( $operation['policy']['response_fields'] ) ) );
			} else {
				$data = $data instanceof \stdClass ? get_object_vars( $data ) : $data;
				if ( ! is_array( $data ) ) {
					$response->set_data( null );
				} else {
					$filtered = array();
					$code     = $data['code'] ?? null;
					if ( is_int( $code ) || ( is_string( $code ) && strlen( $code ) <= 80 && 1 === preg_match( '/^[a-z][a-z0-9_.-]*$/iD', $code ) && 0 === preg_match( '/^(?:sk[-_](?:live|test|proj)[-_]|gh[pousr]_)/i', $code ) ) ) {
						$filtered['code'] = $code;
					}
					if ( is_string( $data['message'] ?? null ) && strlen( $data['message'] ) <= 4096 ) {
						$filtered['message'] = sanitize_text_field( $data['message'] );
					}
					$response->set_data( $filtered );
				}
			}
		}
		$headers = array_intersect_key( array_change_key_case( $response->get_headers(), CASE_LOWER ), array_flip( array( 'x-wp-total', 'x-wp-totalpages', 'allow', 'retry-after' ) ) );
		if ( isset( $headers['retry-after'] ) ) {
			$retry_after = $headers['retry-after'];
			if ( ! is_scalar( $retry_after ) || strlen( (string) $retry_after ) > 128 || preg_match( '/[\x00-\x1F\x7F]/', (string) $retry_after ) ) {
				unset( $headers['retry-after'] );
			} else {
				$headers['retry-after'] = (string) $retry_after;
			}
		}
		return array(
			'path'        => $params['path'],
			'method'      => $params['method'],
			'status'      => $response->get_status(),
			'headers'     => (object) $headers,
			'body'        => $response->get_data(),
			'schema_hash' => $contract['schema_hash'],
		);
	}

	/**
	 * Reject transport overrides before validation and after sanitization.
	 */
	public static function validate_request( WP_REST_Request $request, bool $check_size = true, int $max_input_bytes = Rest_Operation_Policy::DEFAULT_MAX_INPUT_BYTES, bool $binary_upload = false ): ?WP_Error {
		$query          = $request->get_query_params();
		$body           = $request->get_body_params();
		$json           = $request->get_json_params() ?? array();
		$keys           = array_merge( array_keys( $query ), array_keys( $body ), array_keys( $json ) );
		$forbidden      = array( '_method', '_jsonp', '_envelope', '_embed', 'rest_route', 'authorization', 'cookie', '_wpnonce', 'headers', 'x-http-method-override', 'x-automator-creds' );
		$effective_keys = array_merge( $keys, array_keys( $request->get_default_params() ) );
		$headers        = $request->get_headers();
		if ( isset( $headers['content_type'] ) && array( 'application/json' ) === $headers['content_type'] ) {
			unset( $headers['content_type'] );
		}
		if ( $binary_upload ) {
			$error = Media_Upload_Transfer::validate_native_request( $request );
			if ( is_wp_error( $error ) ) {
				return $error;
			}
			unset( $headers['content_type'], $headers['content_disposition'] );
		}
		if ( $request->get_file_params() || array_intersect( array_map( 'strtolower', $effective_keys ), $forbidden ) || $headers ) {
			return new WP_Error( 'rest_invalid_parameters', 'REST transport overrides, credentials and embedding are not supported.', array( 'status' => 400 ) );
		}
		if ( array_intersect( $keys, array_keys( $request->get_url_params() ) ) ) {
			return new WP_Error( 'rest_invalid_parameters', 'Route parameters belong in the path, not query or body.', array( 'status' => 400 ) );
		}
		if ( ! $check_size ) {
			return null;
		}
		if ( $max_input_bytes < 1 || $max_input_bytes > Rest_Operation_Policy::MAX_REVIEWED_INPUT_BYTES ) {
			return new WP_Error( 'rest_invalid_parameters', 'The reviewed input limit is invalid.', array( 'status' => 400 ) );
		}
		$encoded = wp_json_encode( array( $query, $body, $json ) );
		if ( false === $encoded || strlen( $encoded ) > $max_input_bytes ) {
			$message = Rest_Operation_Policy::DEFAULT_MAX_INPUT_BYTES === $max_input_bytes
				? 'REST inputs exceed the 256 KiB limit.'
				: sprintf( 'REST inputs exceed the %d-byte limit.', $max_input_bytes );
			return new WP_Error(
				'rest_input_too_large',
				$message,
				array(
					'status'          => 413,
					'max_input_bytes' => $max_input_bytes,
				)
			);
		}
		return null;
	}

	/**
	 * Give an ability run the input its schema accepts as "no arguments", in
	 * the place WordPress reads it: the query for GET and DELETE, the JSON
	 * body otherwise. The request is rewritten only when the input changes.
	 */
	private static function normalize_ability_input( WP_REST_Request $request, string $name ): void {
		$ability = function_exists( 'wp_has_ability' ) && function_exists( 'wp_get_ability' ) && wp_has_ability( $name ) ? wp_get_ability( $name ) : null;
		if ( ! $ability instanceof \WP_Ability ) {
			return;
		}
		$in_query = in_array( $request->get_method(), array( 'GET', 'DELETE' ), true );
		$params   = $in_query ? $request->get_query_params() : ( $request->get_json_params() ?? array() );
		$supplied = $params['input'] ?? null;
		$input    = Ability_Input::normalize( $supplied, $ability->get_input_schema() );
		if ( $input === $supplied ) {
			return;
		}
		unset( $params['input'] );
		if ( null !== $input ) {
			$params['input'] = $input;
		}
		if ( $in_query ) {
			$request->set_query_params( $params );
			return;
		}
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( (object) $params ) );
	}

	/**
	 * Enforce the reviewed JSON/query shape without altering literal source values.
	 */
	public static function validate_operation_request( WP_REST_Request $request, array $policy ): ?WP_Error {
		if ( ! isset( $policy['request_schema'] ) ) {
			return null;
		}
		if ( $request->get_body_params() ) {
			return new WP_Error( 'rest_invalid_parameters', 'This operation requires a JSON body.', array( 'status' => 400 ) );
		}
		$input = array(
			'query' => $request->get_query_params(),
			'body'  => $request->get_json_params() ?? array(),
		);
		$valid = rest_validate_value_from_schema( $input, $policy['request_schema'], 'request' );
		if ( is_wp_error( $valid ) ) {
			$data  = array( 'status' => 400 );
			$forms = self::accepted_forms( $input, $policy['request_schema'] );
			if ( null !== $forms ) {
				$data['next_action'] = $forms;
			}
			return new WP_Error( 'rest_invalid_parameters', self::field_guidance( $input, $policy['request_schema'] ) ?? $valid->get_error_message(), $data );
		}
		return null;
	}

	/**
	 * The description of the first reviewed field the input breaks.
	 *
	 * WordPress names only the value it expected, and prints false as nothing.
	 */
	private static function field_guidance( array $input, array $schema ): ?string {
		foreach ( array( 'query', 'body' ) as $part ) {
			$fields = $schema['properties'][ $part ]['properties'] ?? array();
			foreach ( is_array( $fields ) ? $fields : array() as $name => $field ) {
				if ( ! is_array( $field ) || ! is_string( $field['description'] ?? null ) || ! is_array( $input[ $part ] ?? null ) || ! array_key_exists( $name, $input[ $part ] ) ) {
					continue;
				}
				if ( is_wp_error( rest_validate_value_from_schema( $input[ $part ][ $name ], $field, $name ) ) ) {
					return $field['description'];
				}
			}
		}
		return null;
	}

	/**
	 * Name the accepted forms of the query or body that matches none of them.
	 *
	 * WordPress reports only that a value matched none of a oneOf, which leaves
	 * a caller without the field it missed.
	 */
	private static function accepted_forms( array $input, array $schema ): ?string {
		foreach ( array( 'query', 'body' ) as $part ) {
			$alternatives = $schema['properties'][ $part ]['oneOf'] ?? null;
			if ( ! is_array( $alternatives ) || ! is_wp_error( rest_validate_value_from_schema( $input[ $part ], $schema['properties'][ $part ], $part ) ) ) {
				continue;
			}
			$forms = array();
			foreach ( $alternatives as $alternative ) {
				$fields = array();
				foreach ( (array) ( $alternative['required'] ?? array() ) as $field ) {
					$enum     = $alternative['properties'][ $field ]['enum'] ?? null;
					$fields[] = is_array( $enum ) && 1 === count( $enum ) && is_scalar( $enum[0] ) ? $field . '=' . $enum[0] : $field;
				}
				$forms[] = array() === $fields ? 'no fields' : implode( ', ', $fields );
			}
			return sprintf( 'Send %s in one of these forms: %s.', $part, implode( '; ', $forms ) );
		}
		return null;
	}

	/** Concrete paths exclude ambiguous URL encodings and traversal. */
	private static function valid_path( string $path ): bool {
		return strlen( $path ) <= 2048 && ( 1 === preg_match( '#^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]+$#D', $path ) || Site_Editor_Operations::valid_path( $path ) );
	}

	/**
	 * Matches.
	 *
	 * @param string $route The route.
	 * @param string $path The path.
	 * @return bool
	 */
	private function matches( string $route, string $path ): bool {
		return 1 === preg_match( '@^' . $route . '$@i', $path );
	}

	/**
	 * Publish data-only native input rules; do not execute schema callbacks.
	 */
	private function contract( string $route, string $method, array $handler, array $policy ) {
		$arguments = array();
		$ability   = null;
		// WordPress binds named route captures from the URL, not query or body.
		preg_match_all( '/\(\?(?:P?<|\')([a-zA-Z_][a-zA-Z0-9_]*)(?:>|\')/', $route, $captures );
		$path_parameters = array_fill_keys( $captures[1], (object) array() );
		if ( isset( $policy['ability_name'] ) ) {
			// Rest_Operation_Policy::ability_policy() sets ability_name only when wp_has_ability() and
			// wp_get_ability() exist. The ability may be unregistered since, and WordPress reports
			// looking up an unregistered name as incorrect usage, so ask first.
			$ability = wp_has_ability( $policy['ability_name'] ) ? wp_get_ability( $policy['ability_name'] ) : null;
			if ( ! $ability instanceof \WP_Ability ) {
				return $this->error( 'rest_operation_unavailable', 'The selected ability is no longer registered.', 404 );
			}
			$handler['args']['input'] = $ability->get_input_schema();
		}
		try {
			foreach ( $handler['args'] ?? array() as $name => $schema ) {
				$schema = $this->schema_data( (array) $schema );
				if ( array_key_exists( $name, $path_parameters ) ) {
					$path_parameters[ $name ] = $schema;
				} else {
					$arguments[ $name ] = $schema;
				}
			}
			$output         = $ability instanceof \WP_Ability && $ability->get_output_schema() ? $this->schema_data( $ability->get_output_schema() ) : null;
			$request_schema = isset( $policy['request_schema'] ) ? $this->schema_data( $policy['request_schema'] ) : null;
		} catch ( \LengthException $error ) {
			return $this->error( 'rest_schema_too_large', $error->getMessage(), 413 );
		}
		$stable_policy = $policy;
		if ( isset( $stable_policy['upload']['max_file_bytes'] ) ) {
			// This is a live resource limit, not a change to native input rules.
			unset( $stable_policy['upload']['max_file_bytes'] );
		}
		$definition = array( $route, $method, $arguments, $stable_policy );
		if ( $path_parameters ) {
			$definition[] = $path_parameters;
		}
		if ( $ability instanceof \WP_Ability ) {
			$definition[] = $output;
		}
		$encoded = wp_json_encode( $definition );
		if ( false === $encoded ) {
			return $this->error( 'rest_invalid_schema', 'The operation definition could not be encoded. No partial schema was returned.', 500 );
		}
		$contract = array(
			'arguments'       => (object) $arguments,
			'schema_hash'     => hash( 'sha256', $encoded ),
			'max_input_bytes' => $policy['max_input_bytes'],
		);
		if ( $path_parameters ) {
			$contract['path_parameters'] = (object) $path_parameters;
		}
		if ( isset( $policy['upload'] ) ) {
			$contract['upload'] = $policy['upload'];
		}
		if ( $ability instanceof \WP_Ability ) {
			$contract['output_schema'] = $output;
		}
		if ( isset( $policy['request_schema'] ) ) {
			$contract['request_schema'] = $request_schema;
		}
		if ( isset( $policy['response_fields'] ) ) {
			$contract['arguments']       = (object) array_intersect_key( $arguments, array_flip( $policy['response_fields'] ) );
			$contract['response_fields'] = $policy['response_fields'];
		}
		if ( isset( $policy['native_tool'] ) ) {
			$contract['native_tool'] = $policy['native_tool'];
		}
		return $contract;
	}

	/**
	 * Schema data.
	 *
	 * @param array $schema The schema.
	 * @param int $depth The depth.
	 * @return array
	 */
	private function schema_data( array $schema, int $depth = 0 ): array {
		if ( $depth > 16 ) {
			throw new \LengthException( 'This input definition exceeds 16 schema levels. No partial schema was returned.' );
		}
		// Use the native helper available since WordPress 5.6. Keep required
		// flags, which WordPress validates but its route-index list omits.
		$schema = array_intersect_key( $schema, array_flip( array_merge( rest_get_allowed_schema_keywords(), array( 'required' ) ) ) );
		foreach ( array( 'properties', 'patternProperties' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
				foreach ( $schema[ $keyword ] as $name => $child ) {
					$schema[ $keyword ][ $name ] = $this->schema_data( (array) $child, $depth + 1 );
				}
				$schema[ $keyword ] = (object) $schema[ $keyword ];
			}
		}
		foreach ( array( 'items', 'additionalProperties' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
				$schema[ $keyword ] = $this->schema_data( $schema[ $keyword ], $depth + 1 );
			}
		}
		foreach ( array( 'oneOf', 'anyOf' ) as $keyword ) {
			if ( isset( $schema[ $keyword ] ) && is_array( $schema[ $keyword ] ) ) {
				foreach ( $schema[ $keyword ] as $index => $child ) {
					$schema[ $keyword ][ $index ] = $this->schema_data( (array) $child, $depth + 1 );
				}
			}
		}
		if ( 'object' === ( $schema['type'] ?? null ) && array() === ( $schema['default'] ?? null ) ) {
			$schema['default'] = (object) array();
		}
		return $schema;
	}

	/**
	 * Error.
	 *
	 * @param string $code The code.
	 * @param string $message The message.
	 * @param int $status The status.
	 * @return WP_Error
	 */
	private function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}

	/**
	 * The method an ability's run route is exposed with, and its policy.
	 *
	 * @return array{0:string,1:array}|null
	 */
	private function exposed_ability_method( Rest_Operation_Policy $policies, string $route, array $handler, array $path_params ): ?array {
		foreach ( array( 'GET', 'POST', 'DELETE' ) as $candidate ) {
			$policy = $policies->describe( $route, $candidate, $handler, $path_params );
			if ( null !== $policy ) {
				return array( $candidate, $policy );
			}
		}
		return null;
	}

	/**
	 * WordPress's own wrong-method error for an ability, with the way to retry.
	 */
	private function ability_method_error( string $method, array $policy ): WP_Error {
		$messages = array(
			'GET'    => 'Read-only abilities require GET method.',
			'POST'   => 'Abilities that perform updates require POST method.',
			'DELETE' => 'Abilities that perform destructive actions require DELETE method.',
		);
		$next     = sprintf( 'Send this request again with method %s and the input in %s', $method, 'POST' === $method ? 'body.input' : 'query.input' );
		if ( in_array( 'run_ability', $policy['tools'] ?? array(), true ) ) {
			$next .= sprintf( ', or call run_ability with ability "%s"', $policy['ability_name'] );
		}

		return new WP_Error(
			'rest_ability_invalid_method',
			$messages[ $method ],
			array(
				'status'      => 405,
				'next_action' => $next . '.',
			)
		);
	}
}
