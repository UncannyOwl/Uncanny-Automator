<?php


namespace Uncanny_Automator;

use PeepSoUser;
use PeepSoProfile;
use PeepSoProfileFields;
use PeepSoNotificationsIntensity;

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

	/**
	 * @param Get profile fields
	 */
	public function get_profile_fields( $label = null, $option_code = 'PPPROFILEFIELDS', $args = array(), $bynames = false ) {

		if ( ! $this->load_options ) {
			return Automator()->helpers->recipe->build_default_options_array( $label, $option_code );
		}

		if ( ! $label ) {
			$label = esc_attr__( 'Profile fields', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => ( true === $args['uo_include_any'] ) ? true : false,
				'uo_any_label'   => esc_attr__( 'Any field', 'uncanny-automator' ),
			)
		);

		$options = array();
		$options = $this->get_user_fields( 0, $args['uo_include_any'], $args );

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => array(),
		);

		return apply_filters( 'uap_option_peepso_users_profile_fields', $option );
	}

	public function get_user_fields( $user_id = 0, $any = false, $args = array() ) {
		$options    = array();
		$PeepSoUser = PeepSoUser::get_instance( $user_id );
		$PeepSoUser->profile_fields->load_fields();
		$fields = $PeepSoUser->profile_fields->get_fields();

		if ( true === $any ) {
			$options[- 1] = $args['uo_any_label'];
		}

		foreach ( $fields as $field ) {
			if ( 1 == $field->prop( 'published' ) ) {
				$options[ $field->prop( 'id' ) ] = $field->prop( 'title' );
			}
		};

		$options['peepso_is_profile_likable'] = __( 'Allow others to "like" my profile', 'uncanny-automator' );
		$options['peepso_hide_birthday_year'] = __( 'Hide my birthday year', 'uncanny-automator' );
		$options['usr_profile_acc']           = __( 'Who can see my profile', 'uncanny-automator' );
		$options['peepso_profile_post_acc']   = __( 'Who can post on my profile', 'uncanny-automator' );
		$options['peepso_hide_online_status'] = __( "Don't show my online status", 'uncanny-automator' );
		$options['peepso_gmt_offset']         = __( 'My timezone', 'uncanny-automator' );

		return $options;
	}

	public function get_gmt_value( $gmt_time ) {
		$offset_range = array(
			- 12,
			- 11.5,
			- 11,
			- 10.5,
			- 10,
			- 9.5,
			- 9,
			- 8.5,
			- 8,
			- 7.5,
			- 7,
			- 6.5,
			- 6,
			- 5.5,
			- 5,
			- 4.5,
			- 4,
			- 3.5,
			- 3,
			- 2.5,
			- 2,
			- 1.5,
			- 1,
			- 0.5,
			0,
			0.5,
			1,
			1.5,
			2,
			2.5,
			3,
			3.5,
			4,
			4.5,
			5,
			5.5,
			5.75,
			6,
			6.5,
			7,
			7.5,
			8,
			8.5,
			8.75,
			9,
			9.5,
			10,
			10.5,
			11,
			11.5,
			12,
			12.75,
			13,
			13.75,
			14,
		);
		foreach ( $offset_range as $offset ) {
			$offset_label = (string) $offset;
			if ( 0 <= $offset ) {
				$offset_label = '+' . $offset_label;
			}
			$offset_label = 'UTC' . str_replace(
				array(
					'.25',
					'.5',
					'.75',
				),
				array( ':15', ':30', ':45' ),
				$offset_label
			);
			if ( (string) $gmt_time === (string) $offset ) {
				return $offset_label;
			}
		}
	}

}
