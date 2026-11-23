<?php

namespace Uncanny_Automator\Integrations\Sportspress;

/**
 * Class Sportspress_Selections
 *
 * Reads the IDs an action's select fields hold and checks them before
 * anything is written: a value that isn't an ID of the right post type or
 * taxonomy is an error, never silently dropped (R15).
 *
 * @package Uncanny_Automator\Integrations\Sportspress
 */
class Sportspress_Selections {

	/**
	 * @var Sportspress_Helpers
	 */
	private $helpers;

	/**
	 * @param Sportspress_Helpers $helpers
	 */
	public function __construct( $helpers ) {
		$this->helpers = $helpers;
	}

	/**
	 * The IDs a multi-select holds. With "Use a token/custom value" the builder
	 * saves the automator_custom_value sentinel and puts the value in
	 * {code}_custom, which the action parser unwraps only for a single value,
	 * so it is read here. SportsPress's empty slot (-1, 0) is skipped; any
	 * other value that isn't a whole number comes back as invalid.
	 *
	 * @param array  $parsed
	 * @param string $option_code
	 *
	 * @return array{ids: int[], invalid: string[], custom: bool}
	 */
	public function selected_ids( $parsed, $option_code ) {

		$items  = $this->list_items( $parsed[ $option_code ] ?? '' );
		$custom = in_array( 'automator_custom_value', $items, true );

		if ( $custom ) {
			$items = array_merge(
				array_diff( $items, array( 'automator_custom_value' ) ),
				$this->list_items( $parsed[ $option_code . '_custom' ] ?? '' )
			);
		}

		$ids     = array();
		$invalid = array();

		foreach ( $items as $item ) {
			if ( in_array( $item, array( '', '0', '-1' ), true ) ) {
				continue;
			}
			if ( ctype_digit( $item ) ) {
				$ids[] = (int) $item;
			} else {
				$invalid[] = $item;
			}
		}

		return array(
			'ids'     => array_values( array_unique( $ids ) ),
			'invalid' => $invalid,
			'custom'  => $custom,
		);
	}

	/**
	 * The posts of one type a multi-select holds, or a translated error when a
	 * value isn't the ID of such a post (R15).
	 *
	 * @param array  $parsed
	 * @param string $option_code
	 * @param string $post_type
	 * @param string $label The field's label, for the error.
	 *
	 * @return array{ids: int[], error: string}
	 */
	public function selected_posts( $parsed, $option_code, $post_type, $label ) {

		$selection = $this->selected_ids( $parsed, $option_code );
		$error     = $this->selection_error( $selection, $label );

		foreach ( '' === $error ? $selection['ids'] : array() as $id ) {
			if ( null === $this->helpers->get_entity( $id, $post_type ) ) {
				/* translators: 1: The field label, 2: The ID */
				$error = sprintf( esc_html_x( '%1$s: %2$d was not found.', 'SportsPress', 'uncanny-automator' ), $label, $id );
				break;
			}
		}

		return array(
			'ids'   => '' === $error ? $selection['ids'] : array(),
			'error' => $error,
		);
	}

	/**
	 * The terms of one taxonomy a select holds, or a translated error when a
	 * value isn't the ID of such a term (R15).
	 *
	 * @param array  $parsed
	 * @param string $option_code
	 * @param string $taxonomy
	 * @param string $label The field's label, for the error.
	 *
	 * @return array{ids: int[], error: string}
	 */
	public function selected_terms( $parsed, $option_code, $taxonomy, $label ) {

		$selection = $this->selected_ids( $parsed, $option_code );
		$error     = $this->selection_error( $selection, $label );

		foreach ( '' === $error ? $selection['ids'] : array() as $id ) {
			if ( ! term_exists( $id, $taxonomy ) ) {
				/* translators: 1: The field label, 2: The ID */
				$error = sprintf( esc_html_x( '%1$s: %2$d was not found.', 'SportsPress', 'uncanny-automator' ), $label, $id );
				break;
			}
		}

		return array(
			'ids'   => '' === $error ? $selection['ids'] : array(),
			'error' => $error,
		);
	}

	/**
	 * @param array  $selection selected_ids() result.
	 * @param string $label
	 *
	 * @return string
	 */
	private function selection_error( $selection, $label ) {

		if ( ! empty( $selection['invalid'] ) ) {
			/* translators: 1: The field label, 2: The values */
			return sprintf( esc_html_x( '%1$s: "%2$s" is not an ID.', 'SportsPress', 'uncanny-automator' ), $label, implode( '", "', $selection['invalid'] ) );
		}

		if ( $selection['custom'] && empty( $selection['ids'] ) ) {
			/* translators: %s: The field label */
			return sprintf( esc_html_x( '%s: the token or custom value gave no ID.', 'SportsPress', 'uncanny-automator' ), $label );
		}

		return '';
	}

	/**
	 * The trimmed items of an array, a JSON array, a JSON string or a comma
	 * list. The builder saves a re-saved custom-only multi-select as the JSON
	 * string "automator_custom_value".
	 *
	 * @param mixed $value
	 *
	 * @return string[]
	 */
	private function list_items( $value ) {

		if ( ! is_array( $value ) ) {
			$decoded = json_decode( (string) $value, true );
			if ( is_array( $decoded ) ) {
				$value = $decoded;
			} else {
				$value = explode( ',', is_string( $decoded ) ? $decoded : (string) $value );
			}
		}

		$items = array();

		foreach ( $value as $item ) {
			$items[] = is_scalar( $item ) ? trim( (string) $item ) : (string) wp_json_encode( $item );
		}

		return $items;
	}
}
