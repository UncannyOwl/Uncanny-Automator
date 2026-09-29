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
 *   post that is deleted before the loopback runs).
 * - WooCommerce data types keep only their ID and are reloaded through WooCommerce's
 *   own factories, because their state lives in protected properties.
 *
 * Only this fixed list is rebuilt. A type name in the payload never selects an
 * arbitrary class to instantiate. An input array that already contains the marker key
 * is escaped, so hook data can never be mistaken for an encoded object.
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
	 * WooCommerce types restored by ID. Order matters: the first match wins.
	 *
	 * @var string[]
	 */
	const REFERENCE_TYPES = array( 'WC_Abstract_Order', 'WC_Product', 'WC_Order_Item', 'WC_Customer', 'WC_Coupon' );

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

		$type = $this->type_of( $instance );

		if ( in_array( $type, self::REFERENCE_TYPES, true ) ) {
			return $this->encode_reference( $type, $instance );
		}

		if ( null === $type ) {
			throw new \InvalidArgumentException( 'Unsupported hook argument object: ' . esc_html( get_class( $instance ) ) );
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
	 * @param object $instance The WooCommerce data object.
	 *
	 * @return array The marker array.
	 *
	 * @throws \InvalidArgumentException When the object has not been saved.
	 */
	private function encode_reference( $type, $instance ) {

		$id = (int) $instance->get_id();

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
	 *
	 * @return string|null The supported type, or null.
	 */
	private function type_of( $instance ) {

		if ( 'stdClass' === get_class( $instance ) ) {
			return 'stdClass';
		}

		foreach ( array_merge( self::SNAPSHOT_TYPES, self::REFERENCE_TYPES ) as $type ) {
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

		$type = (string) $marker[ self::MARKER ];

		if ( in_array( $type, self::REFERENCE_TYPES, true ) ) {
			return $this->load_reference( $type, (int) ( $marker['id'] ?? 0 ) );
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
	 * Reload a WooCommerce data object through WooCommerce's own factories.
	 *
	 * @param string $type The reference type.
	 * @param int    $id   The object ID.
	 *
	 * @return object|null The object, or null when it no longer exists.
	 */
	private function load_reference( $type, $id ) {

		if ( $id <= 0 || ! function_exists( 'wc_get_order' ) ) {
			return null;
		}

		$instance = $this->load_woocommerce_object( $type, $id );

		// Some constructors (e.g. WC_Coupon) return an empty object for a missing ID.
		return is_object( $instance ) && (int) $instance->get_id() === $id ? $instance : null;
	}

	/**
	 * @param string $type The reference type.
	 * @param int    $id   The object ID.
	 *
	 * @return object|false|null
	 */
	private function load_woocommerce_object( $type, $id ) {

		switch ( $type ) {
			case 'WC_Abstract_Order':
				return wc_get_order( $id );
			case 'WC_Product':
				return wc_get_product( $id );
			case 'WC_Order_Item':
				return \WC_Order_Factory::get_order_item( $id );
			case 'WC_Customer':
				return new \WC_Customer( $id );
			case 'WC_Coupon':
				return new \WC_Coupon( $id );
		}

		return null;
	}
}
