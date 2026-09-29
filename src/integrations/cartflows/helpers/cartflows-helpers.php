<?php

namespace Uncanny_Automator\Integrations\Cartflows;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Cartflows_Helpers
 *
 * Shared logic for the CartFlows integration: the flow / product / step-type
 * pickers and their remote_data segments, plus the dependency gates.
 *
 * CartFlows registers `cartflows_flow` as `'public' => false` and
 * `'publicly_queryable' => false` (`Cartflows_Flow_Post_Type::flow_post_type()`)
 * to prevent unauthenticated funnel enumeration — remote_data is authenticated,
 * so flows are safe to list there, but never through a public route.
 *
 * @package Uncanny_Automator\Integrations\Cartflows
 */
class Cartflows_Helpers extends Abstract_Helpers {

	/**
	 * "Any" sentinel shared by the trigger dropdowns.
	 *
	 * @var string
	 */
	const ANY = '-1';

	/**
	 * Order meta CartFlows stamps with the funnel ID. Written on both the classic
	 * and the Blocks checkout paths, which converge on
	 * `Cartflows_Checkout_Markup::store_flow_metadata_on_order()`.
	 *
	 * @var string
	 */
	const ORDER_FLOW_META = '_wcf_flow_id';

	/**
	 * Order meta identifying the checkout step. Present on checkout orders only.
	 *
	 * @var string
	 */
	const ORDER_CHECKOUT_META = '_wcf_checkout_id';

	/**
	 * Order meta identifying the optin step. CartFlows optin steps also create
	 * WooCommerce orders and also stamp ORDER_FLOW_META
	 * (`Cartflows_Optin_Markup::save_optin_fields()`), so this key is the only
	 * reliable way to tell an optin order from a checkout order. CartFlows uses
	 * exactly this test itself
	 * (`Cartflows_Tracking::prepare_purchase_data_fb_response()`).
	 *
	 * @var string
	 */
	const ORDER_OPTIN_META = '_wcf_optin_id';

	/**
	 * Step types CartFlows Free supports.
	 *
	 * @var string[]
	 */
	const FREE_STEP_TYPES = array( 'landing', 'optin', 'checkout', 'thankyou' );

	/**
	 * Step types that require CartFlows Pro. CartFlows rejects these outright
	 * without it (`Cartflows_Ability_Runtime::create_step()`).
	 *
	 * @var string[]
	 */
	const PRO_STEP_TYPES = array( 'upsell', 'downsell' );

	/**
	 * Whether WooCommerce is active. CartFlows gates on this itself
	 * (`Cartflows_Loader::load_helper_files_components()`).
	 *
	 * @return bool
	 */
	public function woocommerce_active() {
		return function_exists( 'WC' );
	}

	/**
	 * Whether CartFlows Pro is active.
	 *
	 * @return bool
	 */
	public function cartflows_pro_active() {
		return function_exists( '_is_cartflows_pro' ) && _is_cartflows_pro();
	}

	/**
	 * All CartFlows funnels as dropdown options.
	 *
	 * @param bool $include_any Whether to prepend the "Any flow" sentinel.
	 *
	 * @return array
	 */
	public function get_flow_options( $include_any = true ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any flow', 'CartFlows', 'uncanny-automator' ),
			);
		}

		if ( ! defined( 'CARTFLOWS_FLOW_POST_TYPE' ) ) {
			return $options;
		}

		$flows = get_posts(
			array(
				'post_type'        => CARTFLOWS_FLOW_POST_TYPE,
				'post_status'      => array( 'publish', 'draft' ),
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'posts_per_page'   => apply_filters( 'automator_select_all_posts_limit', 999, CARTFLOWS_FLOW_POST_TYPE ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $flows as $flow ) {
			$options[] = array(
				'value' => (string) $flow->ID,
				'text'  => $flow->post_title,
			);
		}

		return $options;
	}

	/**
	 * Published WooCommerce products as dropdown options.
	 *
	 * @param bool $include_any Whether to prepend the "Any product" sentinel.
	 *
	 * @return array
	 */
	public function get_product_options( $include_any = true ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'value' => self::ANY,
				'text'  => esc_html_x( 'Any product', 'CartFlows', 'uncanny-automator' ),
			);
		}

		if ( ! $this->woocommerce_active() ) {
			return $options;
		}

		$products = get_posts(
			array(
				'post_type'        => array( 'product', 'product_variation' ),
				'post_status'      => 'publish',
				// phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
				'posts_per_page'   => apply_filters( 'automator_select_all_posts_limit', 999, 'product' ),
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);

		foreach ( $products as $product ) {
			$options[] = array(
				'value' => (string) $product->ID,
				'text'  => $product->post_title,
			);
		}

		return $options;
	}

	/**
	 * Step types as dropdown options. Upsell and downsell are labelled so the
	 * CartFlows Pro requirement is visible before the recipe runs.
	 *
	 * @return array
	 */
	public function get_step_type_options() {

		$labels = array(
			'landing'  => esc_html_x( 'Landing', 'CartFlows', 'uncanny-automator' ),
			'optin'    => esc_html_x( 'Optin', 'CartFlows', 'uncanny-automator' ),
			'checkout' => esc_html_x( 'Checkout', 'CartFlows', 'uncanny-automator' ),
			'thankyou' => esc_html_x( 'Thank You', 'CartFlows', 'uncanny-automator' ),
			'upsell'   => esc_html_x( 'Upsell (requires CartFlows Pro)', 'CartFlows', 'uncanny-automator' ),
			'downsell' => esc_html_x( 'Downsell (requires CartFlows Pro)', 'CartFlows', 'uncanny-automator' ),
		);

		$options = array();

		foreach ( $labels as $value => $text ) {
			$options[] = array(
				'value' => $value,
				'text'  => $text,
			);
		}

		return $options;
	}

	/**
	 * Every valid step-type slug.
	 *
	 * @return string[]
	 */
	public function get_step_types() {
		return array_merge( self::FREE_STEP_TYPES, self::PRO_STEP_TYPES );
	}

	/**
	 * Whether a step type needs CartFlows Pro.
	 *
	 * @param string $step_type The step-type slug.
	 *
	 * @return bool
	 */
	public function step_type_requires_pro( $step_type ) {
		return in_array( (string) $step_type, self::PRO_STEP_TYPES, true );
	}

	/**
	 * Attach a newly created step to its funnel.
	 *
	 * Mirrors CartFlows' own create_step() (`Cartflows_Importer::create_step()`
	 * and `Cartflows_Ability_Runtime::create_step()`): meta, both
	 * taxonomies, and — critically — the flow's own `wcf-steps` array. Without
	 * that last part the step exists but never appears inside its funnel.
	 *
	 * @param int    $step_id    The new step post ID.
	 * @param int    $flow_id    The funnel to attach it to.
	 * @param string $step_type  The step-type slug.
	 * @param string $step_title The step title.
	 *
	 * @return void
	 */
	public function attach_step_to_flow( $step_id, $flow_id, $step_type, $step_title ) {

		$step_id = absint( $step_id );
		$flow_id = absint( $flow_id );

		update_post_meta( $step_id, 'wcf-flow-id', $flow_id );
		update_post_meta( $step_id, 'wcf-step-type', $step_type );
		update_post_meta( $step_id, '_wp_page_template', 'cartflows-default' );

		wp_set_object_terms( $step_id, $step_type, CARTFLOWS_TAXONOMY_STEP_TYPE );
		wp_set_object_terms( $step_id, 'flow-' . $flow_id, CARTFLOWS_TAXONOMY_STEP_FLOW );

		$flow_steps = get_post_meta( $flow_id, 'wcf-steps', true );

		if ( ! is_array( $flow_steps ) ) {
			$flow_steps = array();
		}

		$flow_steps[] = array(
			'id'    => $step_id,
			'title' => $step_title,
			'type'  => $step_type,
		);

		// Guard the method being called, not get_instance() — that has always
		// existed; maybe_update_flow_steps() has not.
		if ( method_exists( '\Cartflows_Helper', 'maybe_update_flow_steps' ) ) {
			$flow_steps = \Cartflows_Helper::get_instance()->maybe_update_flow_steps( $flow_id, $flow_steps );
		}

		update_post_meta( $flow_id, 'wcf-steps', $flow_steps );
	}

	/**
	 * Remote_Data segment: flows with the "Any" sentinel (triggers).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_flows( $request ): array {
		return $this->remote_data_success( $this->get_flow_options( true ) );
	}

	/**
	 * Remote_Data segment: flows without the "Any" sentinel (actions).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_flows_strict( $request ): array {
		return $this->remote_data_success( $this->get_flow_options( false ) );
	}

	/**
	 * Remote_Data segment: products with the "Any" sentinel (triggers).
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_products( $request ): array {
		return $this->remote_data_success( $this->get_product_options( true ) );
	}

	/**
	 * Remote_Data segment: products without the "Any" sentinel (actions).
	 *
	 * Free owns the product picker, so both halves of the pair live here even
	 * though only Pro currently consumes the strict one — the alternative leaves
	 * a free action unable to list products whenever Pro is inactive.
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_products_strict( $request ): array {
		return $this->remote_data_success( $this->get_product_options( false ) );
	}
}
