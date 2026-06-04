<?php

namespace Uncanny_Automator\Integrations\Wp_Webhooks;

/**
 * Class Wpwh_Tokens
 *
 * Modern tokens framework class for the WP Webhooks trigger.
 *
 * Token IDs follow the legacy parser shape — they are stored in recipes
 * and must not change. Composite keys use the `prefix|field` form
 * (e.g. `data|user_login`, `post|ID`) and resolve through
 * {@see hydrate_dynamic_payload_tokens()}.
 *
 * @package Uncanny_Automator\Integrations\Wp_Webhooks
 */
class Wpwh_Tokens {

	/**
	 * Integration helpers reference.
	 *
	 * @var Wpwh_Helpers
	 */
	private $helpers;

	/**
	 * Constructor.
	 *
	 * @param Wpwh_Helpers $helpers Integration helpers instance.
	 */
	public function __construct( Wpwh_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * Tokens emitted for the `create_user`, `login_user`, `update_user` payloads.
	 *
	 * @return string[]
	 */
	public static function user_payload_keys() {
		return array(
			'ID',
			'user_login',
			'user_pass',
			'user_nicename',
			'user_email',
			'user_url',
			'user_registered',
			'user_activation_key',
			'user_status',
			'display_name',
		);
	}

	/**
	 * Tokens emitted for the `deleted_user` payload (top-level keys, no prefix).
	 *
	 * @return string[]
	 */
	public static function deleted_user_payload_keys() {
		return array(
			'user_id',
			'reassign',
		);
	}

	/**
	 * Tokens emitted for the `post_create`, `post_update`, `post_delete`, `post_trash` payloads.
	 *
	 * @return string[]
	 */
	public static function post_payload_keys() {
		return array(
			'ID',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_content',
			'post_title',
			'post_excerpt',
			'post_status',
			'comment_status',
			'ping_status',
			'post_password',
			'post_name',
			'to_ping',
			'pinged',
			'post_modified',
			'post_modified_gmt',
			'post_content_filtered',
			'post_parent',
			'guid',
			'menu_order',
			'post_type',
			'post_mime_type',
			'comment_count',
			'filter',
		);
	}

	/**
	 * Curated "main" tokens shared by the comment triggers
	 * (`create_comment`, `update_comment`, `delete_comment`, `trash_comment`).
	 *
	 * Map of flat tokenId => payload path. Flat ids keep the recipe UI clean
	 * while {@see hydrate_main_tokens()} descends the nested webhook payload.
	 * These are new tokens, so a lean, high-value subset is exposed rather than
	 * every field in the payload.
	 *
	 * @return array<string, string[]>
	 */
	public static function comment_main_tokens() {
		return array(
			'comment_id'           => array( 'comment_id' ),
			'comment_content'      => array( 'comment_data', 'comment_content' ),
			'comment_author'       => array( 'comment_data', 'comment_author' ),
			'comment_author_email' => array( 'comment_data', 'comment_author_email' ),
			'post_id'              => array( 'current_post_id' ),
			'user_id'              => array( 'user_id' ),
		);
	}

	/**
	 * Curated "main" tokens shared by the WooCommerce order triggers
	 * (`wc_order_created`, `wc_order_updated`, `wc_order_deleted`, `wc_order_restored`).
	 *
	 * @return array<string, string[]>
	 */
	public static function wc_order_main_tokens() {
		return array(
			'order_id'           => array( 'id' ),
			'order_status'       => array( 'status' ),
			'order_total'        => array( 'total' ),
			'order_currency'     => array( 'currency' ),
			'customer_id'        => array( 'customer_id' ),
			'billing_email'      => array( 'billing', 'email' ),
			'billing_first_name' => array( 'billing', 'first_name' ),
			'billing_last_name'  => array( 'billing', 'last_name' ),
		);
	}

	/**
	 * Curated "main" tokens shared by the WooCommerce customer triggers
	 * (`wc_customer_created`, `wc_customer_updated`, `wc_customer_deleted`).
	 *
	 * @return array<string, string[]>
	 */
	public static function wc_customer_main_tokens() {
		return array(
			'customer_id' => array( 'id' ),
			'email'       => array( 'email' ),
			'first_name'  => array( 'first_name' ),
			'last_name'   => array( 'last_name' ),
			'username'    => array( 'username' ),
		);
	}

	/**
	 * Define the dynamic token set for a given selected webhook trigger value.
	 *
	 * Returns trigger-format token definitions whose tokenId matches what
	 * `hydrate_dynamic_payload_tokens()` writes back, so the modern
	 * resolution path replaces the legacy `automator_maybe_parse_token`
	 * filter chain entirely.
	 *
	 * @param string $webhook_value   The webhook_name selected on the recipe (e.g. 'create_user', 'post_create').
	 * @param string $token_identifier The trigger_meta value to attach as tokenIdentifier.
	 *
	 * @return array[]
	 */
	public function webhook_trigger_tokens( $webhook_value, $token_identifier = 'WPWHTRIGGER' ) {

		$tokens = array();

		if ( empty( $webhook_value ) ) {
			return $tokens;
		}

		switch ( $webhook_value ) {
			case 'create_user':
			case 'login_user':
			case 'update_user':
				foreach ( self::user_payload_keys() as $key ) {
					$tokens[] = array(
						'tokenId'         => 'data|' . $key,
						'tokenName'       => $key,
						'tokenType'       => 'text',
						'tokenIdentifier' => $token_identifier,
					);
				}
				break;

			case 'deleted_user':
				foreach ( self::deleted_user_payload_keys() as $key ) {
					$tokens[] = array(
						'tokenId'         => $key,
						'tokenName'       => $key,
						'tokenType'       => 'text',
						'tokenIdentifier' => $token_identifier,
					);
				}
				break;

			case 'post_create':
			case 'post_update':
			case 'post_delete':
			case 'post_trash':
				foreach ( self::post_payload_keys() as $key ) {
					$tokens[] = array(
						'tokenId'         => 'post|' . $key,
						'tokenName'       => $key,
						'tokenType'       => 'text',
						'tokenIdentifier' => $token_identifier,
					);
				}
				break;

			case 'create_comment':
			case 'update_comment':
			case 'delete_comment':
			case 'trash_comment':
				$tokens = array_merge( $tokens, $this->build_main_tokens( self::comment_main_tokens(), $token_identifier ) );
				break;

			case 'wc_order_created':
			case 'wc_order_updated':
			case 'wc_order_deleted':
			case 'wc_order_restored':
				$tokens = array_merge( $tokens, $this->build_main_tokens( self::wc_order_main_tokens(), $token_identifier ) );
				break;

			case 'wc_customer_created':
			case 'wc_customer_updated':
			case 'wc_customer_deleted':
				$tokens = array_merge( $tokens, $this->build_main_tokens( self::wc_customer_main_tokens(), $token_identifier ) );
				break;
		}

		return $tokens;
	}

	/**
	 * Hydrate the dynamic payload tokens for the trigger.
	 *
	 * Mirrors the legacy parser: composite keys like `data|user_login` and
	 * `post|ID` index into nested payload data; flat keys read directly.
	 *
	 * The returned map always covers the FULL keyset for the selected
	 * webhook_value — missing values are empty strings rather than absent
	 * keys, so unresolved tokens never render as raw `{{ }}` placeholders.
	 *
	 * @param string $webhook_value The selected webhook_name on the recipe.
	 * @param mixed  $payload       Decoded webhook payload (array on success).
	 *
	 * @return array<string, string>
	 */
	public function hydrate_dynamic_payload_tokens( $webhook_value, $payload ) {

		$payload = is_array( $payload ) ? $payload : array();
		$out     = array();

		switch ( $webhook_value ) {
			case 'create_user':
			case 'login_user':
			case 'update_user':
				foreach ( self::user_payload_keys() as $key ) {
					$value                 = isset( $payload['data'][ $key ] ) ? $payload['data'][ $key ] : '';
					$out[ 'data|' . $key ] = self::stringify( $value );
				}
				break;

			case 'deleted_user':
				foreach ( self::deleted_user_payload_keys() as $key ) {
					$value       = isset( $payload[ $key ] ) ? $payload[ $key ] : '';
					$out[ $key ] = self::stringify( $value );
				}
				break;

			case 'post_create':
			case 'post_update':
			case 'post_delete':
			case 'post_trash':
				foreach ( self::post_payload_keys() as $key ) {
					$value                 = isset( $payload['post'][ $key ] ) ? $payload['post'][ $key ] : '';
					$out[ 'post|' . $key ] = self::stringify( $value );
				}
				break;

			case 'create_comment':
			case 'update_comment':
			case 'delete_comment':
			case 'trash_comment':
				$out = array_merge( $out, $this->hydrate_main_tokens( self::comment_main_tokens(), $payload ) );
				break;

			case 'wc_order_created':
			case 'wc_order_updated':
			case 'wc_order_deleted':
			case 'wc_order_restored':
				$out = array_merge( $out, $this->hydrate_main_tokens( self::wc_order_main_tokens(), $payload ) );
				break;

			case 'wc_customer_created':
			case 'wc_customer_updated':
			case 'wc_customer_deleted':
				$out = array_merge( $out, $this->hydrate_main_tokens( self::wc_customer_main_tokens(), $payload ) );
				break;
		}

		return $out;
	}

	/**
	 * Build trigger-format token definitions from a curated tokenId => path map.
	 *
	 * Only the map keys (the flat tokenIds) matter here; the paths are consumed
	 * by {@see hydrate_main_tokens()} at run time.
	 *
	 * @param array<string, string[]> $map              tokenId => payload path map.
	 * @param string                  $token_identifier The trigger_meta value to attach.
	 *
	 * @return array[]
	 */
	private function build_main_tokens( array $map, $token_identifier ) {

		$tokens = array();

		foreach ( array_keys( $map ) as $token_id ) {
			$tokens[] = array(
				'tokenId'         => $token_id,
				'tokenName'       => self::humanize_label( $token_id ),
				'tokenType'       => 'text',
				'tokenIdentifier' => $token_identifier,
			);
		}

		return $tokens;
	}

	/**
	 * Turn a flat snake_case tokenId into a Sentence-case display label.
	 *
	 * e.g. comment_id -> "Comment ID", billing_first_name -> "Billing first name".
	 * Only the first word is capitalized; known acronyms are upper-cased.
	 *
	 * @param string $token_id The flat tokenId.
	 *
	 * @return string
	 */
	private static function humanize_label( $token_id ) {

		$label = ucfirst( str_replace( '_', ' ', $token_id ) );

		return (string) preg_replace_callback(
			'/\b(id|url)\b/i',
			static function ( $m ) {
				return strtoupper( $m[1] );
			},
			$label
		);
	}

	/**
	 * Hydrate a curated tokenId => path map against the decoded payload.
	 *
	 * Each path is a list of array keys descended in order; a missing segment
	 * yields an empty string so the token never renders as a raw `{{ }}`.
	 *
	 * @param array<string, string[]> $map     tokenId => payload path map.
	 * @param array                   $payload Decoded webhook payload.
	 *
	 * @return array<string, string>
	 */
	private function hydrate_main_tokens( array $map, array $payload ) {

		$out = array();

		foreach ( $map as $token_id => $path ) {

			$value = $payload;

			foreach ( $path as $segment ) {
				if ( is_array( $value ) && isset( $value[ $segment ] ) ) {
					$value = $value[ $segment ];
					continue;
				}
				$value = '';
				break;
			}

			$out[ $token_id ] = self::stringify( $value );
		}

		return $out;
	}

	/**
	 * Cast a scalar / arrayish value to string for token rendering.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private static function stringify( $value ) {

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		return '';
	}
}
