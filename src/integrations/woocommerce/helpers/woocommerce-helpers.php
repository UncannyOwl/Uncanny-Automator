<?php


namespace Uncanny_Automator;

use DateTime;
use Uncanny_Automator_Pro\Woocommerce_Pro_Helpers;

/**
 * Class Woocommerce_Helpers
 *
 * @package Uncanny_Automator
 */
class Woocommerce_Helpers {
	/**
	 * @var Woocommerce_Helpers
	 */
	public $options;

	/**
	 * @var Woocommerce_Pro_Helpers
	 */
	public $pro;

	/**
	 * @var bool
	 */
	public $load_options = true;

	/**
	 *
	 */
	public function __construct() {
		add_action(
			'wp_ajax_search_wc_products_for_trigger',
			array(
				$this,
				'search_wc_products_for_trigger',
			)
		);
	}

	/**
	 * AJAX handler for searching WC products. Used with `search_options` ajax event.
	 *
	 * @return void
	 */
	public function search_wc_products_for_trigger() {

		Automator()->utilities->ajax_auth_check();

		$search_term = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		$args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => apply_filters( 'automator_wc_product_search_limit', 50 ),
			'orderby'        => 'title',
			'order'          => 'ASC',
			's'              => $search_term,
		);

		// Allow searching by product ID directly.
		if ( is_numeric( $search_term ) ) {
			$args['post__in'] = array( absint( $search_term ) );
			unset( $args['s'] );
		}

		$products = get_posts( $args );
		$options  = array();

		$options[] = array(
			'value' => '-1',
			'text'  => __( 'Any product', 'uncanny-automator' ),
		);

		foreach ( $products as $product ) {
			$title = ! empty( $product->post_title )
				? $product->post_title
				: sprintf( /* translators: %d is the product ID */ __( 'ID: %d (no title)', 'uncanny-automator' ), $product->ID );

			$options[] = array(
				'value' => $product->ID,
				'text'  => $title,
			);
		}

		echo wp_json_encode(
			array(
				'success' => true,
				'options' => $options,
			)
		);

		wp_die();
	}

	/**
	 * Returns a product select field configured with AJAX search.
	 *
	 * @param string $option_code     The option code for the field.
	 * @param array  $relevant_tokens Optional. Override the default relevant tokens.
	 *
	 * @return array
	 */
	public static function get_ajax_product_select_field( $option_code = 'WOOPRODUCT', $relevant_tokens = null ) {
		if ( null === $relevant_tokens ) {
			$relevant_tokens = array(
				$option_code                                     => esc_attr__( 'Product title', 'uncanny-automator' ),
				$option_code . '_ID'                             => esc_attr__( 'Product ID', 'uncanny-automator' ),
				$option_code . '_URL'                            => esc_attr__( 'Product URL', 'uncanny-automator' ),
				$option_code . '_THUMB_ID'                       => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
				$option_code . '_THUMB_URL'                      => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
				$option_code . '_PRODUCT_PRICE'                  => esc_attr__( 'Product price', 'uncanny-automator' ),
				$option_code . '_PRODUCT_PRICE_UNFORMATTED'      => esc_attr__( 'Product price (unformatted)', 'uncanny-automator' ),
				$option_code . '_PRODUCT_SALE_PRICE'             => esc_attr__( 'Product sale price', 'uncanny-automator' ),
				$option_code . '_PRODUCT_SALE_PRICE_UNFORMATTED' => esc_attr__( 'Product sale price (unformatted)', 'uncanny-automator' ),
				$option_code . '_ORDER_QTY'                      => esc_attr__( 'Product quantity', 'uncanny-automator' ),
			);
		}

		return Automator()->helpers->recipe->field->select_field_args(
			array(
				'option_code'     => $option_code,
				'label'           => esc_attr__( 'Product', 'uncanny-automator' ),
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => $relevant_tokens,
				'ajax'            => array(
					'endpoint' => 'search_wc_products_for_trigger',
					'event'    => 'search_options',
				),
			)
		);
	}

	/**
	 * @param Woocommerce_Helpers $options
	 */
	public function setOptions( Woocommerce_Helpers $options ) {
		$this->options = $options;
	}

	/**
	 * @param Woocommerce_Pro_Helpers $pro
	 */
	public function setPro( Woocommerce_Pro_Helpers $pro ) {
		$this->pro = $pro;
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function all_wc_products( $label = null, $option_code = 'WOOPRODUCT' ) {

		$relevant_tokens                                = array(
			$option_code                                => esc_attr__( 'Product title', 'uncanny-automator' ),
			$option_code . '_ID'                        => esc_attr__( 'Product ID', 'uncanny-automator' ),
			$option_code . '_URL'                       => esc_attr__( 'Product URL', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'                  => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL'                 => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
			$option_code . '_PRODUCT_PRICE'             => esc_attr__( 'Product price', 'uncanny-automator' ),
			$option_code . '_PRODUCT_PRICE_UNFORMATTED' => esc_attr__( 'Product price (unformatted)', 'uncanny-automator' ),
			$option_code . '_PRODUCT_SALE_PRICE'        => esc_attr__( 'Product sale price', 'uncanny-automator' ),
			$option_code . '_PRODUCT_SALE_PRICE_UNFORMATTED' => esc_attr__( 'Product sale price (unformatted)', 'uncanny-automator' ),
		);
		$relevant_tokens[ $option_code . '_ORDER_QTY' ] = esc_attr__( 'Product quantity', 'uncanny-automator' );

		return $this->load_products( $label, $option_code, $relevant_tokens );
	}

	/**
	 * @param $label
	 * @param $option_code
	 *
	 * @return array
	 */
	public function all_wc_view_products( $label = null, $option_code = 'WOOPRODUCT' ) {
		$relevant_tokens                                 = array(
			$option_code                                => esc_attr__( 'Product title', 'uncanny-automator' ),
			$option_code . '_ID'                        => esc_attr__( 'Product ID', 'uncanny-automator' ),
			$option_code . '_URL'                       => esc_attr__( 'Product URL', 'uncanny-automator' ),
			$option_code . '_THUMB_ID'                  => esc_attr__( 'Product featured image ID', 'uncanny-automator' ),
			$option_code . '_THUMB_URL'                 => esc_attr__( 'Product featured image URL', 'uncanny-automator' ),
			$option_code . '_PRODUCT_PRICE'             => esc_attr__( 'Product price', 'uncanny-automator' ),
			$option_code . '_PRODUCT_PRICE_UNFORMATTED' => esc_attr__( 'Product price (unformatted)', 'uncanny-automator' ),
			$option_code . '_PRODUCT_SALE_PRICE'        => esc_attr__( 'Product sale price', 'uncanny-automator' ),
			$option_code . '_PRODUCT_SALE_PRICE_UNFORMATTED' => esc_attr__( 'Product sale price (unformatted)', 'uncanny-automator' ),
		);
		$relevant_tokens[ $option_code . '_SKU' ]        = esc_attr__( 'Product SKU', 'uncanny-automator' );
		$relevant_tokens[ $option_code . '_CATEGORIES' ] = esc_attr__( 'Product categories', 'uncanny-automator' );
		$relevant_tokens[ $option_code . '_TAGS' ]       = esc_attr__( 'Product tags', 'uncanny-automator' );

		return $this->load_products( $label, $option_code, $relevant_tokens );
	}

	/**
	 * @param $label
	 * @param $option_code
	 * @param $relevant_tokens
	 *
	 * @return mixed|null
	 */
	public function load_products( $label = null, $option_code = 'WOOPRODUCT', $relevant_tokens = array() ) {

		if ( ! $label ) {
			$label = esc_attr__( 'Product', 'uncanny-automator' );
		}

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => 999999,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'post_status'    => 'publish',
		);

		$options = Automator()->helpers->recipe->options->wp_query( $args, true, esc_attr__( 'Any product', 'uncanny-automator' ) );
		$option  = array(
			'option_code'     => $option_code,
			'label'           => $label,
			'input_type'      => 'select',
			'required'        => true,
			'options'         => $options,
			'relevant_tokens' => $relevant_tokens,
		);

		return apply_filters( 'uap_option_all_wc_products', $option );
	}

	/**
	 * @param string $label
	 * @param string $option_code
	 *
	 * @return mixed
	 */
	public function wc_order_statuses( $label = null, $option_code = 'WCORDERSTATUS' ) {

		if ( ! $label ) {
			$label = 'Status';
		}

		$option = array(
			'option_code' => $option_code,
			'label'       => $label,
			'input_type'  => 'select',
			'required'    => true,
			'options'     => wc_get_order_statuses(),
		);

		return apply_filters( 'uap_option_woocommerce_statuses', $option );
	}

	/**
	 * @param string $code
	 *
	 * @return mixed|void
	 */
	public function get_woocommerce_trigger_conditions( $code = 'TRIGGERCOND' ) {
		$options = array(
			'option_code' => $code,
			/* translators: Noun */
			'label'       => esc_attr__( 'Trigger condition', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => $this->get_trigger_condition_labels(),
		);

		return apply_filters( 'uap_option_woocommerce_trigger_conditions', $options );
	}

	/**
	 * Fetch labels for trigger conditions.
	 *
	 * @return array
	 * @since 2.10
	 */
	public function get_trigger_condition_labels() {
		/**
		 * Filters WooCommerce Integrations' trigger conditions.
		 *
		 * @param array $trigger_conditions An array of key-value pairs of action hook handle and human readable label.
		 */
		return apply_filters(
			'uap_wc_trigger_conditions',
			array(
				'woocommerce_payment_complete'       => _x( 'pays for', 'WooCommerce', 'uncanny-automator' ),
				'woocommerce_order_status_completed' => _x( 'completes', 'WooCommerce', 'uncanny-automator' ),
				'woocommerce_thankyou'               => _x( 'lands on a thank you page for', 'WooCommerce', 'uncanny-automator' ),
			)
		);
	}

	/**
	 * Retrieves orders for a specific user in WooCommerce within a specified date range.
	 *
	 * @param int|null $user_id The ID of the user whose orders are being retrieved. Pass null to fetch site orders.
	 * @param mixed $date_filter DateTime object or string ('24_hours', 'weekly', 'monthly', 'yearly').
	 * @return array|false An array of orders with details or false on failure.
	 */
	public static function get_user_orders( $user_id = null, $date_filter = null ) {

		// Ensure WooCommerce functions are available.
		if ( ! class_exists( '\WC_Order_Query' ) ) {
			return false; // WooCommerce is not active or not available.
		}

		// Determine the date range for fetching orders.
		$date_after = null;

		if ( $date_filter instanceof \DateTime ) {

			$date_after = $date_filter->getTimestamp(); // Use timestamp.

		} elseif ( is_string( $date_filter ) ) {

			switch ( $date_filter ) {
				case '24_hours':
					$date_after = ( new \DateTime( '-24 hours' ) )->getTimestamp();
					break;
				case 'weekly':
					$date_after = ( new \DateTime( '-7 days' ) )->getTimestamp();
					break;
				case 'monthly':
					$date_after = ( new \DateTime( '-30 days' ) )->getTimestamp();
					break;
				case 'yearly':
					$date_after = ( new \DateTime( '-365 days' ) )->getTimestamp();
					break;
				default:
					return false; // Invalid date filter string provided.
			}
		}

		// Set up query arguments.
		$args = array(
			'limit'   => 99999, // Retrieve all orders.
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( ! empty( $user_id ) ) {
			$args['customer_id'] = $user_id;
		}

		if ( $date_after ) {
			$args['date_created'] = '>' . $date_after;
		}

		// Fetch orders using WC_Order_Query.
		$order_query = new \WC_Order_Query( $args );
		$orders      = $order_query->get_orders();

		// Check if any orders were found.
		if ( empty( $orders ) ) {
			return false; // No orders found for this user.
		}

		$orders_data = array();

		// Loop through each order and gather relevant details.
		foreach ( $orders as $order ) {
			// Ensure $order is a valid \WC_Order object.
			if ( ! is_object( $order ) || ! ( $order instanceof \WC_Order ) ) {
				continue; // Skip if the object is not a valid order.
			}

			$order_items = array();

			// Get the items associated with the order.
			foreach ( $order->get_items() as $item_id => $item ) {
				$product = $item->get_product();

				if ( ! $product ) {
					continue; // Skip if the product is not found or no longer exists.
				}

				// Format item details as a comma-separated string.
				$order_items[] = sprintf(
					'%s (ID: %d, Quantity: %d, Total: %s)',
					$product->get_name(),
					$product->get_id(),
					$item->get_quantity(),
					wc_price( $item->get_total() )
				);
			}

			// Join the order items into a single string, separated by commas.
			$order_items_string = implode( ', ', $order_items );

			$orders_data[] = array(
				'order_id'     => $order->get_id(),
				'date_created' => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
				'total'        => $order->get_total(),
				'status'       => $order->get_status(),
				'items'        => wp_strip_all_tags( $order_items_string ),
			);
		}

		// Return the orders data or false if no valid orders were processed.
		return ! empty( $orders_data ) ? $orders_data : false;
	}

}
