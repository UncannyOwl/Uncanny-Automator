<?php
declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Restful\Recipe\Items\Traits\Hooks;

use WP_REST_Request;
use Uncanny_Automator\Api\Components\Integration\Enums\Integration_Item_Types;

/**
 * Trait for deprecated add operation hooks.
 *
 * Consolidates all backwards compatibility for deprecated hooks.
 * Remove this trait when dropping support for deprecated hooks.
 *
 * @since 7.0
 */
trait Deprecated_Hook_Add_Operations {

	/**
	 * Generated request for backwards compatibility.
	 *
	 * @var WP_REST_Request|null
	 */
	private ?WP_REST_Request $deprecated_add_request = null;

	/**
	 * Dispatch deprecated created hooks.
	 *
	 * @return void
	 */
	protected function dispatch_deprecated_add_hooks(): void {
		$type = $this->get_item_type();

		$args = array(
			$this->get_item_id(),
			$this->get_item_code(),
			$this->get_deprecated_add_request(),
		);

		$message = esc_html_x(
			'The REST request structure for recipe items has changed significantly and no longer matches the previous format.',
			'Restful API',
			'uncanny-automator'
		);

		switch ( $type ) {
			case Integration_Item_Types::TRIGGER:
				do_action_deprecated(
					'automator_recipe_trigger_created',
					$args,
					'7.0',
					'automator_recipe_trigger_add_complete',
					esc_html( $message )
				);
				break;

			case Integration_Item_Types::ACTION:
				do_action_deprecated(
					'automator_recipe_action_created',
					$args,
					'7.0',
					'automator_recipe_action_add_complete',
					esc_html( $message )
				);
				break;

			case Integration_Item_Types::CLOSURE:
				do_action_deprecated(
					'automator_recipe_closure_created',
					$args,
					'7.0',
					'automator_recipe_closure_add_complete',
					esc_html( $message )
				);
				break;
		}
	}

	/**
	 * Returns the generated request for backwards compatibility.
	 *
	 * @return WP_REST_Request
	 */
	private function get_deprecated_add_request(): WP_REST_Request {
		if ( null === $this->deprecated_add_request ) {
			$this->deprecated_add_request = $this->build_deprecated_add_request();
		}

		return $this->deprecated_add_request;
	}

	/**
	 * Builds the request for backwards compatibility.
	 *
	 * @return WP_REST_Request
	 */
	private function build_deprecated_add_request(): WP_REST_Request {
		$request   = $this->get_request();
		$item_type = $this->get_item_type();

		$legacy_request = new WP_REST_Request( $request->get_method(), $request->get_route() );

		$legacy_request->set_param( 'recipePostID', $this->get_recipe_id() );
		$legacy_request->set_param( 'item_code', $this->get_item_code() );
		$legacy_request->set_param( 'action', 'add-new-' . $item_type );
		$legacy_request->set_param( 'parent_id', $this->get_parent_id() ?? $this->get_recipe_id() );

		return $legacy_request;
	}
}
