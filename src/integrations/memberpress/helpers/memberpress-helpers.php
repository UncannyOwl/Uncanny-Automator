<?php


namespace Uncanny_Automator;

use MeprUser;
use Uncanny_Automator_Pro\Memberpress_Pro_Helpers;

/**
 * Class Memberpress_Helpers
 *
 * @package Uncanny_Automator
 */
class Memberpress_Helpers {

	/**
	 * @var Memberpress_Helpers
	 */
	public $options;

	/**
	 * @var Memberpress_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 * Memberpress_Helpers constructor.
	 */
	public function __construct() {
		// Static callback: every helper instance (Pro's included) shares one registration.
		add_filter( 'automator_trigger_object_codec_adapters', array( self::class, 'register_codec_adapters' ) );
	}

	/**
	 * Carry MemberPress's hook-argument models through the trigger loopback.
	 *
	 * MemberPress handles gateway webhooks and signup forms on init priority 10 and
	 * exits, before recipe parts load, so its triggers run through the AJAX loopback.
	 * Each model travels as its ID and MemberPress reloads it on the receiving request.
	 *
	 * @param array<string, array{encode: callable, decode: callable}> $adapters Adapters keyed by class name.
	 *
	 * @return array<string, array{encode: callable, decode: callable}>
	 */
	public static function register_codec_adapters( $adapters ) {

		foreach ( array( 'MeprTransaction', 'MeprSubscription', 'MeprEvent' ) as $model ) {
			$adapters[ $model ] = array(
				'encode' => function ( $instance ) {
					return (int) $instance->id;
				},
				'decode' => function ( $id ) use ( $model ) {
					return new $model( $id );
				},
			);
		}

		// MeprUser is keyed by its WordPress user ID.
		$adapters['MeprUser'] = array(
			'encode' => function ( $user ) {
				return (int) $user->ID;
			},
			'decode' => function ( $id ) {
				return new MeprUser( $id );
			},
		);

		return $adapters;
	}

	/**
	 * @param Memberpress_Helpers $options
	 */
	public function setOptions( Memberpress_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Memberpress_Pro_Helpers $pro
	 */
	public function setPro( Memberpress_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products( $label = null, $option_code = 'MPPRODUCT', $args = array() ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any product', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options['-1'] = $args['uo_any_label'];
		}

		$results = Automator()->helpers->recipe->options->wp_query( array( 'post_type' => 'memberpressproduct' ) );
		if ( ! empty( $results ) ) {
			foreach ( $results as $k => $v ) {
				$options[ $k ] = $v;
			}
		}

		$relevant_tokens = array(
			$option_code                => esc_attr__( 'Product title', 'uncanny-automator' ),
			$option_code . '_ID'        => esc_attr__( 'Product ID', 'uncanny-automator' ),
			$option_code . '_URL'       => esc_attr__( 'Product URL', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'  => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL' => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
		);

		if ( isset( $args['relevant_tokens'] ) && ! empty( $args['relevant_tokens'] ) && is_array( $args['relevant_tokens'] ) ) {
			$relevant_tokens = array_merge( $relevant_tokens, $args['relevant_tokens'] );
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_memberpress_products', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products_onetime( $label = null, $option_code = 'MPPRODUCT', $args = array() ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any one-time subscription product', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options['-1'] = $args['uo_any_label'];
		}

		//$posts   = get_posts( );
		$query_args = array(
			'post_type'      => 'memberpressproduct',
			'posts_per_page' => 999,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_mepr_product_period_type',
					'value'   => 'lifetime',
					'compare' => '=',
				),
			),
		);

		$results = Automator()->helpers->recipe->wp_query( $query_args );

		if ( ! empty( $results ) ) {
			foreach ( $results as $k => $v ) {
				$options[ $k ] = $v;
			}
		}

		$relevant_tokens = array(
			$option_code                => esc_attr__( 'Product title', 'uncanny-automator' ),
			$option_code . '_ID'        => esc_attr__( 'Product ID', 'uncanny-automator' ),
			$option_code . '_URL'       => esc_attr__( 'Product URL', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'  => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL' => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
		);

		if ( isset( $args['relevant_tokens'] ) && ! empty( $args['relevant_tokens'] ) && is_array( $args['relevant_tokens'] ) ) {
			$relevant_tokens = array_merge( $relevant_tokens, $args['relevant_tokens'] );
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_memberpress_products_onetime', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 * @param array  $args
	 *
	 * @return mixed
	 */
	public function all_memberpress_products_recurring( $label = null, $option_code = 'MPPRODUCT', $args = array() ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = wp_parse_args(
			$args,
			array(
				'uo_include_any' => false,
				'uo_any_label'   => esc_attr__( 'Any recurring subscription product', 'uncanny-automator' ),
			)
		);

		$options = array();

		if ( $args['uo_include_any'] ) {
			$options['-1'] = $args['uo_any_label'];
		}

		$query_args = array(
			'post_type'      => 'memberpressproduct',
			'posts_per_page' => 999,
			'post_status'    => 'publish',
			'meta_query'     => array(
				array(
					'key'     => '_mepr_product_period_type',
					'value'   => 'lifetime',
					'compare' => '!=',
				),
			),
		);

		$results = Automator()->helpers->recipe->wp_query( $query_args );
		if ( ! empty( $results ) ) {
			foreach ( $results as $k => $v ) {
				$options[ $k ] = $v;
			}
		}

		$relevant_tokens = array(
			$option_code                => esc_attr__( 'Product title', 'uncanny-automator' ),
			$option_code . '_ID'        => esc_attr__( 'Product ID', 'uncanny-automator' ),
			$option_code . '_URL'       => esc_attr__( 'Product URL', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'  => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL' => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
		);

		if ( isset( $args['relevant_tokens'] ) && ! empty( $args['relevant_tokens'] ) && is_array( $args['relevant_tokens'] ) ) {
			$relevant_tokens = array_merge( $relevant_tokens, $args['relevant_tokens'] );
		}

		$option = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_memberpress_products_recurring', $option );
	}

	/**
	 * @param $subscription
	 *
	 * @return bool
	 */
	public function check_if_is_renewal_or_first_payment( $subscription ) {
		if ( $subscription !== false ) {
			if ( ( ! $subscription->trial || ( $subscription->trial && $subscription->trial_amount <= 0.00 ) ) && $subscription->txn_count == 1 ) {
				return true;
			} elseif ( $subscription->trial && $subscription->trial_amount > 0.00 && $subscription->txn_count == 2 ) {
				return true;
			}
		}

		return false;
	}

}
