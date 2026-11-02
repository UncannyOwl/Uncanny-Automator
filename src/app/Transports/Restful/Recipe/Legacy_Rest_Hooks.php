<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe;

use InvalidArgumentException;
use Uncanny_Automator\App\Events\Dispatcher;

/**
 * The hooks the deleted legacy REST API (`Recipe_Post_Rest_Api`, v7.7.1) fired,
 * kept as deprecation notices on the 8.0.0 builder routes that do the same job.
 *
 * This is the only recipe-hook surface a transport fires. Each notice names the
 * replacement, `automator_event_recipe_changed`, and says how to match the case
 * and where to read the data, so a third-party listener can move over from the
 * notice alone. WordPress
 * shows a notice only to a site that listens to the hook.
 *
 * @since 8.0.0
 */
final class Legacy_Rest_Hooks {

	/**
	 * The release that retired the legacy REST API.
	 */
	const VERSION = '8.0.0';

	/**
	 * The notice table, translated on first use and kept for the request.
	 *
	 * @var array<string, array{replacement: string, message: string}>|null
	 */
	private static $notices = null;

	/**
	 * Fire a legacy action as a deprecation notice.
	 *
	 * @param string $hook Legacy hook name; must be in `notices()`.
	 * @param array  $args Arguments, in the legacy order.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the hook is not a legacy REST API hook.
	 */
	public static function action( string $hook, array $args ): void {
		$notice = self::notice( $hook );

		Dispatcher::action_deprecated( $hook, $args, self::VERSION, $notice['replacement'], $notice['message'] );
	}

	/**
	 * Apply a legacy filter as a deprecation notice.
	 *
	 * @param string $hook Legacy filter name; must be in `notices()`.
	 * @param array  $args Arguments, in the legacy order; the first is the filtered value.
	 *
	 * @return mixed The filtered value.
	 *
	 * @throws InvalidArgumentException When the hook is not a legacy REST API hook.
	 */
	public static function filter( string $hook, array $args ) {
		$notice = self::notice( $hook );

		return Dispatcher::filter_deprecated( $hook, $args, self::VERSION, $notice['replacement'], $notice['message'] );
	}

	/**
	 * Whether a site listens to any of these legacy hooks.
	 *
	 * A route whose arguments cost reads builds them only when this is true:
	 * `do_action_deprecated()` skips a hook nobody listens to, but not the reads
	 * that built its arguments.
	 *
	 * @param string ...$hooks Legacy hook names.
	 *
	 * @return bool
	 */
	public static function is_heard( string ...$hooks ): bool {
		foreach ( $hooks as $hook ) {
			if ( has_filter( $hook ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Every legacy REST API hook with its replacement and message.
	 *
	 * @return array<string, array{replacement: string, message: string}>
	 */
	public static function notices(): array {
		if ( null === self::$notices ) {
			self::$notices = self::build_notices();
		}

		return self::$notices;
	}

	/**
	 * Translate the notice table.
	 *
	 * @return array<string, array{replacement: string, message: string}>
	 */
	private static function build_notices(): array {
		// phpcs:disable Uncanny_Automator.Strings.AutoContextTranslation.MissingContext, Uncanny_Automator.Strings.TranslationFunction.UnescapedTranslation -- Plain-text deprecation notices: WordPress passes them through wp_kses() before display, and HTML-escaping them would print entities instead of quotes in debug.log.
		$event         = 'automator_event_recipe_changed';
		$read_recipe   = static function ( string $key ): string {
			return sprintf(
				/* translators: %s A key of the recipe automator_get_recipe() returns. */
				_x( "Match operation() 'updated' and item_type() 'recipe', then read automator_get_recipe( recipe_id() )['recipe']['%s']. The event does not say which value changed.", 'Legacy REST hook notice', 'uncanny-automator' ),
				$key
			);
		};
		$read_limit    = static function ( string $key ): string {
			return sprintf(
				/* translators: %s A run-limit key of the recipe automator_get_recipe() returns. */
				_x( "Match operation() 'updated' and item_type() 'recipe', then read automator_get_recipe( recipe_id() )['recipe']['%s']; null means unlimited, where this hook passed -1. The event does not say which value changed.", 'Legacy REST hook notice', 'uncanny-automator' ),
				$key
			);
		};
		$before_update = _x( "8.0.0 has no supported before-save hook. To change a value a trigger or action stores, match operation() 'created' or 'updated' and item_type() 'trigger' or 'action', then update the stored value with update_post_meta( item_id(), \$key, wp_slash( \$value ) ). The event fires for builder, MCP and automator_*() saves alike.", 'Legacy REST hook notice', 'uncanny-automator' );

		return array(
			'automator_recipe_trigger_created'             => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'created' and item_type() 'trigger', or listen to automator_event_trigger_created; item_id() is the new trigger and recipe_id() its recipe. Both fire for builder, MCP and automator_*() saves alike, and automator_event_trigger_created also fires for each trigger a recipe copy or import adds.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_action_created'              => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'created' and item_type() 'action', or listen to automator_event_action_created; item_id() is the new action and recipe_id() its recipe. Both fire for builder, MCP and automator_*() saves alike, and automator_event_action_created also fires for each action a recipe copy or import adds.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_before_options_update'       => array(
				'replacement' => $event,
				'message'     => $before_update,
			),
			'automator_recipe_before_update'               => array(
				'replacement' => $event,
				'message'     => $before_update,
			),
			'automator_field_values_before_save'           => array(
				'replacement' => $event,
				'message'     => _x( "8.0.0 has no supported before-save hook. To change a value a trigger or action stores, match operation() 'created' or 'updated' and item_type() 'trigger' or 'action', then update the stored value with update_post_meta( item_id(), \$key, wp_slash( \$value ) ). This filter runs for builder saves only; its return value is still stored.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_option_updated_before_cache_is_cleared' => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'updated' and item_type() 'trigger' or 'action'. The event fires after the recipe's cache is cleared, so a listener reads the saved values.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_option_updated'              => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'updated' and item_type() 'trigger' or 'action', then read the saved fields from automator_get_trigger( item_id() )['trigger']['configuration'] or automator_get_action_by_id( item_id() )['action']['config']. To act only when particular fields changed, listen to automator_event_trigger_fields_changed or automator_event_action_fields_changed, whose fields() lists them. This hook fires once per builder save: \$meta_key is the item's main option code and \$meta_value holds every field that save stored.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_option_updated'                     => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'updated' and item_type() 'trigger' or 'action', then read the saved fields from automator_get_trigger( item_id() )['trigger']['configuration'] or automator_get_action_by_id( item_id() )['action']['config']. To act only when particular fields changed, listen to automator_event_trigger_fields_changed or automator_event_action_fields_changed, whose fields() lists them. The events fire for builder, MCP and automator_*() saves alike. This filter's return value is no longer used.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_item_deleted'                => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'deleted' and item_type() 'trigger' or 'action'; item_id() is the deleted item and recipe_id() its recipe. The event also reports deleted blocks, block modifiers and recipes.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_status_updated'              => array(
				'replacement' => $event,
				'message'     => _x( "Listen to automator_event_recipe_status_changed, automator_event_trigger_status_changed or automator_event_action_status_changed: old_status() and new_status() give the change. automator_event_recipe_status_changed also fires when removing or switching off a recipe's last live trigger or action sets the recipe to draft; automator_event_recipe_changed reports that save as a trigger or action change.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_type_updated'                => array(
				'replacement' => $event,
				'message'     => $read_recipe( 'type' ),
			),
			'automator_recipe_title_updated'               => array(
				'replacement' => $event,
				'message'     => $read_recipe( 'title' ),
			),
			'automator_recipe_notes_updated'               => array(
				'replacement' => $event,
				'message'     => $read_recipe( 'notes' ),
			),
			'automator_recipe_completions_allowed_updated' => array(
				'replacement' => $event,
				'message'     => $read_limit( 'times_per_user' ),
			),
			'automator_recipe_max_completions_allowed_updated' => array(
				'replacement' => $event,
				'message'     => $read_limit( 'total_times' ),
			),
			'automator_recipe_terms_updated'               => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'updated' and item_type() 'recipe', then read the terms with wp_get_post_terms( recipe_id(), 'recipe_category' ) or 'recipe_tag'. The event does not say which value changed.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_user_selector_updated'       => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'updated' and item_type() 'recipe', then read the run-as settings with automator_get_recipe_user_selector( recipe_id() ), which returns null when none are set. The event does not say which value changed.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_actions_order_updated'       => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'updated' and item_type() 'recipe', then read the order from automator_get_recipe_wire_service(), whose get_wire_array( recipe_id() )['flow']['content'] lists the actions and blocks in order. The event does not say which value changed.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
			'automator_recipe_action_duplicated'           => array(
				'replacement' => $event,
				'message'     => _x( "Match operation() 'created' and item_type() 'action', or listen to automator_event_action_created; item_id() is the copy and recipe_id() its recipe. Both also fire for an action added any other way.", 'Legacy REST hook notice', 'uncanny-automator' ),
			),
		);
		// phpcs:enable
	}

	/**
	 * One hook's notice.
	 *
	 * @param string $hook Legacy hook name.
	 *
	 * @return array{replacement: string, message: string}
	 *
	 * @throws InvalidArgumentException When the hook is not a legacy REST API hook.
	 */
	private static function notice( string $hook ): array {
		$notices = self::notices();
		if ( ! isset( $notices[ $hook ] ) ) {
			throw new InvalidArgumentException( sprintf( '"%s" is not a hook the legacy REST API fired.', esc_html( $hook ) ) );
		}

		return $notices[ $hook ];
	}
}
