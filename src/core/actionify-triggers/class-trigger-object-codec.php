<?php

namespace Uncanny_Automator\Actionify_Triggers;

/**
 * Carries hook-argument objects through the loopback payload as plain arrays.
 *
 * The loopback payload is decoded with allowed_classes => false (CVE-2026-82627), so
 * objects cannot be serialized into it. Each supported object becomes a marker array
 * instead, and is rebuilt on the receiving request:
 *
 * - Snapshot types (stdClass, WP_User, WP_Post, WP_Term, WP_Comment) keep their public
 *   properties, so hook-time state survives (e.g. post_updated's $post_before, or a
 *   post that is deleted before the loopback runs). This list is fixed.
 * - Reference types keep only their ID and are reloaded from their source of truth by
 *   an adapter, because their state lives in protected properties or a plugin table.
 *   WooCommerce's data types are built in; an integration registers its own through
 *   the `automator_trigger_object_codec_adapters` filter (MemberPress does).
 *
 * A type name in the payload never selects an arbitrary class to instantiate: a
 * snapshot marker is rebuilt only for the fixed list, and a reference marker only by an
 * adapter that code registered in the receiving request. A reloaded object must be an
 * instance of its registered type and carry the same ID, so a missing row is refused
 * rather than delivered empty. An input array that already contains the marker key is
 * escaped, so hook data can never be mistaken for an encoded object.
 *
 * The payload is signed but not encrypted, so secret fields (password hashes,
 * activation keys, post passwords) are never copied into it. They are refilled from
 * the current database row on restore, so unchanged values still compare equal.
 *
 * @package Uncanny_Automator\Actionify_Triggers
 */
class Trigger_Object_Codec {

	/**
	 * Array key that marks an encoded object.
	 *
	 * @var string
	 */
	const MARKER = '__automator_object';

	/**
	 * Types restored from their public properties.
	 *
	 * @var string[]
	 */
	const SNAPSHOT_TYPES = array( 'WP_User', 'WP_Post', 'WP_Term', 'WP_Comment' );

	/**
	 * Filter through which an integration registers reference adapters.
	 *
	 * @var string
	 */
	const ADAPTERS_FILTER = 'automator_trigger_object_codec_adapters';

	/**
	 * Marker type for an input array that contains the marker key.
	 *
	 * @var string
	 */
	const ESCAPED_ARRAY = 'array';

	/**
	 * Secret fields never copied into the payload. WP_User keeps them in its `data` object.
	 *
	 * @var array<string, string[]>
	 */
	const SECRET_FIELDS = array(
		'WP_Post' => array( 'post_password' ),
		'WP_User' => array( 'user_pass', 'user_activation_key' ),
	);

	/**
	 * Replace every object inside a value with its marker array.
	 *
	 * @param mixed $value The value to encode.
	 *
	 * @return mixed The value without objects.
	 *
	 * @throws \InvalidArgumentException When an object cannot be carried safely.
	 */
	public function encode( $value ) {

		if ( ! is_array( $value ) ) {
			return is_object( $value ) ? $this->encode_object( $value ) : $value;
		}

		$encoded = array_map( array( $this, 'encode' ), $value );

		// Hook data shaped like a marker must stay an array (no type confusion on decode).
		if ( array_key_exists( self::MARKER, $encoded ) ) {
			return array(
				self::MARKER => self::ESCAPED_ARRAY,
				'items'      => $encoded,
			);
		}

		return $encoded;
	}

	/**
	 * Rebuild every marker array inside a value into its object.
	 *
	 * @param mixed $value The value to decode.
	 *
	 * @return mixed The value with objects restored.
	 *
	 * @throws \UnexpectedValueException When a marker cannot be restored.
	 */
	public function decode( $value ) {

		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( ! array_key_exists( self::MARKER, $value ) ) {
			return array_map( array( $this, 'decode' ), $value );
		}

		if ( self::ESCAPED_ARRAY === $value[ self::MARKER ] ) {
			return array_map( array( $this, 'decode' ), (array) ( $value['items'] ?? array() ) );
		}

		$instance = $this->decode_object( $value );

		if ( ! is_object( $instance ) ) {
			throw new \UnexpectedValueException( 'Hook argument object could not be restored: ' . esc_html( (string) $value[ self::MARKER ] ) );
		}

		return $instance;
	}

	/**
	 * @param object $instance The object to encode.
	 *
	 * @return array The marker array.
	 *
	 * @throws \InvalidArgumentException When the object type is unsupported or unsaved.
	 */
	private function encode_object( $instance ) {

		$adapters = $this->adapters();
		$type     = $this->type_of( $instance, $adapters );

		if ( null === $type ) {
			throw new \InvalidArgumentException( 'Unsupported hook argument object: ' . esc_html( get_class( $instance ) ) );
		}

		if ( isset( $adapters[ $type ] ) ) {
			return $this->encode_reference( $type, $instance, $adapters[ $type ] );
		}

		return array(
			self::MARKER => $type,
			'props'      => $this->encode( $this->snapshot_props( $type, $instance ) ),
			'site_id'    => 'WP_User' === $type ? $instance->get_site_id() : 0,
		);
	}

	/**
	 * Public properties of a snapshot type, without its secret fields.
	 *
	 * @param string $type     The snapshot type.
	 * @param object $instance The object to snapshot.
	 *
	 * @return array
	 */
	private function snapshot_props( $type, $instance ) {

		$props   = get_object_vars( $instance );
		$secrets = array_flip( self::SECRET_FIELDS[ $type ] ?? array() );

		if ( 'WP_User' === $type ) {
			$props['data'] = (object) array_diff_key( (array) $props['data'], $secrets );
			return $props;
		}

		return array_diff_key( $props, $secrets );
	}

	/**
	 * @param string $type     The matched reference type.
	 * @param object $instance The object to carry by ID.
	 * @param array  $adapter  The type's adapter.
	 *
	 * @return array The marker array.
	 *
	 * @throws \InvalidArgumentException When the object has not been saved.
	 */
	private function encode_reference( $type, $instance, array $adapter ) {

		$id = (int) call_user_func( $adapter['encode'], $instance );

		if ( $id <= 0 ) {
			throw new \InvalidArgumentException( 'Unsaved hook argument object cannot be reloaded: ' . esc_html( get_class( $instance ) ) );
		}

		return array(
			self::MARKER => $type,
			'id'         => $id,
		);
	}

	/**
	 * @param object $instance The object to classify.
	 * @param array  $adapters The reference adapters keyed by type.
	 *
	 * @return string|null The supported type, or null. Snapshot types win, then adapters in order.
	 */
	private function type_of( $instance, array $adapters ) {

		if ( 'stdClass' === get_class( $instance ) ) {
			return 'stdClass';
		}

		foreach ( array_merge( self::SNAPSHOT_TYPES, array_keys( $adapters ) ) as $type ) {
			if ( $instance instanceof $type ) {
				return $type;
			}
		}

		return null;
	}

	/**
	 * @param array $marker The marker array.
	 *
	 * @return object|false|null The restored object, or a non-object on failure.
	 */
	private function decode_object( array $marker ) {

		$type     = (string) $marker[ self::MARKER ];
		$adapters = $this->adapters();

		if ( isset( $adapters[ $type ] ) ) {
			return $this->load_reference( $type, (int) ( $marker['id'] ?? 0 ), $adapters[ $type ] );
		}

		$props = $this->decode( (array) ( $marker['props'] ?? array() ) );

		return $this->restore_snapshot( $type, (array) $props, (int) ( $marker['site_id'] ?? 0 ) );
	}

	/**
	 * @param string $type    The snapshot type.
	 * @param array  $props   The decoded public properties.
	 * @param int    $site_id The user's site ID (WP_User only).
	 *
	 * @return object|null The restored object, or null for an unknown type.
	 */
	private function restore_snapshot( $type, array $props, $site_id ) {

		switch ( $type ) {
			case 'stdClass':
				return (object) $props;
			case 'WP_Post':
				$post = new \WP_Post( (object) $props );
				$this->refill_secrets( 'WP_Post', $post->ID ? get_post( $post->ID ) : null, $post );
				return $post;
			case 'WP_Term':
				return new \WP_Term( (object) $props );
			case 'WP_Comment':
				return new \WP_Comment( (object) $props );
			case 'WP_User':
				return $this->restore_user( $props, $site_id );
		}

		return null;
	}

	/**
	 * Rebuild a WP_User, then reapply the hook-time capabilities and roles.
	 *
	 * @param array $props   The decoded public properties.
	 * @param int   $site_id The user's site ID.
	 *
	 * @return \WP_User
	 */
	private function restore_user( array $props, $site_id ) {

		$user    = new \WP_User( (object) ( $props['data'] ?? array() ), '', $site_id );
		$current = get_userdata( $user->ID );

		$this->refill_secrets( 'WP_User', $current ? $current->data : null, $user->data );

		foreach ( array( 'caps', 'cap_key', 'roles', 'allcaps', 'filter' ) as $key ) {
			if ( array_key_exists( $key, $props ) ) {
				$user->$key = $props[ $key ];
			}
		}

		return $user;
	}

	/**
	 * Copy secret fields from the current database row onto a restored object.
	 *
	 * @param string      $type    The snapshot type.
	 * @param object|null $current The current row, or null when it no longer exists.
	 * @param object      $target  The restored object (or WP_User data) to fill.
	 *
	 * @return void
	 */
	private function refill_secrets( $type, $current, $target ) {

		if ( ! is_object( $current ) ) {
			return;
		}

		foreach ( self::SECRET_FIELDS[ $type ] as $field ) {
			if ( isset( $current->$field ) ) {
				$target->$field = $current->$field;
			}
		}
	}

	/**
	 * Reload a reference type through its adapter.
	 *
	 * @param string $type    The reference type.
	 * @param int    $id      The object ID.
	 * @param array  $adapter The type's adapter.
	 *
	 * @return object|null The object, or null when it no longer exists.
	 */
	private function load_reference( $type, $id, array $adapter ) {

		if ( $id <= 0 ) {
			return null;
		}

		$instance = call_user_func( $adapter['decode'], $id );

		// Some loaders return an empty object for a missing ID (WC_Coupon, every
		// MemberPress model), so the reloaded object must prove it is the one asked for.
		if ( ! $instance instanceof $type || (int) call_user_func( $adapter['encode'], $instance ) !== $id ) {
			return null;
		}

		return $instance;
	}

	/**
	 * Reference adapters keyed by class or interface name. Each is an array with an
	 * `encode` callable (object in, positive ID out) and a `decode` callable (ID in,
	 * object out, or null when it no longer exists).
	 *
	 * The WooCommerce adapters are built in and cannot be replaced. The rest come from
	 * the filter, applied on every call because the queue is constructed before
	 * integrations register. Order matters: the first `instanceof` match wins.
	 *
	 * @return array<string, array{encode: callable, decode: callable}>
	 */
	private function adapters() {

		/**
		 * Registers reference adapters for hook-argument objects the loopback payload
		 * cannot carry as snapshots. Register from code that runs on every request where
		 * the hook can fire (an integration's helpers), never from the trigger itself:
		 * the encoding request may exit before recipe parts load. Keys are matched with
		 * `instanceof` in registration order, after the built-ins, so register the
		 * concrete class, not a parent that other integrations' objects also extend.
		 *
		 * @since 7.7.1
		 *
		 * @param array<string, array{encode: callable, decode: callable}> $adapters Adapters keyed by class name.
		 */
		$registered = apply_filters( self::ADAPTERS_FILTER, array() );

		return $this->woocommerce_adapters() + $this->valid_adapters( is_array( $registered ) ? $registered : array() );
	}

	/**
	 * Keep only well-formed registrations. The snapshot types stay fixed.
	 *
	 * @param array $adapters Registered adapters.
	 *
	 * @return array<string, array{encode: callable, decode: callable}>
	 */
	private function valid_adapters( array $adapters ) {

		$valid = array();

		foreach ( $adapters as $type => $adapter ) {
			if ( ! is_string( $type ) || 'stdClass' === $type || in_array( $type, self::SNAPSHOT_TYPES, true ) ) {
				continue;
			}
			if ( ! is_array( $adapter ) || ! is_callable( $adapter['encode'] ?? null ) || ! is_callable( $adapter['decode'] ?? null ) ) {
				continue;
			}
			$valid[ $type ] = $adapter;
		}

		return $valid;
	}

	/**
	 * WooCommerce data types, reloaded through WooCommerce's own factories.
	 *
	 * @return array<string, array{encode: callable, decode: callable}> Empty when WooCommerce is not loaded.
	 */
	private function woocommerce_adapters() {

		if ( ! function_exists( 'wc_get_order' ) ) {
			return array();
		}

		$by_id = function ( $instance ) {
			return (int) $instance->get_id();
		};

		return array(
			'WC_Abstract_Order' => array(
				'encode' => $by_id,
				'decode' => 'wc_get_order',
			),
			'WC_Product'        => array(
				'encode' => $by_id,
				'decode' => 'wc_get_product',
			),
			'WC_Order_Item'     => array(
				'encode' => $by_id,
				'decode' => array( 'WC_Order_Factory', 'get_order_item' ),
			),
			'WC_Customer'       => array(
				'encode' => $by_id,
				'decode' => function ( $id ) {
					return new \WC_Customer( $id );
				},
			),
			'WC_Coupon'         => array(
				'encode' => $by_id,
				'decode' => function ( $id ) {
					return new \WC_Coupon( $id );
				},
			),
		);
	}
}
