<?php


namespace Uncanny_Automator;

use Uncanny_Automator_Pro\PeepSo_Pro_Helpers;

/**
 * Class PeepSo_Helpers
 *
 * @package Uncanny_Automator
 */
class PeepSo_Helpers {

	/**
	 * @var PeepSo_Helpers
	 */
	public $options;

	/**
	 * @var PeepSo_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options;

	/**
	 * Ninja_Forms_Helpers constructor.
	 */
	public function __construct() {
		$this->load_options = true;
	}

	/**
	 * @param PeepSo_Helpers $options
	 */
	public function setOptions( PeepSo_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param PeepSo_Pro_Helpers $pro
	 */
	public function setPro( PeepSo_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param Get gender of the follower
	 *
	 * @param $user_id
	 *
	 */
	public function get_gender( $user_id ) {
		if ( empty( $user_id ) ) {
			return;
		}
		$gender = get_user_meta( $user_id, 'peepso_user_field_gender', true );
		$acc    = get_user_meta( $user_id, 'peepso_user_field_gender_acc', true );

		if ( $gender === 'm' && $acc >= 0 ) {
			return 'Male';
		} elseif ( $gender === 'f' && $acc >= 0 ) {
			return 'Female';
		}
	}

	/**
	 * @param Get name of the follower
	 *
	 * @param $user_id
	 *
	 */
	public function get_name( $name, $type = 'full' ) {
		if ( empty( $name ) ) {
			return;
		}
		$full_name = explode( ' ', $name );
		if ( $type === 'last' ) {
			return isset( $full_name[1] ) ? $full_name[1] : '';
		}

		if ( $type === 'first' ) {
			return isset( $full_name[0] ) ? $full_name[0] : '';
		}

		if ( $type === 'full' ) {
			return $full_name;
		}

	}

	/**
	 * @param Get gender of the follower
	 *
	 * @param $user_id
	 *
	 */
	public function get_birthdate( $user_id ) {
		if ( empty( $user_id ) ) {
			return;
		}
		$bod = get_user_meta( $user_id, 'peepso_user_field_birthdate', true );
		$acc = get_user_meta( $user_id, 'peepso_user_field_birthdate_acc', true );

		if ( ! empty( $bod ) && $acc >= 0 ) {
			return $bod;
		}
	}

	/**
	 * @param Get bio of the follower
	 *
	 * @param $user_id
	 *
	 */
	public function get_bio( $user_id ) {
		if ( empty( $user_id ) ) {
			return;
		}
		$bod = get_user_meta( $user_id, 'description', true );
		$acc = get_user_meta( $user_id, 'peepso_user_field_description_acc', true );

		if ( ! empty( $bod ) && $acc >= 0 ) {
			return $bod;
		}
	}

	/**
	 * @param Get bio of the follower
	 *
	 * @param $user_id
	 *
	 */
	public function get_website( $user_id ) {
		if ( empty( $user_id ) ) {
			return;
		}
		$curauth = get_userdata( $user_id );

		if ( ! empty( $curauth->user_url ) ) {
			return $curauth->user_url;
		}
	}

	/**
	 * @param Get peepso users
	 *
	 */
	public function get_users( $label = null, $option_code = 'PPUSERS', $args = array(), $bynames = false ) {

		if ( ! $label ) {
			$label = esc_attr__( 'PeepSo member', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any PeepSo member', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options['-1'] = $args['uo_any_label'];
		}

		global $wpdb;
		$users = $wpdb->get_results( "SELECT * FROM `{$wpdb->prefix}peepso_users`", ARRAY_A );

		if ( count( $users ) > 0 ) {
			foreach ( $users as $user ) {
				if ( false === $bynames ) {
					$options[ $user['usr_id'] ] = get_user_by( 'id', $user['usr_id'] )->display_name;
				} else {
					$user_by_id = get_user_by( 'id', $user['usr_id'] );
					if ( $user_by_id instanceof \WP_User ) {
						$options[ $user['usr_id'] ] = sprintf( '%s %s [%s]', $user_by_id->last_name, $user_by_id->first_name, $user_by_id->user_email );
					} else {
						$options[ $user['usr_id'] ] = '#' . $user['usr_id'];
					}
				}
			}
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(),
		);

		return apply_filters( 'uap_option_peepso_all_users', $option );
	}


}
