<?php

namespace Uncanny_Automator\Integrations\Fluent_Cart;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Fluent_Cart_Helpers
 *
 * Shared option-data (remote_data handlers), product/status field builders,
 * matching/resolution helpers, and the token surface for the FluentCart
 * integration.
 *
 * @package Uncanny_Automator\Integrations\Fluent_Cart
 */
class Fluent_Cart_Helpers extends Abstract_Helpers {

	use Reads_Entity_Props;

	/**
	 * "Any" sentinel value.
	 */
	const ANY = '-1';

	/**
	 * Token group definitions + hydration, built once per helper.
	 *
	 * @var Fluent_Cart_Tokens|null
	 */
	private $tokens = null;

	/**
	 * FluentCart product CPT slug.
	 */
	const PRODUCT_CPT = 'fluent-products';

	/**
	 * FluentCart product category taxonomy slug.
	 */
	const PRODUCT_TAXONOMY = 'product-categories';

	/**
	 * Category (parent) + product (child) field pair for product-scoped triggers.
	 *
	 * The child product select is the trigger's primary field, so its option_code
	 * must be the trigger meta. The category is an optional filter that narrows the
	 * product dropdown via the remote_data parent cascade.
	 *
	 * @param string $product_option_code The product field option code (the trigger meta).
	 *
	 * @return array[]
	 */
	public function product_option_fields( $product_option_code ) {
		return array(
			array(
				'option_code' => 'FLUENT_CART_PRODUCT_CATEGORY',
				'label'       => esc_html_x( 'Product category', 'FluentCart', 'uncanny-automator' ),
				'input_type'  => 'select',
				'required'    => false,
				'options'     => array(),
				'remote_data' => $this->remote_data_load_config( 'product_categories' ),
			),
			array(
				'option_code'     => $product_option_code,
				'label'           => esc_html_x( 'Product', 'FluentCart', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->remote_data_parent_config( 'products', array( 'FLUENT_CART_PRODUCT_CATEGORY' ) ),
			),
		);
	}

	/**
	 * Order-status select field (with "Any status" sentinel) for triggers.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function order_status_field( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Status', 'FluentCart', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'order_statuses' ),
		);
	}

	/**
	 * Subscription-status select field (with "Any status" sentinel) for triggers.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function subscription_status_field( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Status', 'FluentCart', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'subscription_statuses' ),
		);
	}

	/**
	 * Refund-type select field (with "Any refund" sentinel) for the refund trigger.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function refund_type_field( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Refund type', 'FluentCart', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'refund_types' ),
		);
	}

	/**
	 * Order-status select field WITHOUT an "Any" sentinel — for the "Set order status" action.
	 *
	 * @param string $option_code
	 *
	 * @return array
	 */
	public function order_status_field_strict( $option_code ) {
		return array(
			'option_code' => $option_code,
			'label'       => esc_html_x( 'Status', 'FluentCart', 'uncanny-automator' ),
			'input_type'  => 'select',
			'required'    => true,
			'options'     => array(),
			'remote_data' => $this->remote_data_load_config( 'order_statuses_strict' ),
		);
	}

	/* ------------------------------------------------------------------ *
	 * remote_data handlers
	 * ------------------------------------------------------------------ */

	/**
	 * Product categories (with "Any category" sentinel).
	 *
	 * @param object $request
	 *
	 * @return array
	 */
	protected function remote_data_get_product_categories( $request ): array {

		$options = array(
			array(
				'text'  => esc_html_x( 'Any category', 'FluentCart', 'uncanny-automator' ),
				'value' => self::ANY,
			),
		);

		$terms = get_terms(
			array(
				'taxonomy'   => self::PRODUCT_TAXONOMY,
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[] = array(
					'text'  => esc_html( $term->name ),
					'value' => (string) $term->term_id,
				);
			}
		}

		return $this->remote_data_success( $options );
	}

	/**
	 * Products (with "Any product" sentinel), optionally filtered by the chosen
	 * category. For triggers, which may legitimately match any product.
	 *
	 * @param object $request
	 *
	 * @return array
	 */
	protected function remote_data_get_products( $request ): array {
		return $this->remote_data_success( $this->product_options( $request, true ) );
	}

	/**
	 * Products WITHOUT the "Any product" sentinel, for concrete operations
	 * (actions and conditions).
	 *
	 * A condition evaluates a concrete claim, so "Any product" has no defined
	 * meaning — and it is not merely useless, it is silently wrong:
	 * {@see self::order_matches_product()} short-circuits to true on the
	 * sentinel without reading the order's line items, which makes a `contains`
	 * check pass unconditionally and a `does not contain` check fail forever
	 * (blocking the recipe with no error surfaced).
	 *
	 * @param object $request
	 *
	 * @return array
	 */
	protected function remote_data_get_products_strict( $request ): array {
		return $this->remote_data_success( $this->product_options( $request, false ) );
	}

	/**
	 * Shared product-option builder for both the sentinel and strict variants.
	 *
	 * @param object $request
	 * @param bool   $include_any Prepend the "Any product" sentinel.
	 *
	 * @return array
	 */
	protected function product_options( $request, $include_any ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any product', 'FluentCart', 'uncanny-automator' ),
				'value' => self::ANY,
			);
		}

		$args = array(
			'post_type'      => self::PRODUCT_CPT,
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$category_id = absint( $request->get_field_value( 'FLUENT_CART_PRODUCT_CATEGORY' ) );

		if ( $category_id ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => self::PRODUCT_TAXONOMY,
					'field'    => 'term_id',
					'terms'    => $category_id,
				),
			);
		}

		foreach ( get_posts( $args ) as $product ) {
			$options[] = array(
				'text'  => esc_html( $product->post_title ),
				'value' => (string) $product->ID,
			);
		}

		return $options;
	}

	/**
	 * Order statuses (with "Any status" sentinel) for triggers.
	 *
	 * @param object $request
	 *
	 * @return array
	 */
	protected function remote_data_get_order_statuses( $request ): array {
		return $this->remote_data_success( $this->status_options( $this->get_order_statuses(), true ) );
	}

	/**
	 * Order statuses WITHOUT a sentinel for actions.
	 *
	 * @param object $request
	 *
	 * @return array
	 */
	protected function remote_data_get_order_statuses_strict( $request ): array {
		return $this->remote_data_success( $this->status_options( $this->get_order_statuses(), false ) );
	}

	/**
	 * Subscription statuses (with "Any status" sentinel) for triggers.
	 *
	 * @param object $request
	 *
	 * @return array
	 */
	protected function remote_data_get_subscription_statuses( $request ): array {
		return $this->remote_data_success( $this->status_options( $this->get_subscription_statuses(), true ) );
	}

	/**
	 * Refund types (with "any" sentinel).
	 *
	 * The selected label substitutes into the trigger sentence's
	 * `{{fully or partially}}` brace, so the labels are lowercase and carry no
	 * "refunded" of their own — the sentence already supplies it.
	 *
	 * @param object $request
	 *
	 * @return array
	 */
	protected function remote_data_get_refund_types( $request ): array {

		$types = array(
			'full'    => esc_html_x( 'fully', 'FluentCart', 'uncanny-automator' ),
			'partial' => esc_html_x( 'partially', 'FluentCart', 'uncanny-automator' ),
		);

		return $this->remote_data_success(
			$this->status_options( $types, true, esc_html_x( 'any', 'FluentCart', 'uncanny-automator' ) )
		);
	}

	/* ------------------------------------------------------------------ *
	 * Status sources
	 * ------------------------------------------------------------------ */

	/**
	 * Order statuses from FluentCart's Status helper (value => label).
	 *
	 * @return array
	 */
	public function get_order_statuses() {
		if ( class_exists( '\FluentCart\App\Helpers\Status' ) ) {
			return (array) \FluentCart\App\Helpers\Status::getOrderStatuses();
		}
		return array();
	}

	/**
	 * Subscription statuses from FluentCart's Status helper (value => label).
	 *
	 * @return array
	 */
	public function get_subscription_statuses() {
		if ( class_exists( '\FluentCart\App\Helpers\Status' ) ) {
			return (array) \FluentCart\App\Helpers\Status::getSubscriptionStatuses();
		}
		return array();
	}

	/**
	 * Map a value => label status array to {text,value} options, optionally
	 * prepending an "Any" sentinel.
	 *
	 * @param array  $statuses
	 * @param bool   $include_any
	 * @param string $any_label
	 *
	 * @return array
	 */
	protected function status_options( $statuses, $include_any, $any_label = '' ) {

		$options = array();

		if ( $include_any ) {
			$options[] = array(
				'text'  => '' !== $any_label ? $any_label : esc_html_x( 'Any status', 'FluentCart', 'uncanny-automator' ),
				'value' => self::ANY,
			);
		}

		foreach ( $statuses as $value => $label ) {
			$options[] = array(
				'text'  => esc_html( $label ),
				'value' => (string) $value,
			);
		}

		return $options;
	}

	/* ------------------------------------------------------------------ *
	 * Matching + user resolution
	 * ------------------------------------------------------------------ */

	/**
	 * Resolve the WordPress user ID from a FluentCart customer (model or array).
	 *
	 * @param mixed $customer
	 *
	 * @return int 0 when the customer has no linked WP user (guest).
	 */
	public function get_user_id_from_customer( $customer ) {
		return absint( $this->prop( $customer, 'user_id', 0 ) );
	}

	/**
	 * Resolve the WordPress user ID from a subscription's own customer link.
	 *
	 * The subscription hooks don't always carry a `customer` (or an `order` to
	 * read one from), but the subscription itself always references a customer,
	 * so resolve through `fct_customers` rather than skipping the trigger.
	 *
	 * @param mixed $subscription Subscription model, stdClass, or serialized array.
	 *
	 * @return int 0 when the customer has no linked WP user (guest).
	 */
	public function get_user_id_from_subscription( $subscription ) {
		return $this->get_user_id_from_customer( $this->get_customer_from_subscription( $subscription ) );
	}

	/**
	 * Read the customer a subscription belongs to, straight from FluentCart's table.
	 *
	 * The row carries `id`, `user_id`, `email`, `first_name` and `last_name`, so it
	 * satisfies both user resolution and the customer tokens.
	 *
	 * @param mixed $subscription Subscription model, stdClass, or serialized array.
	 *
	 * @return object|null
	 */
	public function get_customer_from_subscription( $subscription ) {
		return $this->get_customer_by_id( $this->prop( $subscription, 'customer_id' ) );
	}

	/**
	 * Read a customer row straight from FluentCart's table by primary key.
	 *
	 * Deliberately queries the table rather than going through FluentCart's
	 * model: entity payloads reach triggers from Action Scheduler and REST
	 * contexts where the model classes are not guaranteed to be loadable, and
	 * a null customer silently fails user resolution (the trigger just never
	 * fires). The row carries `id`, `user_id`, `email`, `first_name` and
	 * `last_name`, so it satisfies both user resolution and the customer
	 * tokens.
	 *
	 * @param mixed $customer_id
	 *
	 * @return object|null
	 */
	public function get_customer_by_id( $customer_id ) {

		$customer_id = absint( $customer_id );

		if ( empty( $customer_id ) ) {
			return null;
		}

		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}fct_customers WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$customer_id
			)
		);
	}

	/**
	 * Read a *required* field's saved value out of a trigger's configuration.
	 *
	 * Returns null when the key is missing or empty, so callers can fail closed.
	 * A required field has no meaningful default: defaulting an absent value to
	 * the "Any" sentinel silently widens the trigger to fire for every product,
	 * and for a filter, firing too much is the damaging direction — the recipe
	 * runs for orders the user never scoped it to, with nothing to indicate why.
	 *
	 * An explicitly saved '-1' is a real user choice ("Any product") and is
	 * returned as-is; only a genuinely absent value yields null. Optional fields
	 * (e.g. the product category) should NOT use this — for them "absent" really
	 * does mean "no filter", so the sentinel is the correct default.
	 *
	 * @param array  $trigger     The trigger configuration.
	 * @param string $option_code The field's option code.
	 *
	 * @return string|null Null when unset — caller should refuse to fire.
	 */
	public function required_meta_value( $trigger, $option_code ) {

		if ( ! isset( $trigger['meta'][ $option_code ] ) ) {
			return null;
		}

		$value = (string) $trigger['meta'][ $option_code ];

		return '' === $value ? null : $value;
	}

	/**
	 * Does an order contain the selected product (or any product in the selected
	 * category)? A specific product wins; otherwise an "Any product" selection can
	 * still be scoped to a category.
	 *
	 * @param object $order
	 * @param string $selected_product
	 * @param string $selected_category
	 *
	 * @return bool
	 */
	public function order_matches_product( $order, $selected_product, $selected_category ) {

		$is_any_product  = self::ANY === (string) $selected_product;
		$is_any_category = self::ANY === (string) $selected_category || '' === (string) $selected_category;

		// Nothing to narrow by — skip the line-item read entirely.
		if ( $is_any_product && $is_any_category ) {
			return true;
		}

		$product_ids = $this->get_order_product_ids( $order );

		if ( ! $is_any_product ) {
			return in_array( absint( $selected_product ), $product_ids, true );
		}

		foreach ( $product_ids as $product_id ) {
			if ( has_term( absint( $selected_category ), self::PRODUCT_TAXONOMY, $product_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Product post IDs on an order, whatever shape the payload arrived in.
	 *
	 * `order_items` is a hasMany relation, so FluentCart hands us a Collection
	 * object — a `(array)` cast on that returns the Collection's own protected
	 * properties, not the line items. The relation may also be absent entirely
	 * (not eager-loaded, or the payload serialized through Action Scheduler), so
	 * an empty read falls back to the `fct_order_items` table.
	 *
	 * @param mixed $order Order model, stdClass, or serialized array.
	 *
	 * @return int[]
	 */
	public function get_order_product_ids( $order ) {

		$items = $this->prop( $order, 'order_items', null );

		if ( is_object( $items ) && method_exists( $items, 'toArray' ) ) {
			$items = $items->toArray();
		} elseif ( $items instanceof \Traversable ) {
			$items = iterator_to_array( $items );
		}

		$product_ids = array();

		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				$product_id = absint( $this->prop( $item, 'post_id' ) );
				if ( 0 !== $product_id ) {
					$product_ids[] = $product_id;
				}
			}
		}

		if ( ! empty( $product_ids ) ) {
			return array_values( array_unique( $product_ids ) );
		}

		return $this->query_order_product_ids( absint( $this->prop( $order, 'id' ) ) );
	}

	/**
	 * Read line-item product IDs straight from FluentCart's table.
	 *
	 * @param int $order_id
	 *
	 * @return int[]
	 */
	protected function query_order_product_ids( $order_id ) {

		if ( empty( $order_id ) ) {
			return array();
		}

		global $wpdb;

		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->prefix}fct_order_items WHERE order_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$order_id
			)
		);

		return array_values( array_unique( array_map( 'absint', (array) $rows ) ) );
	}

	/**
	 * Does a subscription's product match the selection?
	 *
	 * @param object $subscription
	 * @param string $selected_product
	 * @param string $selected_category
	 *
	 * @return bool
	 */
	public function subscription_matches_product( $subscription, $selected_product, $selected_category ) {

		$product_id = absint( $this->prop( $subscription, 'product_id' ) );

		if ( self::ANY !== (string) $selected_product ) {
			return absint( $selected_product ) === $product_id;
		}

		if ( self::ANY !== (string) $selected_category && '' !== (string) $selected_category ) {
			return $product_id && has_term( absint( $selected_category ), self::PRODUCT_TAXONOMY, $product_id );
		}

		return true;
	}

	/**
	 * Format a cents amount (BIGINT, cast to double by FluentCart) for display.
	 *
	 * @param mixed $cents
	 *
	 * @return string
	 */
	public function format_amount( $cents ) {
		return number_format( (float) $cents / 100, 2, '.', '' );
	}

	/* ------------------------------------------------------------------ *
	 * Token definitions + hydration
	 *
	 * Triggers reach the token groups through the helper rather than
	 * constructing Fluent_Cart_Tokens themselves, so the whole surface a
	 * recipe part touches stays behind $this->item_helpers — the same seam
	 * Pro composes over.
	 * ------------------------------------------------------------------ */

	/**
	 * The shared token group instance.
	 *
	 * @return Fluent_Cart_Tokens
	 */
	private function tokens() {

		if ( null === $this->tokens ) {
			$this->tokens = new Fluent_Cart_Tokens();
		}

		return $this->tokens;
	}

	/**
	 * Customer token definitions.
	 *
	 * @return array
	 */
	public function customer_tokens() {
		return $this->tokens()->customer_tokens();
	}

	/**
	 * Hydrate the customer tokens from a customer model or array.
	 *
	 * @param mixed $customer
	 *
	 * @return array
	 */
	public function hydrate_customer_tokens( $customer ) {
		return $this->tokens()->hydrate_customer_tokens( $customer );
	}

	/**
	 * Order token definitions.
	 *
	 * @return array
	 */
	public function order_tokens() {
		return $this->tokens()->order_tokens();
	}

	/**
	 * Hydrate the order tokens from an order model or array.
	 *
	 * @param mixed $order
	 *
	 * @return array
	 */
	public function hydrate_order_tokens( $order ) {
		return $this->tokens()->hydrate_order_tokens( $order );
	}

	/**
	 * Subscription token definitions.
	 *
	 * @return array
	 */
	public function subscription_tokens() {
		return $this->tokens()->subscription_tokens();
	}

	/**
	 * Hydrate the subscription tokens from a subscription model or array.
	 *
	 * @param mixed $subscription
	 *
	 * @return array
	 */
	public function hydrate_subscription_tokens( $subscription ) {
		return $this->tokens()->hydrate_subscription_tokens( $subscription );
	}
}
