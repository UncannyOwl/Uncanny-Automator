<?php

namespace Uncanny_Automator;

/**
 * Uncanny Toolkit - Tokens class
 */
class Ut_Tokens {

	/**
	 * Global toolkit import module tokens
	 *
	 * @var array
	 */
	public static $user_import_tokens = array();

	/**
	 * Uc_Tokens constructor.
	 */
	public function __construct() {

		self::$user_import_tokens = array(
			'user_id'      => __( 'Imported user ID', 'uncanny-automator' ),
			'user_login'   => __( 'Imported user login', 'uncanny-automator' ),
			'user_email'   => __( 'Imported user email', 'uncanny-automator' ),
			'first_name'   => __( 'Imported user first name', 'uncanny-automator' ),
			'last_name'    => __( 'Imported user last name', 'uncanny-automator' ),
			'display_name' => __( 'Imported user display name', 'uncanny-automator' ),
			'wp_role'      => __( 'Imported user WordPress role', 'uncanny-automator' ),
		);

		add_filter(
			'automator_maybe_parse_token',
			array(
				$this,
				'parse_uncanny_toolkit_token',
			),
			20,
			6
		);

		add_filter(
			'automator_maybe_trigger_uncannytoolkit_utuserimported_tokens',
			array(
				$this,
				'ut_utuserimported_possible_tokens',
			),
			20,
			2
		);
		add_filter(
			'automator_maybe_trigger_uncannytoolkit_utuserimportedcourse_tokens',
			array(
				$this,
				'ut_utuserimportedincourse_possible_tokens',
			),
			20,
			2
		);
		add_filter(
			'automator_maybe_trigger_uncannytoolkit_utuserimportedgroup_tokens',
			array(
				$this,
				'ut_utuserimportedingroup_possible_tokens',
			),
			20,
			2
		);
		add_filter(
			'automator_maybe_trigger_uncannytoolkit_utgroupleaderimported_tokens',
			array(
				$this,
				'ut_utuserimportedingroup_possible_tokens',
			),
			20,
			2
		);

	}

	/**
	 * Add tokens to the triggers
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function ut_utuserimported_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_meta = $args['meta'];
		$new_tokens   = array();
		foreach ( self::$user_import_tokens as $token_id => $token_name ) {
			$type = 'text';
			if ( 'user_email' === (string) $token_id ) {
				$type = 'email';
			}
			if ( 'user_id' === (string) $token_id ) {
				$type = 'int';
			}
			$new_tokens[] = array(
				'tokenId'         => $token_id,
				'tokenName'       => $token_name,
				'tokenType'       => $type,
				'tokenIdentifier' => $trigger_meta,
			);
		}
		$ld_tokens = array(
			'learndash_course_ids'    => __( 'Course ID(s)', 'uncanny-automator' ),
			'learndash_course_titles' => __( 'Course title(s)', 'uncanny-automator' ),
			'learndash_group_ids'     => __( 'Group ID(s)', 'uncanny-automator' ),
			'learndash_group_titles'  => __( 'Group title(s)', 'uncanny-automator' ),
		);
		if ( 'UTUSERIMPORTED' === $trigger_meta && defined( 'LEARNDASH_VERSION' ) ) {
			foreach ( $ld_tokens as $token_id => $token_name ) {
				$new_tokens[] = array(
					'tokenId'         => $token_id,
					'tokenName'       => $token_name,
					'tokenType'       => $type,
					'tokenIdentifier' => $trigger_meta,
				);
			}
		}

		return array_merge( $new_tokens, $tokens );
	}

	/**
	 * Add tokens to the triggers
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function ut_utuserimportedincourse_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_meta = $args['meta'];
		$new_tokens   = array();
		foreach ( self::$user_import_tokens as $token_id => $token_name ) {
			$new_tokens[] = array(
				'tokenId'         => $token_id,
				'tokenName'       => $token_name,
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);
		}
		$new_tokens[] = array(
			'tokenId'         => 'learndash_course_id',
			'tokenName'       => __( 'Course ID', 'uncanny-automator' ),
			'tokenType'       => 'int',
			'tokenIdentifier' => $trigger_meta,
		);

		return array_merge( $new_tokens, $tokens );
	}

	/**
	 * Add tokens to the triggers
	 *
	 * @param array $tokens
	 * @param array $args
	 *
	 * @return array
	 */
	public function ut_utuserimportedingroup_possible_tokens( $tokens = array(), $args = array() ) {
		if ( ! automator_do_identify_tokens() ) {
			return $tokens;
		}
		$trigger_meta = $args['meta'];
		$new_tokens   = array();
		foreach ( self::$user_import_tokens as $token_id => $token_name ) {
			$new_tokens[] = array(
				'tokenId'         => $token_id,
				'tokenName'       => $token_name,
				'tokenType'       => 'text',
				'tokenIdentifier' => $trigger_meta,
			);
		}
		$new_tokens[] = array(
			'tokenId'         => 'learndash_group_id',
			'tokenName'       => __( 'Group ID', 'uncanny-automator' ),
			'tokenType'       => 'int',
			'tokenIdentifier' => $trigger_meta,
		);

		return array_merge( $new_tokens, $tokens );
	}

	/**
	 * Parse tokens of the triggers
	 *
	 * @param $value
	 * @param $pieces
	 * @param $recipe_id
	 * @param $trigger_data
	 * @param $user_id
	 * @param $replace_args
	 *
	 * @return mixed|string
	 */
	public function parse_uncanny_toolkit_token( $value, $pieces, $recipe_id, $trigger_data, $user_id, $replace_args ) {
		$trigger_meta_match = array(
			'UTUSERIMPORTED',
			'UTUSERIMPORTEDCOURSE',
			'UTUSERIMPORTEDGROUP',
			'UTGROUPLEADERIMPORTED',
			'UTUSERSTIMEINCOURSEEXCEEDS',
			'UOUSERSTIMEINCOURSEEXCEEDS',
		);

		if ( empty( $pieces ) ) {
			return $value;
		}
		if ( ! isset( $pieces[2] ) ) {
			return $value;
		}
		if ( empty( $trigger_data ) ) {
			return $value;
		}
		if ( ! array_intersect( $trigger_meta_match, $pieces ) ) {
			return $value;
		}

		$trigger_id = absint( $pieces[0] );
		$token      = $pieces[2];
		$token_args = array(
			'trigger_id'     => $trigger_id,
			'trigger_log_id' => $replace_args['trigger_log_id'],
			'user_id'        => $user_id,
		);
		$token_meta = maybe_unserialize( Automator()->db->trigger->get_token_meta( 'imported_row', $token_args ) );
		if ( isset( $token_meta[ $token ] ) ) {
			$val = maybe_unserialize( $token_meta[ $token ] );
			if ( is_array( $val ) ) {
				return join( ' | ', $val );
			}

			return wp_strip_all_tags( $val );
		}

		if ( 'UOUSERIMPORTEDCOURSE' === $token && isset( $token_meta['learndash_course_id'] ) ) {
			return get_the_title( $token_meta['learndash_course_id'] );
		}
		if ( 'UOUSERIMPORTEDGROUP' === $token && isset( $token_meta['learndash_group_id'] ) ) {
			return get_the_title( $token_meta['learndash_group_id'] );
		}

		if ( 'UOUSERSTIMEINCOURSEEXCEEDS' === $token ) {
			return maybe_unserialize( Automator()->db->trigger->get_token_meta( 'UOUSERSTIMEINCOURSEEXCEEDS', $token_args ) );
		}
		if ( 'UOUSERSTIMEINCOURSEEXCEEDS_ID' === $token ) {
			return maybe_unserialize( Automator()->db->trigger->get_token_meta( 'UOUSERSTIMEINCOURSEEXCEEDS_ID', $token_args ) );
		}
		if ( 'UOUSERSTIMEINCOURSEEXCEEDS_COURSEMINUTES' === $token ) {
			return maybe_unserialize( Automator()->db->trigger->get_token_meta( 'UOUSERSTIMEINCOURSEEXCEEDS_COURSEMINUTES', $token_args ) );
		}
		if ( 'UOUSERSTIMEINCOURSEEXCEEDS_URL' === $token ) {
			return maybe_unserialize( Automator()->db->trigger->get_token_meta( 'UOUSERSTIMEINCOURSEEXCEEDS_URL', $token_args ) );
		}
		if ( 'UOUSERSTIMEINCOURSEEXCEEDS_THUMB_ID' === $token ) {
			return maybe_unserialize( Automator()->db->trigger->get_token_meta( 'UOUSERSTIMEINCOURSEEXCEEDS_THUMB_ID', $token_args ) );
		}
		if ( 'UOUSERSTIMEINCOURSEEXCEEDS_THUMB_URL' === $token ) {
			return maybe_unserialize( Automator()->db->trigger->get_token_meta( 'UOUSERSTIMEINCOURSEEXCEEDS_THUMB_URL', $token_args ) );
		}

		return $value;
	}

}
