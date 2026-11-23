<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Services;

use Uncanny_Automator\App\Bridge\Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Integration_Registry_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Definition_Bridge;
use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Infrastructure\Database\Interfaces\Log_List_Store;
use Uncanny_Automator\App\Recipe_Logs\Lists\Wire_Serializer\Log_List_Text;
use Uncanny_Automator\App\Recipe_Logs\Status\Log_Status_Registry;

/**
 * The options the Logs page's filters offer: `GET logs/filters`.
 *
 * Every list is built from the logs as they are now, so a filter offers only
 * what can match. Each option is `{ value, label }`, where value is what the
 * list reads take. A site that cannot filter gets every list empty.
 *
 * @since 8.1.0
 */
final class Log_Filters_Service {

	/**
	 * @var self|null
	 */
	private static $instance = null;

	/** @var Log_List_Store */
	private $store;

	/** @var Integration_Registry_Bridge */
	private $integrations;

	/** @var Trigger_Definition_Bridge */
	private $triggers;

	/** @var Action_Definition_Bridge */
	private $actions;

	/** @var Log_Filters_Availability */
	private $availability;

	/**
	 * @param Log_List_Store|null              $store        The list store.
	 * @param Integration_Registry_Bridge|null $integrations The integration catalog.
	 * @param Trigger_Definition_Bridge|null   $triggers     The trigger definitions.
	 * @param Action_Definition_Bridge|null    $actions      The action definitions.
	 * @param Log_Filters_Availability|null    $availability Whether the site can filter.
	 */
	private function __construct(
		?Log_List_Store $store = null,
		?Integration_Registry_Bridge $integrations = null,
		?Trigger_Definition_Bridge $triggers = null,
		?Action_Definition_Bridge $actions = null,
		?Log_Filters_Availability $availability = null
	) {
		$this->store        = $store ?? Database::get_log_list_store();
		$this->integrations = $integrations ?? new Automator_Integration_Registry_Bridge();
		$this->triggers     = $triggers ?? new Automator_Trigger_Definition_Bridge();
		$this->actions      = $actions ?? new Automator_Action_Definition_Bridge();
		$this->availability = $availability ?? new Log_Filters_Availability();
	}

	/**
	 * The shared instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * An instance with its dependencies given, for tests.
	 *
	 * @param Log_List_Store|null              $store        The list store.
	 * @param Integration_Registry_Bridge|null $integrations The integration catalog.
	 * @param Trigger_Definition_Bridge|null   $triggers     The trigger definitions.
	 * @param Action_Definition_Bridge|null    $actions      The action definitions.
	 * @param Log_Filters_Availability|null    $availability Whether the site can filter.
	 *
	 * @return self
	 */
	public static function create_with_dependencies(
		?Log_List_Store $store = null,
		?Integration_Registry_Bridge $integrations = null,
		?Trigger_Definition_Bridge $triggers = null,
		?Action_Definition_Bridge $actions = null,
		?Log_Filters_Availability $availability = null
	): self {
		return new self( $store, $integrations, $triggers, $actions, $availability );
	}

	/**
	 * Every filter's options, and whether the site can filter.
	 *
	 * A site that cannot filter offers no filter, so it gets every list empty
	 * and nothing is read from the logs.
	 *
	 * @return array{recipes: array, users: array, statuses: array, integrations: array, endpoints: array, triggers: array, actions: array, available: bool}
	 */
	public function get_filters(): array {
		if ( ! $this->availability->is_available() ) {
			return array(
				'recipes'      => array(),
				'users'        => array(),
				'statuses'     => array(),
				'integrations' => array(),
				'endpoints'    => array(),
				'triggers'     => array(),
				'actions'      => array(),
				'available'    => false,
			);
		}

		return array(
			'recipes'      => $this->recipes(),
			'users'        => $this->users(),
			'statuses'     => $this->statuses(),
			'integrations' => $this->integration_options(),
			'endpoints'    => $this->endpoints(),
			'triggers'     => $this->items( 'trigger' ),
			'actions'      => $this->items( 'action' ),
			'available'    => true,
		);
	}

	/**
	 * The recipes that have runs. The value is the recipe id.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function recipes(): array {
		$options = array();

		foreach ( $this->store->get_logged_recipes() as $recipe ) {
			$title = Log_List_Text::plain( $recipe['title'] );

			$options[] = array(
				'value' => (string) $recipe['id'],
				/* translators: %d: The id of a recipe that has no title. */
				'label' => '' !== $title ? $title : sprintf( esc_html_x( 'ID: %d (no title)', 'Logs filter option', 'uncanny-automator' ), $recipe['id'] ),
			);
		}

		return $options;
	}

	/**
	 * The users who have runs, guests first. The value is the user id, "0"
	 * for guests.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function users(): array {
		$options = array();

		foreach ( $this->store->get_logged_users() as $user ) {
			if ( 0 === $user['id'] ) {
				$label = esc_html_x( 'Guests', 'Logs filter option', 'uncanny-automator' );
			} elseif ( ! $user['found'] ) {
				/* translators: %d: The id of a user who was deleted. */
				$label = sprintf( esc_html_x( 'Deleted user #%d', 'Logs filter option', 'uncanny-automator' ), $user['id'] );
			} elseif ( '' !== $user['user_email'] ) {
				/* translators: 1: A user's display name. 2: Their email address. */
				$label = sprintf( esc_html_x( '%1$s (%2$s)', 'Logs filter option', 'uncanny-automator' ), Log_List_Text::plain( $user['display_name'] ), $user['user_email'] );
			} else {
				$label = Log_List_Text::plain( $user['display_name'] );
			}

			$options[] = array(
				'value' => (string) $user['id'],
				'label' => $label,
			);
		}

		return $options;
	}

	/**
	 * The statuses runs, triggers and actions carry, by label. The value is
	 * the status code.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function statuses(): array {
		$options = array();

		foreach ( $this->store->get_logged_statuses() as $status_int ) {
			$status = Log_Status_Registry::from_legacy_int( $status_int );

			// An integer no status names takes the fallback, which no filter can match.
			if ( array() === Log_Status_Registry::legacy_ints_for_code( $status->get_code() ) ) {
				continue;
			}

			$options[ $status->get_code() ] = array(
				'value' => $status->get_code(),
				'label' => $status->get_label(),
			);
		}

		return $this->by_label( array_values( $options ) );
	}

	/**
	 * The integrations that ran, by name: those of the triggers and actions
	 * that have run, and LOOP when a run held a loop. Only integrations the
	 * catalog has, the same set entries list.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function integration_options(): array {
		$logged  = $this->store->get_logged_integrations();
		$codes   = $logged['codes'];
		$catalog = $this->integrations->get_all_integrations();

		if ( $logged['has_loops'] ) {
			$codes[] = 'LOOP';
		}

		$options = array();

		foreach ( array_unique( $codes ) as $code ) {
			if ( ! isset( $catalog[ $code ] ) ) {
				continue;
			}

			$options[] = array(
				'value' => $code,
				'label' => $this->integrations->get_integration_name_by_code( $code ),
			);
		}

		return $this->by_label( $options );
	}

	/**
	 * The endpoints the failed actions called.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function endpoints(): array {
		$options = array();

		foreach ( $this->store->get_failed_endpoints() as $endpoint ) {
			$options[] = array(
				'value' => $endpoint,
				'label' => $endpoint,
			);
		}

		return $options;
	}

	/**
	 * The triggers or actions that have run, by sentence. The value is the
	 * trigger or action code; the label is its sentence in the recipe editor's
	 * list, else the code when its integration is not loaded.
	 *
	 * @param string $item_type `trigger` or `action`.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function items( string $item_type ): array {
		$options = array();

		foreach ( $this->store->get_logged_item_codes( $item_type ) as $code ) {
			$definition = 'trigger' === $item_type ? $this->triggers->get_trigger_definition( $code ) : $this->actions->get_action_definition( $code );
			$label      = Log_List_Text::plain( (string) ( $definition['select_option_name'] ?? '' ) );

			$options[] = array(
				'value' => $code,
				'label' => '' !== $label ? $label : $code,
			);
		}

		return $this->by_label( $options );
	}

	/**
	 * Options sorted by label, the way a person reads them.
	 *
	 * @param array<int, array{value: string, label: string}> $options The options.
	 *
	 * @return array<int, array{value: string, label: string}>
	 */
	private function by_label( array $options ): array {
		usort(
			$options,
			static function ( array $a, array $b ): int {
				return strnatcasecmp( $a['label'], $b['label'] );
			}
		);

		return $options;
	}
}
