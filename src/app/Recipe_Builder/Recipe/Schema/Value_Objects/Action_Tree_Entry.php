<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Schema\Value_Objects;

use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Recipe_Builder\Action\Action;
use Uncanny_Automator\App\Recipe_Builder\Action\Enums\Action_Status;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Token_Dependency_Extractor;
use WP_Post;

/**
 * Tree-entry projection for an action item.
 *
 * Adds two action-specific keys:
 *
 *  - `depends_on` — upstream action ids referenced by `{{ACTION_FIELD:N:...}}` /
 *    `{{ACTION_META:N:...}}` tokens in the action's field values. Scanned
 *    via {@see Token_Dependency_Extractor::from_fields_bag()}; the action's
 *    own id is excluded.
 *  - `is_async_app_action` — true when the live action class declared
 *    `set_async_app_action(true)` via `Abstract_App_Action`. Resolved
 *    through {@see Integration_Registry_Bridge::get_action_execution_callback()}
 *    (Rule 1).
 */
final class Action_Tree_Entry extends Item_Tree_Entry {

	/**
	 * Ids this action reads from, so the runner knows what must complete first.
	 *
	 * @var array
	 */
	private array $depends_on;

	/**
	 * Whether the action completes on a later request rather than inline, which changes how the runner waits on it.
	 *
	 * @var bool
	 */
	private bool $is_async_app_action;

	/**
	 * Private by design: build through the named constructors below so every
	 * instance is fully projected before it is handed out.
	 *
	 */
	private function __construct() {}

	/**
	 * Project an action aggregate and its post row into a tree entry.
	 *
	 * The token extractor scans the action fields for upstream references so
	 * `depends_on` is resolved at projection time rather than on read.
	 *
	 * @param Action                      $action               Hydrated action aggregate.
	 * @param WP_Post                     $post                 Backing action post.
	 * @param Token_Dependency_Extractor  $token_extractor      Token-reference scanner.
	 * @param Integration_Registry_Bridge $integration_registry Integration lookup for display data.
	 *
	 * @return self
	 */
	public static function from_aggregate(
		Action $action,
		WP_Post $post,
		Token_Dependency_Extractor $token_extractor,
		Integration_Registry_Bridge $integration_registry
	): self {
		$instance                      = new self();
		$instance->id                  = (int) $post->ID;
		$instance->kind                = 'action';
		$instance->code                = $action->get_action_code()->get_value();
		$instance->integration_code    = $action->get_action_integration_code()->get_value();
		$instance->is_item_on          = Action_Status::is_item_on( $post->post_status );
		$instance->custom_name         = $action->get_custom_name();
		$instance->fields              = $action->get_saved_fields();
		$instance->menu_order          = (int) $post->menu_order;
		$instance->presentation        = array(
			'sentence_human_readable'      => $action->get_sentence(),
			'sentence_human_readable_html' => $action->get_sentence_html(),
		);
		$instance->depends_on          = $token_extractor->from_fields_bag( $instance->fields, $instance->id );
		$instance->is_async_app_action = self::resolve_async_app_action_eligibility(
			$instance->code,
			$integration_registry
		);

		return $instance;
	}

	/**
	 * Flatten this action entry to the cached tree shape.
	 *
	 * @return array<string, mixed> Storage-ready projection.
	 */
	public function to_array(): array {
		return parent::to_array() + array(
			'depends_on'          => $this->depends_on,
			'is_async_app_action' => $this->is_async_app_action,
		);
	}

	/**
	 * Resolve whether the live action class declared `set_async_app_action(true)`.
	 * Stamped at cache-build time; cache invalidates when the recipe is
	 * saved. If the underlying integration changes its async mode after
	 * cache build, the stale value is corrected on the next save.
	 */
	private static function resolve_async_app_action_eligibility(
		string $action_code,
		Integration_Registry_Bridge $integration_registry
	): bool {
		if ( '' === $action_code ) {
			return false;
		}

		$execution_function = $integration_registry->get_action_execution_callback( $action_code );
		if ( ! is_array( $execution_function ) || ! isset( $execution_function[0] ) ) {
			return false;
		}

		$instance = $execution_function[0];
		return is_object( $instance )
			&& method_exists( $instance, 'is_async_app_action' )
			&& (bool) $instance->is_async_app_action();
	}
}
