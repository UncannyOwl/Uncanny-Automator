<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Recipe_Runner\Services;

use Uncanny_Automator\App\Bridge\Automator_Process_User_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Recipe_Object_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Sentence_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Token_Parse_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Trigger_Log_Bridge;
use Uncanny_Automator\App\Bridge\Process_User_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Object_Bridge;
use Uncanny_Automator\App\Bridge\Sentence_Bridge;
use Uncanny_Automator\App\Bridge\Token_Parse_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Log_Bridge;

/**
 * Recipe data provider — pipeline-facing wrapper around recipe data,
 * token parsing, sentence retrieval, trigger-log lookups, and trigger
 * meta read/write.
 *
 * Every legacy `Automator()->*` access is funneled through a bridge
 * interface from `src/app/bridge/`. This class itself stays a thin
 * pipeline service; the bridges are the only seam allowed to talk to the
 * legacy global.
 *
 * @package Uncanny_Automator\App\Recipe_Runner\Services
 * @since   7.3
 */
class Recipe_Data_Provider {

	private Recipe_Object_Bridge $recipes;
	private Token_Parse_Bridge $parser;
	private Sentence_Bridge $sentences;
	private Trigger_Log_Bridge $trigger_logs;
	private Process_User_Bridge $process_user;

	/**
	 * @param Recipe_Object_Bridge|null $recipes      Bridge override (tests).
	 * @param Token_Parse_Bridge|null   $parser       Bridge override (tests).
	 * @param Sentence_Bridge|null      $sentences    Bridge override (tests).
	 * @param Trigger_Log_Bridge|null   $trigger_logs Bridge override (tests).
	 * @param Process_User_Bridge|null  $process_user Bridge override (tests).
	 */
	public function __construct(
		?Recipe_Object_Bridge $recipes = null,
		?Token_Parse_Bridge $parser = null,
		?Sentence_Bridge $sentences = null,
		?Trigger_Log_Bridge $trigger_logs = null,
		?Process_User_Bridge $process_user = null
	) {
		$this->recipes       = $recipes ?? new Automator_Recipe_Object_Bridge();
		$this->parser        = $parser ?? new Automator_Token_Parse_Bridge();
		$this->sentences     = $sentences ?? new Automator_Sentence_Bridge();
		$this->trigger_logs  = $trigger_logs ?? new Automator_Trigger_Log_Bridge();
		$this->process_user  = $process_user ?? new Automator_Process_User_Bridge();
	}

	/**
	 * @param string $type      Post type (AUTOMATOR_POST_TYPE_TRIGGER, etc.).
	 * @param int    $recipe_id The recipe ID.
	 *
	 * @return array
	 */
	public function get_recipe_data( string $type, int $recipe_id ): array {
		return $this->recipes->get_recipe_data_by_type( $type, $recipe_id );
	}

	/**
	 * @param int $recipe_id The recipe ID.
	 *
	 * @return string The recipe type (user, anonymous, etc.).
	 */
	public function get_recipe_type( int $recipe_id ): string {
		return $this->recipes->get_recipe_type( $recipe_id );
	}

	/**
	 * Get the trigger code from post meta.
	 *
	 * @param int $trigger_id The trigger post ID.
	 *
	 * @return string The trigger code, or empty string.
	 */
	public function get_trigger_code( int $trigger_id ): string {
		return (string) get_post_meta( $trigger_id, 'code', true );
	}

	/**
	 * Get the trigger logic setting for a recipe (all/any).
	 *
	 * @param int $recipe_id The recipe post ID.
	 *
	 * @return string The trigger logic value ('any' or empty for 'all').
	 */
	public function get_trigger_logic( int $recipe_id ): string {
		return (string) get_post_meta( $recipe_id, 'automator_trigger_logic', true );
	}

	/**
	 * Get the user selector fields meta for a recipe.
	 *
	 * @param int $recipe_id The recipe post ID.
	 *
	 * @return string The fields value (serialized or empty).
	 */
	public function get_recipe_user_selector_fields( int $recipe_id ): string {
		return (string) get_post_meta( $recipe_id, 'fields', true );
	}

	/**
	 * Get the user selector source meta for a recipe.
	 *
	 * @param int $recipe_id The recipe post ID.
	 *
	 * @return string The source value or empty.
	 */
	public function get_recipe_user_selector_source( int $recipe_id ): string {
		return (string) get_post_meta( $recipe_id, 'source', true );
	}

	/**
	 * @param string $text      Text with token placeholders.
	 * @param int    $recipe_id The recipe ID.
	 * @param int    $user_id   The user ID.
	 * @param array  $args      Trigger args.
	 *
	 * @return string Parsed text.
	 */
	public function parse_text( string $text, int $recipe_id, int $user_id, array $args ): string {
		return $this->parser->parse_tokens( $text, $recipe_id, $user_id, $args );
	}

	/**
	 * @param int $action_id The action post ID.
	 *
	 * @return array Sentence meta.
	 */
	public function get_action_sentence( int $action_id ): array {
		return $this->sentences->get_action_sentence( $action_id );
	}

	/**
	 * @param int    $trigger_id The trigger post ID.
	 * @param string $type       Sentence type (trigger_detail, sentence_human_readable).
	 *
	 * @return mixed
	 */
	public function get_trigger_sentence( int $trigger_id, string $type ) {
		return $this->sentences->get_trigger_sentence( $trigger_id, $type );
	}

	/**
	 * @param int $recipe_id      The recipe ID.
	 * @param int $completed_count The completed count.
	 *
	 * @return bool
	 */
	public function recipe_number_times_completed( int $recipe_id, int $completed_count ): bool {

		$threshold = new \Uncanny_Automator\Services\Recipe\Process\User_Run_Number_Threshold( $this->field_manager() );

		$threshold->set_recipe_id( $recipe_id );
		$threshold->set_completed_times( $completed_count );

		return $threshold->has_run_times_reached_limit();
	}

	/**
	 * Lazy-loaded Field_Manager instance (stateless, safe to reuse).
	 *
	 * @return \Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Manager
	 */
	private function field_manager(): \Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Manager {
		static $instance = null;
		$instance = $instance ?? new \Uncanny_Automator\Services\Recipe\Builder\Settings\Fields\Field_Manager(
			new \Uncanny_Automator\Services\Recipe\Builder\Settings\Repository\Settings_Repository()
		);
		return $instance;
	}

	/**
	 * @param string   $trigger_code The trigger code.
	 * @param int|null $recipe_id    Optional recipe ID to filter by.
	 *
	 * @return array
	 */
	public function get_recipes_from_trigger_code( string $trigger_code, ?int $recipe_id = null ): array {
		return $this->recipes->get_recipes_for_trigger_code( $trigger_code, $recipe_id );
	}

	/**
	 * @param int      $user_id        The user ID.
	 * @param int      $trigger_id     The trigger ID.
	 * @param int      $recipe_id      The recipe ID.
	 * @param int|null $recipe_log_id  The recipe log ID.
	 *
	 * @return int|null The trigger log ID.
	 */
	public function get_trigger_log_id( int $user_id, int $trigger_id, int $recipe_id, $recipe_log_id ): ?int {
		return $this->trigger_logs->get_trigger_log_id( $user_id, $trigger_id, $recipe_id, $recipe_log_id );
	}

	// ── Trigger_Numtimes internals ──

	/**
	 * @param int $trigger_id     The trigger ID.
	 * @param int $trigger_log_id The trigger log ID.
	 * @param int $user_id        The user ID.
	 *
	 * @return int
	 */
	public function get_trigger_run_number( int $trigger_id, int $trigger_log_id, int $user_id ): int {
		return $this->trigger_logs->get_trigger_run_number( $trigger_id, $trigger_log_id, $user_id );
	}

	/**
	 * @param int    $user_id        The user ID.
	 * @param int    $trigger_id     The trigger ID.
	 * @param string $meta_key       The meta key.
	 * @param int    $trigger_log_id The trigger log ID.
	 *
	 * @return mixed
	 */
	public function get_trigger_meta( int $user_id, int $trigger_id, string $meta_key, int $trigger_log_id ) {
		return $this->process_user->get_trigger_meta( $user_id, $trigger_id, $meta_key, $trigger_log_id );
	}

	/**
	 * @param int    $run_number     The run number.
	 * @param int    $trigger_id     The trigger ID.
	 * @param int    $trigger_log_id The trigger log ID.
	 * @param string $meta_key       The meta key.
	 * @param int    $user_id        The user ID.
	 *
	 * @return mixed
	 */
	public function maybe_get_meta_id_from_trigger_log( int $run_number, int $trigger_id, int $trigger_log_id, string $meta_key, int $user_id ): ?int {
		return $this->trigger_logs->find_trigger_log_meta_id( $run_number, $trigger_id, $trigger_log_id, $meta_key, $user_id );
	}

	/**
	 * @param int    $user_id        The user ID.
	 * @param int    $trigger_id     The trigger ID.
	 * @param string $meta_key       The meta key.
	 * @param mixed  $meta_value     The meta value.
	 * @param int    $trigger_log_id The trigger log ID.
	 *
	 * @return mixed
	 */
	public function update_trigger_meta( int $user_id, int $trigger_id, string $meta_key, $meta_value, int $trigger_log_id ): bool {
		return $this->process_user->update_trigger_meta( $user_id, $trigger_id, $meta_key, $meta_value, $trigger_log_id );
	}
}
