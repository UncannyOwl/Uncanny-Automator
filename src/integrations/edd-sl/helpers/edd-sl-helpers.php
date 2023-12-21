<?php

namespace Uncanny_Automator\Integrations\Edd_SL;

/**
 * Class Edd_Helpers
 *
 * @package Uncanny_Automator
 */
class Edd_Sl_Helpers {

	/**
	 * @return array
	 */
	public function get_all_downloads( $is_any = true ) {
		$all_downloads = array();
		if ( true === $is_any ) {
			$all_downloads[] = array(
				'text'  => _x( 'Any download', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$args = array(
			'post_type'      => 'download',
			'posts_per_page' => 9999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
			'meta_key'       => '_edd_sl_enabled',
			'meta_value'     => 1,
		);

		$downloads = get_posts( $args );

		foreach ( $downloads as $download ) {
			$all_downloads[] = array(
				'text'  => $download->post_title,
				'value' => $download->ID,
			);
		}

		return $all_downloads;
	}

	/**
	 * @return array[]
	 */
	public function get_common_tokens() {
		return array(
			array(
				'tokenId'   => 'DOWNLOAD_ID',
				'tokenName' => __( 'Download ID', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DOWNLOAD_NAME',
				'tokenName' => __( 'Download name', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'DOWNLOAD_QTY',
				'tokenName' => __( 'Download quantity', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DOWNLOAD_SUBTOTAL',
				'tokenName' => __( 'Download subtotal', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DOWNLOAD_TAX',
				'tokenName' => __( 'Download tax', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'DOWNLOAD_PRICE',
				'tokenName' => __( 'Download price', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
			array(
				'tokenId'   => 'LICENSE_KEY',
				'tokenName' => __( 'License key', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LICENSE_PURCHASE_DATE',
				'tokenName' => __( 'License purchase date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'LICENSE_EXPIRATION_DATE',
				'tokenName' => __( 'License expiration date', 'uncanny-automator' ),
				'tokenType' => 'date',
			),
			array(
				'tokenId'   => 'LICENSE_TERM',
				'tokenName' => __( 'License term', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => 'LICENSE_ACTIVATION_LIMIT',
				'tokenName' => __( 'License activation limit', 'uncanny-automator' ),
				'tokenType' => 'int',
			),
		);
	}

	/**
	 * @param $license_id
	 * @param $download_id
	 *
	 * @return array
	 */
	public function parse_common_token_values( $license_id, $download_id ) {
		$license = edd_software_licensing()->get_license( $license_id );

		// Generate array of empty default values.
		$defaults = wp_list_pluck( $this->get_common_tokens(), 'tokenId' );
		$tokens   = array_fill_keys( $defaults, '' );

		$tokens['DOWNLOAD_NAME']            = get_the_title( $download_id );
		$tokens['DOWNLOAD_ID']              = $download_id;
		$tokens['DOWNLOAD_QTY']             = edd_get_cart_item_quantity( $download_id );
		$tokens['DOWNLOAD_SUBTOTAL']        = edd_get_cart_items_subtotal( $download_id );
		$tokens['DOWNLOAD_TAX']             = edd_get_cart_item_tax( $download_id );
		$tokens['DOWNLOAD_PRICE']           = edd_get_cart_item_price( $download_id );
		$tokens['LICENSE_KEY']              = $license->license_key;
		$tokens['LICENSE_TERM']             = $license->license_term();
		$tokens['LICENSE_ACTIVATION_LIMIT'] = $license->get_activation_limit();
		$tokens['LICENSE_PURCHASE_DATE']    = date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $license->date_created ) );
		$tokens['LICENSE_EXPIRATION_DATE']  = date( get_option( 'date_format' ), $license->expiration );

		return $tokens;

	}

}
