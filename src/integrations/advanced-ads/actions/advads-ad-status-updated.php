<?php

namespace Uncanny_Automator;

/**
 * Class ADVADS_AD_STATUS_UPDATED
 *
 * @package Uncanny_Automator
 */
class ADVADS_AD_STATUS_UPDATED {
	use Recipe\Actions;

	/**
	 * Set up Automator action constructor.
	 */
	public function __construct() {
		$this->setup_action();
		$this->helpers = new Advanced_Ads_Helpers();
	}

	/**
	 * Define and register the action by pushing it into the Automator object
	 */
	protected function setup_action() {
		$this->set_integration( 'ADVADS' );
		$this->set_action_code( 'ADVADS_AD_STATUS' );
		$this->set_action_meta( 'ALL_ADS_META' );
		$this->set_requires_user( false );

		/* translators: Action - Advanced Ads */
		$this->set_sentence( sprintf( esc_attr__( 'Set {{an ad:%1$s}} to {{a specific status:%2$s}}', 'uncanny-automator' ), $this->get_action_meta(), 'AD_STATUS' ) );

		/* translators: Action - Advanced Ads */
		$this->set_readable_sentence( esc_attr__( 'Set {{an ad}} to {{a specific status}}', 'uncanny-automator' ) );

		$this->set_options_callback( array( $this, 'load_options' ) );
		$this->register_action();
	}

	/**
	 * load_options
	 *
	 * @return array
	 */
	public function load_options() {

		return Automator()->utilities->keep_order_of_options(
			array(
				'options' => array(
					$this->helpers->get_all_ads( $this->get_action_meta(), false, true ),
					$this->helpers->ad_statuses( 'AD_STATUS' ),
				),
			)
		);

	}

	/**
	 * Process the action.
	 *
	 * @param $user_id
	 * @param $action_data
	 * @param $recipe_id
	 * @param $args
	 * @param $parsed
	 *
	 * @return void.
	 * @throws \Exception
	 */
	protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
		$ad_id     = isset( $parsed[ $this->get_action_meta() ] ) ? sanitize_text_field( $parsed[ $this->get_action_meta() ] ) : '';
		$ad_status = isset( $parsed['AD_STATUS'] ) ? sanitize_text_field( $parsed['AD_STATUS'] ) : '';

		if ( empty( $ad_id ) || empty( $ad_status ) ) {
			return;
		}

		if ( intval( '-1' ) === intval( $ad_id ) ) {
			global $wpdb;
			$post_ids = $wpdb->get_results( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_status != %s AND post_type = 'advanced_ads'", $ad_status ), ARRAY_A );
			if ( $post_ids ) {
				foreach ( $post_ids as $post ) {
					$this->update_status( $post['ID'], $ad_status );
					clean_post_cache( $post['ID'] );
				}
			}
		} else {
			$this->update_status( $ad_id, $ad_status );
			clean_post_cache( $ad_id );
		}

		Automator()->complete->action( $user_id, $action_data, $recipe_id );
	}

	/**
	 * @param $post_id
	 * @param $ad_status
	 *
	 * @return void
	 */
	public function update_status( $post_id, $ad_status ) {
		switch ( $ad_status ) {
			case 'publish':
				wp_publish_post( $post_id );
				break;
			case 'advanced_ads_expired':
				$ad_options                = get_post_meta( $post_id, 'advanced_ads_ad_options', true );
				$new_expiry_date           = current_time( 'timestamp' );
				$ad_options['expiry_date'] = $new_expiry_date;
				update_post_meta( $post_id, 'advanced_ads_ad_options', $ad_options );
				$key = defined( '\Advanced_Ads_Ad_Expiration::POST_META' ) ? \Advanced_Ads_Ad_Expiration::POST_META : 'advanced_ads_expiration_time';
				update_post_meta( $post_id, $key, gmdate( 'Y-m-d H:i:s', $new_expiry_date ) );
				break;
			default:
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => $ad_status,
					)
				);
				break;
		}
	}
}
