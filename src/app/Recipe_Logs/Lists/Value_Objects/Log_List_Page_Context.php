<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Value_Objects;

/**
 * Everything one page of a list needs beyond its rows, read in batches for
 * the whole page: what each run ran, each item's integration and title, each
 * action's note, and what the current user may open or change.
 *
 * Built by the list service; read by the entry serializer, which stays a
 * pure projection.
 *
 * @since 8.1.0
 */
final class Log_List_Page_Context {

	/** @var array<int, string[]> Per run id. */
	private $run_integrations;

	/** @var array<int, string> Per item post id. */
	private $item_integrations;

	/** @var array<int, string> Per entry id. */
	private $item_titles;

	/** @var array<int, string> Per action log id. */
	private $notes;

	/** @var array<int, string> Per run id: the stored action_delays meta. */
	private $delays;

	/** @var array<int, bool> Per run id. */
	private $replays;

	/** @var array<int, array{avatar_url: string, edit_url: string}> Per user id. */
	private $users;

	/** @var array<int, array{edit_url: string, can_delete: bool}> Per recipe id. */
	private $recipes;

	/** @var array<int, bool> Per action log id. */
	private $replayable;

	/**
	 * @param array<string, array> $data The batches, keyed as the properties.
	 */
	public function __construct( array $data ) {
		$this->run_integrations  = $data['run_integrations'] ?? array();
		$this->item_integrations = $data['item_integrations'] ?? array();
		$this->item_titles       = $data['item_titles'] ?? array();
		$this->notes             = $data['notes'] ?? array();
		$this->delays            = $data['delays'] ?? array();
		$this->replays           = $data['replays'] ?? array();
		$this->users             = $data['users'] ?? array();
		$this->recipes           = $data['recipes'] ?? array();
		$this->replayable        = $data['replayable'] ?? array();
	}

	/**
	 * The integrations a run ran, each once, in the order they ran.
	 *
	 * @param int $recipe_log_id Run id.
	 *
	 * @return string[]
	 */
	public function run_integrations( int $recipe_log_id ): array {
		return $this->run_integrations[ $recipe_log_id ] ?? array();
	}

	/**
	 * An item's integration code, empty when its post has none.
	 *
	 * @param int $item_id Trigger or action post id.
	 *
	 * @return string
	 */
	public function item_integration( int $item_id ): string {
		return $this->item_integrations[ $item_id ] ?? '';
	}

	/**
	 * An entry's item title, plain text, empty when none was found.
	 *
	 * @param int $entry_id Trigger or action log id.
	 *
	 * @return string
	 */
	public function item_title( int $entry_id ): string {
		return $this->item_titles[ $entry_id ] ?? '';
	}

	/**
	 * An action's note, plain text.
	 *
	 * @param int $action_log_id Action log id.
	 *
	 * @return string
	 */
	public function note( int $action_log_id ): string {
		return $this->notes[ $action_log_id ] ?? '';
	}

	/**
	 * A run's stored action_delays meta, empty when it has none.
	 *
	 * @param int $recipe_log_id Run id.
	 *
	 * @return string
	 */
	public function delays( int $recipe_log_id ): string {
		return $this->delays[ $recipe_log_id ] ?? '';
	}

	/**
	 * Whether a run replays an earlier run.
	 *
	 * @param int $recipe_log_id Run id.
	 *
	 * @return bool
	 */
	public function is_replay( int $recipe_log_id ): bool {
		return ! empty( $this->replays[ $recipe_log_id ] );
	}

	/**
	 * A user's picture address and, when the current user may edit them,
	 * their profile address.
	 *
	 * @param int $user_id User id.
	 *
	 * @return array{avatar_url: string, edit_url: string}
	 */
	public function user( int $user_id ): array {
		return $this->users[ $user_id ] ?? array(
			'avatar_url' => '',
			'edit_url'   => '',
		);
	}

	/**
	 * A recipe's editor address, when the current user may edit it, and
	 * whether they may delete its runs.
	 *
	 * @param int $recipe_id Recipe id.
	 *
	 * @return array{edit_url: string, can_delete: bool}
	 */
	public function recipe( int $recipe_id ): array {
		return $this->recipes[ $recipe_id ] ?? array(
			'edit_url'   => '',
			'can_delete' => false,
		);
	}

	/**
	 * Whether a failed action's stored request can be replayed.
	 *
	 * @param int $action_log_id Action log id.
	 *
	 * @return bool
	 */
	public function can_replay( int $action_log_id ): bool {
		return ! empty( $this->replayable[ $action_log_id ] );
	}
}
