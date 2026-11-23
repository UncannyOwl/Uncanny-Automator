<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Integration_Catalog\Services;

use Uncanny_Automator\App\Bridge\Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Item_Availability_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Trigger_Definition_Bridge;
use Uncanny_Automator\App\Bridge\Item_Availability_Bridge;
use Uncanny_Automator\App\Bridge\Trigger_Definition_Bridge;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Block_Modifier_Types;
use Uncanny_Automator\App\Integration_Catalog\Integration\Enums\Integration_Item_Types;
use Uncanny_Automator\App\Recipe_Builder\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\App\Recipe_Builder\Condition\Registry\WP_Action_Condition_Registry;
use Uncanny_Automator\App\Recipe_Builder\Condition\Services\Condition_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Loop\Filter\Registry\WP_Registry;
use Uncanny_Automator\App\Recipe_Builder\Loop\Services\Filter\Filter_Registry_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Registry\WP_Trigger_Registry;

/**
 * The single reader of the items whose requirements are not met, and of the requirements the registration
 * pass stored with an item, for triggers, actions, loop filters and conditions; and so of whether the site has
 * an item's code at all.
 *
 * Every lookup carries the item type, the integration code and the item code, so a code two integrations
 * share never answers for the wrong one, and a new item type joins without a new method.
 *
 * The registered conditions and loop filters are read through their registry services, resolved on each read:
 * those are the request's shared registries, so each list is loaded once.
 *
 * @since 8.1.0
 */
final class Item_Availability_Provider {

	/**
	 * Singleton instance.
	 *
	 * @since 8.1.0
	 *
	 * @var Item_Availability_Provider|null
	 */
	private static ?Item_Availability_Provider $instance = null;

	/**
	 * Reads the unavailable list.
	 *
	 * @since 8.1.0
	 *
	 * @var Item_Availability_Bridge
	 */
	private Item_Availability_Bridge $bridge;

	/**
	 * Reads the registered trigger definitions.
	 *
	 * @since 8.1.0
	 *
	 * @var Trigger_Definition_Bridge
	 */
	private Trigger_Definition_Bridge $triggers;

	/**
	 * Reads the registered action definitions.
	 *
	 * @since 8.1.0
	 *
	 * @var Action_Definition_Bridge
	 */
	private Action_Definition_Bridge $actions;

	/**
	 * Converts a blocked trigger's definition into the row a registered trigger has, or refuses it.
	 *
	 * @since 8.1.0
	 *
	 * @var WP_Trigger_Registry
	 */
	private WP_Trigger_Registry $trigger_registry;

	/**
	 * Converts a blocked action's definition into the row a registered action has.
	 *
	 * @since 8.1.0
	 *
	 * @var WP_Action_Registry
	 */
	private WP_Action_Registry $action_registry;

	/**
	 * Converts a blocked condition's definition into the row a registered condition has.
	 *
	 * @since 8.1.0
	 *
	 * @var WP_Action_Condition_Registry
	 */
	private WP_Action_Condition_Registry $condition_registry;

	/**
	 * Converts a blocked loop filter's definition into the row a registered loop filter has.
	 *
	 * @since 8.1.0
	 *
	 * @var WP_Registry
	 */
	private WP_Registry $loop_filter_registry;

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @since 8.1.0
	 *
	 * @param Item_Availability_Bridge|null  $bridge   The unavailable list reader.
	 * @param Trigger_Definition_Bridge|null $triggers The registered trigger definitions reader.
	 * @param Action_Definition_Bridge|null  $actions  The registered action definitions reader.
	 */
	private function __construct( ?Item_Availability_Bridge $bridge = null, ?Trigger_Definition_Bridge $triggers = null, ?Action_Definition_Bridge $actions = null ) {
		$this->bridge               = $bridge ?? new Automator_Item_Availability_Bridge();
		$this->triggers             = $triggers ?? new Automator_Trigger_Definition_Bridge();
		$this->actions              = $actions ?? new Automator_Action_Definition_Bridge();
		$this->trigger_registry     = new WP_Trigger_Registry( $this->triggers );
		$this->action_registry      = new WP_Action_Registry( null, $this->actions );
		$this->condition_registry   = new WP_Action_Condition_Registry();
		$this->loop_filter_registry = new WP_Registry();
	}

	/**
	 * Singleton accessor.
	 *
	 * @since 8.1.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		return self::$instance ??= new self();
	}

	/**
	 * Test seam — fresh instance with injected bridges.
	 *
	 * @since 8.1.0
	 *
	 * @param Item_Availability_Bridge|null  $bridge   The unavailable list reader.
	 * @param Trigger_Definition_Bridge|null $triggers The registered trigger definitions reader.
	 * @param Action_Definition_Bridge|null  $actions  The registered action definitions reader.
	 *
	 * @return self
	 */
	public static function create_with_dependencies( ?Item_Availability_Bridge $bridge = null, ?Trigger_Definition_Bridge $triggers = null, ?Action_Definition_Bridge $actions = null ): self {
		return new self( $bridge, $triggers, $actions );
	}

	/**
	 * The requirements stored with an item in the registration pass: on the unavailable list when they are not met,
	 * in the registered definition when they are. A definition registered under another integration does not answer.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type.
	 * @param string $integration_code The integration code, any case.
	 * @param string $code             The item code.
	 *
	 * @return array|null The requirements; null when the item is stored on neither list under this integration, or stored none.
	 */
	public function requirements( string $type, string $integration_code, string $code ): ?array {
		$definition = $this->unavailable_definition( $type, $integration_code, $code )
			?? $this->registered_definition( $type, $integration_code, $code );

		return is_array( $definition['requirements'] ?? null ) ? $definition['requirements'] : null;
	}

	/**
	 * Whether this site has the item's code: registered under this integration, or stored as unavailable. True for
	 * an item that stored no requirements (a legacy one), so the site's own answer for it is never replaced.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type.
	 * @param string $integration_code The integration code, any case.
	 * @param string $code             The item code.
	 *
	 * @return bool False for an item only the feed lists.
	 */
	public function is_on_site( string $type, string $integration_code, string $code ): bool {
		return null !== $this->unavailable_definition( $type, $integration_code, $code )
			|| null !== $this->registered_definition( $type, $integration_code, $code );
	}

	/**
	 * The full stored definition of an item whose requirements are not met.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type.
	 * @param string $integration_code The integration code, any case.
	 * @param string $code             The item code, any case.
	 *
	 * @return array|null The definition it would register, with `requirements` as stored; null when it is not stored as unavailable.
	 */
	public function unavailable_definition( string $type, string $integration_code, string $code ): ?array {
		$this->run_registration( $type, $integration_code );

		return $this->bridge->get_unavailable_item( $type, $integration_code, $code );
	}

	/**
	 * The items whose requirements are not met that the catalog lists, each as the row its registry gives a
	 * registered item: those whose stored requirements declare a plugin, a required integration or a setup
	 * step, and whose definition the registry accepts. The one membership rule: discovery lists these rows,
	 * and the map and the discovery fingerprint count their codes.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type.
	 * @param string $integration_code The integration code, any case.
	 *
	 * @return array<string, array> Map of item code, as the definition carries it => registered-shape row.
	 */
	public function listed_unavailable( string $type, string $integration_code ): array {
		$this->run_registration( $type, $integration_code );

		$listed = array();

		foreach ( $this->bridge->get_unavailable_items( $type, $integration_code ) as $definition ) {
			$row = self::declares_something( is_array( $definition ) && is_array( $definition['requirements'] ?? null ) ? $definition['requirements'] : null ) ? $this->registered_row( $type, $integration_code, $definition ) : null;
			if ( null !== $row ) {
				$listed[ (string) ( $definition['code'] ?? '' ) ] = $row;
			}
		}

		return $listed;
	}

	/**
	 * The codes of the items listed_unavailable() lists, for the readers that compare codes only.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type.
	 * @param string $integration_code The integration code, any case.
	 *
	 * @return string[] Item codes as the definitions carry them.
	 */
	public function listed_unavailable_codes( string $type, string $integration_code ): array {
		return array_map( 'strval', array_keys( $this->listed_unavailable( $type, $integration_code ) ) );
	}

	/**
	 * Make sure a type's registration pass has run before its unavailable list is read. Conditions register, and
	 * store a blocked one, only when their list is first read (`automator_pro_actions_conditions_list`); reading it
	 * through the shared registry runs that once per request. The other types register while their integration loads.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type.
	 * @param string $integration_code The integration code.
	 *
	 * @return void
	 */
	private function run_registration( string $type, string $integration_code ): void {
		if ( Block_Modifier_Types::FILTER_CONDITION === $type ) {
			Condition_Registry_Service::instance()->get_conditions_by_integration( $integration_code );
		}
	}

	/**
	 * A registered item's definition, when it registered under this integration.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type.
	 * @param string $integration_code The integration code, any case.
	 * @param string $code             The item code, exact case.
	 *
	 * @return array|null Null when it is not registered under this integration, or for an unknown type.
	 */
	private function registered_definition( string $type, string $integration_code, string $code ): ?array {
		switch ( $type ) {
			case Integration_Item_Types::TRIGGER:
				$definition = $this->triggers->get_trigger_definition( $code );
				break;
			case Integration_Item_Types::ACTION:
				$definition = $this->actions->get_action_definition( $code );
				break;
			case Block_Modifier_Types::LOOP_FILTER:
				$definition = Filter_Registry_Service::instance()->get_filter_definition( $code );
				break;
			case Block_Modifier_Types::FILTER_CONDITION:
				// The conditions list is keyed by integration, so the definition is this integration's.
				return Condition_Registry_Service::instance()->get_conditions_by_integration( $integration_code )[ $code ] ?? null;
			default:
				return null;
		}

		return null !== $definition && strtoupper( (string) ( $definition['integration'] ?? '' ) ) === strtoupper( $integration_code ) ? $definition : null;
	}

	/**
	 * A blocked item's definition as the row its registry gives a registered one.
	 *
	 * @since 8.1.0
	 *
	 * @param string $type             The item type.
	 * @param string $integration_code The integration code.
	 * @param array  $definition       The definition it would register.
	 *
	 * @return array|null Null when the registry refuses the definition, or for an unknown type.
	 */
	private function registered_row( string $type, string $integration_code, array $definition ): ?array {
		switch ( $type ) {
			case Integration_Item_Types::TRIGGER:
				return $this->trigger_registry->to_registered_definition( $definition );
			case Integration_Item_Types::ACTION:
				return $this->action_registry->normalize_action_definition( $definition );
			case Block_Modifier_Types::FILTER_CONDITION:
				return $this->condition_registry->to_listed_condition( $integration_code, (string) ( $definition['code'] ?? '' ), $definition );
			case Block_Modifier_Types::LOOP_FILTER:
				return $this->loop_filter_registry->normalize_filter_definition( $definition );
			default:
				return null;
		}
	}

	/**
	 * Whether stored requirements declare a plugin, a required integration or a setup step: the membership rule
	 * listed_unavailable() applies, and the rule that tells a listed blocked item from one that declares nothing.
	 *
	 * @since 8.1.0
	 *
	 * @param array|null $requirements Stored requirements; null when there are none.
	 *
	 * @return bool
	 */
	public static function declares_something( ?array $requirements ): bool {
		$requirements = (array) $requirements;
		$details      = (array) ( $requirements['plugin_details'] ?? array() );

		return '' !== (string) ( $details['plugin_file'] ?? '' )
			|| '' !== (string) ( $details['integration_required'] ?? '' )
			|| ! empty( $requirements['setup'] );
	}
}
