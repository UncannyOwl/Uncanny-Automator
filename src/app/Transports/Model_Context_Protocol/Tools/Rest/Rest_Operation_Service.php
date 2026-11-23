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
use WP_REST_Response;

/**
 * Uses the registered native handlers; it never sends credential-bearing HTTP.
 */
final class Rest_Operation_Service {

	public const NO_UPDATE_FIELDS = 'rest_no_update_fields';

	/** Contract and discovery output stay bounded independently of upload input. */
	private const MAX_INPUT_BYTES         = Rest_Operation_Policy::DEFAULT_MAX_INPUT_BYTES;
	private const MAX_INLINE_SCHEMA_BYTES = 65536;
	private const SCHEMA_CHUNK_BYTES      = 16384;

	private const UNEXPOSED_OPERATION         = 'This registered operation is not exposed through MCP.';
	private const REVISION_DELETE_UNAVAILABLE = 'Revision deletion is unavailable through MCP. Discover an available GET operation to read revisions.';

	/** WordPress trims any response to these fields, yet no route registers the argument. */
	private const RESPONSE_FIELDS_ARGUMENT = array(
		'type'        => 'string',
		'description' => 'Optional. Comma-separated response fields to return instead of the whole item, such as id,status,title.rendered. A list is trimmed item by item. Use dot paths only for supported nested fields. Some WordPress controllers return whole objects, such as labels for /wp/v2/types/post. A response that maps names to items, such as /wp/v2/types, needs both the names and item fields, such as post,page,name,slug. Leave out _links unless you will follow one.',
	);

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
			$path = self::registered_template_route( $path, array_keys( $routes ) );
			if ( null === $path ) {
				return $this->error( 'rest_invalid_path', 'Use a registered route pattern or a concrete site-relative REST path.' . self::missing_leading_slash_hint( $params['path'], $routes ), 400 );
			}
		}
		// A concrete path must describe the handler native dispatch will execute,
		// rather than every registered pattern that happens to match it.
		$selected         = null;
		$revision_blocked = false;
		if ( '' !== $path && self::valid_path( $path ) ) {
			$selected = array();
			foreach ( $method ? array( $method ) : array( 'GET', 'HEAD', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $verb ) {
				$operation = $this->resolve( $path, $verb );
				if ( ! is_wp_error( $operation ) ) {
					$selected[ $verb ] = $operation;
				} elseif ( '' !== $method && 'rest_forbidden' === $operation->get_error_code()
					&& self::REVISION_DELETE_UNAVAILABLE === $operation->get_error_message() ) {
					$revision_blocked = true;
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
		$operations    = array();
		$hidden_upload = null;
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
				$methods        = $handler['methods'];
				$synthetic_head = empty( $methods['HEAD'] ) && ! empty( $methods['GET'] );
				if ( $synthetic_head ) {
					$methods['HEAD'] = true;
				}
				foreach ( array_keys( array_filter( $methods ) ) as $verb ) {
					if ( isset( $seen[ $verb ] ) ) {
						continue;
					}
					$seen[ $verb ] = true;
					// A GET fallback still takes precedence over later HEAD handlers.
					if ( 'HEAD' === $verb && $synthetic_head && 'HEAD' !== $method ) {
						continue;
					}
					if ( '' !== $method && $method !== $verb ) {
						continue;
					}
					if ( null !== $selected && ( ! isset( $selected[ $verb ] ) || $selected[ $verb ]['route'] !== $pattern || $selected[ $verb ]['handler'] !== $handler || ( isset( $ability_paths[ $route ] ) && $path !== $route ) ) ) {
						continue;
					}
					$exposure = $policy->describe( $pattern, $verb, $handler, isset( $ability_paths[ $route ] ) ? array( 'name' => $ability_paths[ $route ] ) : array() );
					if ( null === $exposure ) {
						if ( '' !== $path && '' !== $method && null === $selected ) {
							if ( Rest_Operation_Policy::REVISION_DELETE === Rest_Operation_Policy::unsupported_core_operation( $verb, $handler['callback'] ?? null ) ) {
								$revision_blocked = true;
							}
						}
						continue;
					}
					$reason = $policy->unavailable_reason( $exposure, $context, 'rest_discover' );
					if ( Rest_Operation_Policy::requires_external_media_transfer( $exposure, $context ) ) {
						$hidden_upload = $reason;
						continue;
					}
					$entry        = array(
						'route'           => $route,
						'method'          => $verb,
						'namespace'       => $options['namespace'] ?? '',
						'read_only'       => $exposure['read_only'],
						'available'       => null === $reason,
						'reason'          => self::explain_unavailability( $reason ),
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
			if ( $revision_blocked ) {
				return $this->error( 'rest_forbidden', self::REVISION_DELETE_UNAVAILABLE, 403 );
			}
			$message = null !== $hidden_upload
				? self::explain_unavailability( $hidden_upload )
				: 'No reviewed registered operation matches this path and method.';
			return $this->error( 'rest_operation_unavailable', $message, 404 );
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
			// A hash from another method or path fails the same way as a changed definition.
			return $this->error( 'rest_schema_changed', 'This schema_hash does not match the current definition of this path and method. It came from another method or path, or the definition changed. Restart discovery for this exact path and method before combining schema chunks.', 409 );
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
			// Keep the transfer contract in one place. Its live file limit can
			// change between chunks, and a hosted caller uses its own upload flow.
			$entry['upload'] = $contract['upload'];
			unset( $contract['upload'] );
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
	public function resolve( string $path, string $method, ?User_Context $context = null ) {
		if ( ! self::valid_path( $path ) ) {
			return $this->error( 'rest_invalid_path', 'Use a concrete site-relative REST path, without a URL, query string or encoded separators.' . self::missing_leading_slash_hint( $path ), 400 );
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
					return $this->error( 'rest_forbidden', self::unexposed_operation_message( $path, $method, $handler ), 403 );
				}
				preg_match( '@^' . $route . '$@i', $path, $matches );
				$path_params = array_filter( $matches, 'is_string', ARRAY_FILTER_USE_KEY );
				$policies    = new Rest_Operation_Policy();
				$policy      = $policies->describe( $route, $method, $handler, $path_params );
				if ( null === $policy ) {
					// WordPress runs an ability with one method, set by its annotations.
					$exposed = Rest_Operation_Policy::ABILITY_ROUTE === $route ? $this->exposed_ability_method( $policies, $route, $handler, $path_params ) : null;
					if ( null === $exposed ) {
						return $this->error( 'rest_forbidden', self::unexposed_operation_message( $path, $method, $handler ), 403 );
					}
					$refusal = null === $context ? null : $this->operation_refusal( $exposed[1], $context );
					return $refusal ?? $this->ability_method_error( ...$exposed );
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
	 * @param array|null $feedback Verified post state and request notices for the tool envelope.
	 * @return array|WP_Error Native result or a refusal.
	 */
	public function request( User_Context $context, array $params, ?bool &$dispatch_started = null, ?array &$feedback = null ) {
		$dispatch_started = false;
		$feedback         = array();
		$operation        = $this->resolve( $params['path'], $params['method'], $context );
		if ( is_wp_error( $operation ) ) {
			return 'rest_no_route' === $operation->get_error_code()
				? $this->explain_missing_operation( $params['path'], $params['method'], $context, $operation )
				: $operation;
		}
		$feedback['read_only'] = $operation['policy']['read_only'];
		$refusal               = $this->operation_refusal( $operation['policy'], $context );
		if ( null !== $refusal ) {
			return $refusal;
		}
		$contract = $this->contract( $operation['route'], $params['method'], $operation['handler'], $operation['policy'] );
		if ( is_wp_error( $contract ) ) {
			return $contract;
		}
		if ( isset( $params['schema_hash'] ) && $params['schema_hash'] !== $contract['schema_hash'] ) {
			// A hash from another method or path fails the same way as a changed definition,
			// so name both causes; rediscovering this exact path and method fixes either.
			return $this->error( 'rest_schema_changed', 'Automator stopped the request before REST dispatch: this schema_hash does not match the current definition of this path and method. It came from another method or path, or the inputs changed. Discover this exact path and method and use its schema_hash.', 409 );
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
		$selector = $request->get_param( '_fields' );
		$fields   = is_array( $selector ) || is_string( $selector )
			? array_map( 'trim', is_array( $selector ) ? array_filter( $selector, 'is_string' ) : wp_parse_list( $selector ) )
			: array();
		$error    = $this->validate_response_fields( $request, $operation['handler'], $fields );
		if ( is_wp_error( $error ) ) {
			return $error;
		}
		$undeclared_update = self::undeclared_post_update( $params, $operation );
		if ( $undeclared_update ) {
			$feedback['undeclared_inputs'] = $undeclared_update;
			return $this->error( self::NO_UPDATE_FIELDS, 'No recognized post update fields were supplied. Automator stopped the request before WordPress ran it. Nothing changed.', 400 );
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
		$callback   = $operation['handler']['callback'] ?? null;
		$controller = is_array( $callback ) ? ( $callback[0] ?? null ) : null;
		if ( Rest_Operation_Policy::is_core_controller( $operation['route'], $controller ) ) {
			if ( $response->get_status() >= 200 && $response->get_status() < 300 ) {
				// Fields a plugin registered while WordPress ran the request, such as ACF's acf, were accepted (QA D100).
				$feedback['undeclared_inputs'] = self::undeclared_input_sources( $params, $operation['handler'], self::registered_fields( $controller ) );
				if ( \WP_REST_Posts_Controller::class === get_class( $controller ) ) {
					self::omit_unrequested_seo_head( $response, $fields );
				}
			} elseif ( $dispatch_started && 'DELETE' === $params['method'] && \WP_REST_Posts_Controller::class === get_class( $controller ) && 'delete_item' === $callback[1] ) {
				$feedback['already_trashed'] = self::confirm_trashed_post( $response, (int) ( $operation['path_params']['id'] ?? 0 ) );
			}
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
	 * Core posts can update the modified date even when every update field is a typo.
	 * Plugin handlers and custom post types can legitimately use undeclared inputs.
	 * Only names that read as a typo of a declared field are refused. A plugin such
	 * as ACF registers its field once WordPress starts handling the request, after
	 * this check, so any other unknown name may be real and goes through (QA D100).
	 *
	 * @since 8.1.0
	 *
	 * @param array $params Original parameters sent by the caller.
	 * @param array $operation Resolved native REST operation.
	 * @return array Warnings for an input-only typo, or an empty array.
	 */
	private static function undeclared_post_update( array $params, array $operation ): array {
		$handler  = $operation['handler'];
		$callback = $handler['callback'] ?? null;
		if ( '/wp/v2/posts/(?P<id>[\d]+)' !== $operation['route']
			|| ! in_array( $params['method'], array( 'POST', 'PUT', 'PATCH' ), true )
			|| ! is_array( $callback ) || ! is_object( $callback[0] ?? null )
			|| \WP_REST_Posts_Controller::class !== get_class( $callback[0] )
			|| 'update_item' !== ( $callback[1] ?? null )
			|| 'post' !== ( $callback[0]->get_item_schema()['title'] ?? null ) ) {
			return array();
		}
		$supplied = (array) ( $params['body'] ?? array() ) + (array) ( $params['query'] ?? array() );
		$controls = array_flip( array( 'id', 'context', '_fields', '_locale' ) );
		$declared = array_diff_key( $handler['args'] ?? array(), $controls );
		// WordPress runs registered update callbacks even when their field has no schema.
		foreach ( $GLOBALS['wp_rest_additional_fields']['post'] ?? array() as $name => $field ) {
			if ( ! empty( $field['update_callback'] ) ) {
				$declared[ $name ] = true;
			}
		}
		if ( array_intersect_key( $supplied, $declared ) ) {
			return array();
		}
		$sources = self::undeclared_input_sources( $params, $handler );
		$known   = ( $handler['args'] ?? array() ) + array(
			'_fields' => true,
			'_locale' => true,
		);
		$unknown = 0;
		foreach ( array( 'query', 'body' ) as $source ) {
			$unknown += count( array_diff_key( (array) ( $params[ $source ] ?? array() ), $known ) );
		}
		$typos = 0;
		foreach ( $sources as $parameters ) {
			foreach ( $parameters as $parameter ) {
				$typos += isset( $parameter['suggestion'] ) ? 1 : 0;
			}
		}
		return 0 < $unknown && $typos === $unknown ? $sources : array();
	}

	/**
	 * Report which parts of the request supplied names not declared by core or a plugin.
	 *
	 * @since 8.1.0
	 *
	 * @param array $params Original parameters sent by the caller.
	 * @param array $handler Matched native handler.
	 *
	 * @return array<string,array{name:string,suggestion?:string}[]> Parameters grouped by query or body.
	 */
	private static function undeclared_input_sources( array $params, array $handler, array $registered = array() ): array {
		$declared       = ( $handler['args'] ?? array() ) + $registered + array(
			'_fields' => true,
			'_locale' => true,
		);
		$declared_names = array_keys( $declared );
		$sources        = array();
		foreach ( array( 'query', 'body' ) as $source ) {
			$unknown = array_diff_key( (array) ( $params[ $source ] ?? array() ), $declared );
			if ( ! $unknown ) {
				continue;
			}
			$sources[ $source ] = array();
			foreach ( array_keys( $unknown ) as $name ) {
				if ( ! self::is_reportable_parameter( $name ) ) {
					continue;
				}
				$parameter  = array( 'name' => $name );
				$suggestion = self::suggest_parameter_name( $name, $declared_names );
				if ( null !== $suggestion ) {
					$parameter['suggestion'] = $suggestion;
				}
				$sources[ $source ][] = $parameter;
				if ( 5 === count( $sources[ $source ] ) ) {
					break;
				}
			}
		}
		return $sources;
	}

	/**
	 * REST fields plugins registered for a core controller's object type, keyed by name.
	 *
	 * @since 8.1.0
	 *
	 * @param mixed $controller The matched core controller.
	 * @return array<string,true>
	 */
	private static function registered_fields( $controller ): array {
		if ( ! is_object( $controller ) || ! method_exists( $controller, 'get_item_schema' ) ) {
			return array();
		}
		$type = $controller->get_item_schema()['title'] ?? null;
		if ( ! is_string( $type ) ) {
			return array();
		}
		return array_fill_keys( array_keys( (array) ( $GLOBALS['wp_rest_additional_fields'][ $type ] ?? array() ) ), true );
	}

	/**
	 * Report ordinary parameter names without echoing arbitrary text.
	 *
	 * @since 8.1.0
	 */
	private static function is_reportable_parameter( $name ): bool {
		return is_string( $name ) && 1 === preg_match( '/^[a-zA-Z_][a-zA-Z0-9_-]{0,39}$/D', $name );
	}

	/**
	 * Suggest a name only when there is one clearly closest declared key.
	 *
	 * @since 8.1.0
	 */
	private static function suggest_parameter_name( string $name, array $declared ): ?string {
		$closest = array();
		$best    = 2;
		foreach ( $declared as $candidate ) {
			if ( ! self::is_reportable_parameter( $candidate ) ) {
				continue;
			}
			$distance = levenshtein( $name, $candidate );
			if ( $distance < $best ) {
				$best    = $distance;
				$closest = array();
			}
			if ( $distance === $best ) {
				$closest[] = $candidate;
			}
		}
		return 1 === count( $closest ) ? $closest[0] : null;
	}

	/**
	 * Confirm the core 410 response against the stored status of the requested post.
	 *
	 * @since 8.1.0
	 *
	 * @param WP_REST_Response $response Native REST response.
	 * @param int $post_id Post selected by the matched route.
	 *
	 * @return bool
	 */
	private static function confirm_trashed_post( WP_REST_Response $response, int $post_id ): bool {
		$data = $response->get_data();
		if ( $post_id <= 0 || 410 !== $response->get_status() || ! is_array( $data ) || 'rest_already_trashed' !== ( $data['code'] ?? null ) ) {
			return false;
		}
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post || 'trash' !== $post->post_status ) {
			return false;
		}
		$data['message'] = 'This post is already in the trash.';
		$response->set_data( $data );
		return true;
	}

	/**
	 * Return AIOSEO's head only when the caller requests it.
	 *
	 * @since 8.1.0
	 *
	 * @param WP_REST_Response $response Native REST response after WordPress projection.
	 * @param string[] $fields Caller-supplied field selectors before dispatch.
	 *
	 * @return void
	 */
	private static function omit_unrequested_seo_head( WP_REST_Response $response, array $fields ): void {
		$data = $response->get_data();
		$omit = array();
		foreach ( array( 'aioseo_head', 'aioseo_head_json' ) as $field ) {
			if ( ! rest_is_field_included( $field, $fields ) ) {
				$omit[ $field ] = true;
			}
		}
		if ( ! is_array( $data ) || ! $omit ) {
			return;
		}
		if ( ! wp_is_numeric_array( $data ) ) {
			$response->set_data( array_diff_key( $data, $omit ) );
			return;
		}
		foreach ( $data as &$item ) {
			if ( is_array( $item ) ) {
				$item = array_diff_key( $item, $omit );
			}
		}
		unset( $item );
		$response->set_data( $data );
	}

	/** Explain how to correct the path or use an available method. */
	private function explain_missing_operation( string $path, string $method, User_Context $context, WP_Error $error ): WP_Error {
		$message = $error->get_error_message();
		if ( '/wp-json' === $path || 0 === strpos( $path, '/wp-json/' ) ) {
			return $this->error( 'rest_no_route', $message . ' Remove /wp-json from the start of the path and use a concrete REST path such as /wp/v2/posts.', 404 );
		}

		$policy  = new Rest_Operation_Policy();
		$methods = array();
		// The hosted REST tools cannot send HEAD or OPTIONS.
		foreach ( array( 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ) as $candidate ) {
			if ( $candidate === $method ) {
				continue;
			}
			$operation = $this->resolve( $path, $candidate );
			if ( ! is_wp_error( $operation ) && null === $policy->unavailable_reason( $operation['policy'], $context, 'rest_discover' ) ) {
				$methods[] = $candidate;
			}
		}
		if ( empty( $methods ) ) {
			return $error;
		}
		$guidance = sprintf( 'This path supports %s for this connection. Use rest_request with an available method. Inspect the operation with REST discovery first.', implode( ', ', $methods ) );
		return $this->error( 'rest_method_not_allowed', $guidance, 405 );
	}

	/**
	 * Explain unsupported operations without exposing their arguments.
	 *
	 * @since 8.1.0
	 */
	private static function unexposed_operation_message( string $path, string $method, array $handler ): string {
		if ( 'POST' === $method && '/automator/v1/mcp/media-transfer' === strtolower( $path ) ) {
			return 'Use prepare_upload when available. For a direct WordPress connection, discover POST /wp/v2/media and follow its upload instructions.';
		}
		switch ( Rest_Operation_Policy::unsupported_core_operation( $method, $handler['callback'] ?? null ) ) {
			case Rest_Operation_Policy::ATTACHMENT_DERIVATIVE:
				return 'Uploading a derivative to an existing attachment is unavailable through MCP. For a new attachment, discover POST /wp/v2/media and follow its upload instructions.';
			case Rest_Operation_Policy::REVISION_DELETE:
				return self::REVISION_DELETE_UNAVAILABLE;
		}
		return self::UNEXPOSED_OPERATION;
	}

	/** Tell callers how to select fields that these core controllers cannot project with dot paths. */
	private function validate_response_fields( WP_REST_Request $request, array $handler, array $fields ): ?WP_Error {
		if ( 'GET' !== $request->get_method() || ! $fields ) {
			return null;
		}
		$callback   = $handler['callback'] ?? null;
		$target     = is_array( $callback ) ? ( $callback[0] ?? null ) : null;
		$controller = is_object( $target ) ? get_class( $target ) : null;

		$is_types            = \WP_REST_Post_Types_Controller::class === $controller;
		$is_types_collection = $is_types && '/wp/v2/types' === strtolower( $request->get_route() );
		$is_media            = \WP_REST_Attachments_Controller::class === $controller;
		if ( ! $is_types && ! $is_media ) {
			return null;
		}
		foreach ( $fields as $field ) {
			$parts = explode( '.', $field, 3 );
			if ( count( $parts ) < 2 ) {
				continue;
			}
			$parent = $parts[0];
			if ( $is_types && ! $is_types_collection && in_array( $parent, array( 'labels', 'capabilities' ), true ) && ! in_array( $parent, $fields, true ) ) {
				return $this->error( 'rest_invalid_parameters', sprintf( 'WordPress returns %1$s as a whole. Use context=edit and _fields=name,%1$s, then read the needed properties from %1$s.', $parent ), 400 );
			}
			if ( $is_types_collection ) {
				// The types controller prepares each item using the same list of fields as the collection.
				$post_type = get_post_type_object( $parent );
				if ( $post_type && $post_type->show_in_rest && ! rest_is_field_included( $parts[1], $fields ) ) {
					$message = in_array( $parts[1], array( 'labels', 'capabilities' ), true )
						? sprintf( 'WordPress returns %1$s as a whole. Use context=edit and _fields=%2$s,%1$s, then read the needed properties from %1$s.', $parts[1], $parent )
						: 'WordPress selects post types and their fields separately. For example, use _fields=post,page,name,rest_base to get the names and REST bases for posts and pages.';
					return $this->error( 'rest_invalid_parameters', $message, 400 );
				}
			}
			if ( $is_media && in_array( $parent, array( 'media_details', 'caption', 'description' ), true ) && ! in_array( $parent, $fields, true ) ) {
				$message = sprintf( 'WordPress returns %1$s as a whole. Use _fields=id,%1$s and read the needed properties from %1$s.', $parent );
				return $this->error( 'rest_invalid_parameters', $message, 400 );
			}
		}
		return null;
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
	 * Explain a missing leading slash on an otherwise path-shaped input.
	 *
	 * @since 8.1.0
	 *
	 * @param string $path Path supplied by the caller.
	 * @param array  $routes Registered patterns when hinting for discovery.
	 * @return string Additional guidance, or an empty string.
	 */
	private static function missing_leading_slash_hint( string $path, array $routes = array() ): string {
		if ( '/' === ( $path[0] ?? '' ) ) {
			return '';
		}
		$prefixed = 0 === strpos( $path, 'wp-json/' );
		$example  = '/' . ( $prefixed ? substr( $path, strlen( 'wp-json/' ) ) : $path );
		if ( ! self::valid_path( $example ) && ! isset( $routes[ $example ] )
			&& ( ! $routes || null === self::registered_template_route( $example, array_keys( $routes ) ) ) ) {
			return '';
		}
		if ( 'wp-json' === $path || $prefixed ) {
			return ' Remove the wp-json prefix and start the path with a slash (/), for example /wp/v2/posts.';
		}
		return ' Start the path with a slash (/), for example /wp/v2/posts.';
	}

	/**
	 * Resolve a discovery-only brace template to one registered route pattern.
	 *
	 * @since 8.1.0
	 *
	 * @param string $path Discovery path with placeholders such as {id}.
	 * @param string[] $routes Registered route patterns.
	 * @return string|null The unique pattern with those literal path segments and capture positions.
	 */
	private static function registered_template_route( string $path, array $routes ): ?string {
		$placeholder = '/\{[a-zA-Z_][a-zA-Z0-9_]*\}/';
		if ( strlen( $path ) > 2048 || 1 !== preg_match( '~^(?:/(?:[a-zA-Z0-9_-]+|\{[a-zA-Z_][a-zA-Z0-9_]*\}))+$~D', $path ) || 1 !== preg_match( $placeholder, $path ) ) {
			return null;
		}
		$template = preg_replace( $placeholder, '{}', $path );
		$matched  = null;
		foreach ( $routes as $route ) {
			// Match simple named captures as whole path segments. Never compile a caller's template as a regex.
			$shape = preg_replace( '~(?<=/)\(\?(?:P?<\w+>|\x27\w+\x27)[^()/]+\)(?=/|$)~', '{}', $route );
			if ( $template !== $shape ) {
				continue;
			}
			if ( null !== $matched ) {
				return null;
			}
			$matched = $route;
		}
		return $matched;
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
		$registered_arguments = $handler['args'] ?? array();
		if ( 0 === strpos( strtolower( $route ), '/uncanny-page-builder/v1/agent/' ) ) {
			// The reviewed schema owns which fields belong in query or body and
			// which combinations are valid. List only its unconditional fields here.
			$parts                 = $policy['request_schema']['properties'] ?? array();
			$registered_arguments += ( $parts['query']['properties'] ?? array() ) + ( $parts['body']['properties'] ?? array() );
		}
		try {
			foreach ( $registered_arguments as $name => $schema ) {
				if ( in_array( $name, $policy['upload']['unsupported_parameters'] ?? array(), true ) ) {
					continue;
				}
				$schema = $this->schema_data( (array) $schema );
				if ( array_key_exists( $name, $path_parameters ) ) {
					$path_parameters[ $name ] = $schema;
				} else {
					$arguments[ $name ] = $schema;
				}
			}
			if ( ! isset( $arguments['_fields'] ) && ! isset( $path_parameters['_fields'] ) && $this->trims_to_fields( $route, $method, $handler, $policy ) ) {
				$arguments['_fields'] = self::RESPONSE_FIELDS_ARGUMENT;
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
		if ( isset( $policy['upload'] ) ) {
			// A continuation from an older release must restart before it can
			// combine chunks from the prior encoding and this one.
			$definition[] = 'upload-details-outside-schema-chunks';
		}
		if ( $path_parameters ) {
			$definition[] = $path_parameters;
		}
		if ( $ability instanceof \WP_Ability ) {
			// Ability discovery always returns a URL with the name already bound.
			// Change the hash so callers restart any older partial schema read.
			$definition[] = 'ability-name-in-route';
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
		if ( $path_parameters && ! $ability instanceof \WP_Ability ) {
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
			// Show a restricted argument the way request_schema enforces it, so a caller reading only
			// arguments does not try force=true. Applied after the hash, which stays as before.
			$parts = $policy['request_schema']['properties'] ?? array();
			$force = $parts['query']['properties']['force'] ?? $parts['body']['properties']['force'] ?? null;
			if ( is_array( $force ) && isset( $arguments['force'] ) ) {
				$contract['arguments'] = (object) array_merge( (array) $contract['arguments'], array( 'force' => $force ) );
			}
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
	 * Whether a request to this operation can name _fields and get a trimmed response.
	 *
	 * @since 8.1.0
	 */
	private function trims_to_fields( string $route, string $method, array $handler, array $policy ): bool {
		// HEAD has no body to trim, and a reviewed request schema states the whole query itself.
		if ( 'HEAD' === $method || isset( $policy['request_schema'] ) ) {
			return false;
		}
		// Since WordPress 7.2 a route can serve its data as returned, as an ability run does.
		return ! method_exists( \WP_REST_Server::class, 'resolve_data_processing' ) || \WP_REST_Server::resolve_data_processing( $handler, rest_get_server()->get_route_options( $route ) )['fields'];
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

	/** Return the same authorization refusal regardless of which ability method was requested. */
	private function operation_refusal( array $policy, User_Context $context ): ?WP_Error {
		$reason = ( new Rest_Operation_Policy() )->unavailable_reason( $policy, $context, 'rest_request' );
		if ( null === $reason ) {
			return null;
		}
		return $this->error( 'rest_forbidden', self::explain_unavailability( $reason ), 403 );
	}

	/** Use the same explanation in discovery and when an operation is refused. */
	private static function explain_unavailability( ?string $reason ): ?string {
		if ( Rest_Operation_Policy::HOSTED_UPLOAD_REQUIRED === $reason ) {
			return 'Use prepare_upload when available, then use the returned attachment in the page builder. Direct WordPress clients can follow the upload instructions for POST /wp/v2/media.';
		}
		return 'first_party_only' === $reason ? 'This operation is not available to this connection.' : $reason;
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
