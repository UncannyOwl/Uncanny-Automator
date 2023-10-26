<?php

namespace Uncanny_Automator\Services\Integrations;

use Uncanny_Automator\Automator_Exception;
use Uncanny_Automator\Utilities;

/**
 * Main integration object structure.
 *
 * @since 5.0
 * @package Uncanny_Automator\Services\Integrations
 *
 */
class Structure {

	/**
	 * The integration structure itself.
	 *
	 * @var mixed $structure Defaults to null.
	 */
	public $structure = null;

	/**
	 * All active integrations.
	 *
	 * @var mixed[] $active_integrations
	 */
	private $active_integrations = array();

	/**
	 * All integrations regardless of status.
	 *
	 * @var mixed[] $all_integrations
	 */
	private $all_integrations = array();

	/**
	 * Pro item list. This one comes from auto-generated list of Pro Triggers and Actions.
	 *
	 * @var mixed[] $pro_item_list
	 */
	private $pro_item_list = array();

	/**
	 * The recipe ID.
	 *
	 * @var int $recipe_id
	 */
	protected $recipe_id = null;

	/**
	 * The recipe action conditions.
	 *
	 * @var string $recipe_action_conditions .
	 */
	protected $recipe_action_conditions = null;

	/**
	 * The registered action conditions. This is not a an official part of the integrations structure.
	 *
	 * Used as variable for fetching all action conditions 'once' and not during the restructuring.
	 *
	 * @var array $action_conditions
	 */
	private static $action_conditions = array();

	/**
	 * Sets the active_integration, all_integrations, and pro_item_list
	 *
	 * @param int $recipe_id
	 *
	 * @return Structure The restructured integrations object.
	 */
	public function __construct( $recipe_id ) {

		$this->active_integrations = Automator()->get_integrations();
		$this->pro_item_list       = Utilities::get_pro_items_list();

		$this->all_integrations = Automator()->get_all_integrations();

		// Combine both Free & Pro integrations
		if ( ! defined( 'AUTOMATOR_PRO_PLUGIN_VERSION' ) ) {
			$this->all_integrations = array_merge( $this->all_integrations, Utilities::get_pro_only_items() );
		}

		// Sort alphabetically
		ksort( $this->all_integrations );

		self::$action_conditions = (array) apply_filters( 'automator_pro_actions_conditions_list', array() );

		$this->set_recipe_id( $recipe_id );

		// Retrieve the recipe action conditions.
		$this->recipe_action_conditions = get_post_meta( $this->recipe_id, 'actions_conditions', true );

		return $this->restructure_integrations_object();
	}

	/**
	 * Sets the Recipe ID.
	 *
	 * @param int $id The ID.
	 *
	 * @return self
	 */
	public function set_recipe_id( $id ) {

		$this->recipe_id = absint( $id );

		return $this;

	}

	/**
	 * Gets the Recipe ID.
	 *
	 *
	 * @return int
	 */
	public function get_recipe_id() {

		return $this->recipe_id;

	}

	/**
	 * Determines if the specific integration is active or not.
	 *
	 * @param string $code
	 *
	 * @return bool
	 */
	private function is_integration_active( $code ) {

		return isset( $this->active_integrations[ $code ] );

	}

	/**
	 * Determines if the specific active integration has settings url and connected properties.
	 *
	 * @param string $code
	 *
	 * @return bool True if both settings url and connected properties exists. Returns false, otherwise.
	 */
	private function has_settings_and_has_connected_prop( $code ) {

		$has_settings       = ! empty( $this->active_integrations[ $code ]['settings_url'] );
		$has_connected_prop = ! empty( $this->active_integrations[ $code ]['connected'] );

		return $has_settings && $has_connected_prop;

	}

	/**
	 * Restructures the pro item list to follow the specification.
	 *
	 * @param mixed[] $pro_item_list
	 * @param string $type
	 *
	 * @return mixed[]
	 */
	private function restructure_pro_item_list( $pro_item_list, $type ) {

		$list = array();

		foreach ( $pro_item_list as $item_list ) {

			$structure = array(
				'unavailability_reason' => 'pro-item',
				'sentence_short'        => $item_list['name'],
			);

			if ( 'actions' === $type ) {
				$structure['type'] = isset( $item_list['type'] ) ? $item_list['type'] : null;
			}

			$list[] = $structure;

		}

		return $list;

	}

	/**
	 * Get the integration actions
	 *
	 * @param string $code
	 *
	 * @return mixed[] $action_items
	 */
	public function get_integration_property( $integration_code, $type ) {

		if ( 'triggers' === $type ) {
			$integration_items = Automator()->get_integration_triggers( $integration_code );
		} else {
			$integration_items = Automator()->get_integration_actions( $integration_code );
		}

		$items = array(
			'free' => array(),
			'pro'  => array(),
		);

		// Assign the list of Pro items.
		$pro_item_list = isset( $this->pro_item_list[ $integration_code ][ $type ] )
			? $this->pro_item_list[ $integration_code ][ $type ]
			: array();

		$recipe_objects = $this->get_recipe_objects();

		foreach ( $integration_items as $code => $property ) {

			$support_link = isset( $property['support_link'] ) ? $property['support_link'] : '';

			$is_pro = isset( $property['is_pro'] ) ? $property['is_pro'] : false;

			$structure = array(
				'is_pro'        => $is_pro,
				'is_deprecated' => isset( $property['is_deprecated'] ) ? $property['is_deprecated'] : false,
				'sentence'      => array(
					'short'   => $property['select_option_name'],
					'dynamic' => $property['sentence'],
				),
				'miscellaneous' => array(
					'url_support' => $support_link,
				),
			);

			if ( in_array( $code, $recipe_objects, true ) ) {

				$fields = new Fields();

				$fields->set_config(
					array(
						'code'        => $code,
						'object_type' => $type,
						'recipe_id'   => $this->recipe_id,
					)
				);

				try {
					$structure['fields'] = $fields->get();
				} catch ( Automator_Exception $e ) {
					$structure['fields'] = $e->getMessage();
				}
			}

			// Only Triggers have type.
			if ( 'triggers' === $type ) {
				$structure['type'] = $property['type'];
			}

			// Only Actions 'requires_user' attribute.
			if ( 'actions' === $type ) {
				$structure['miscellaneous']['requires_user_data'] = isset( $property['requires_user'] ) ? $property['requires_user'] : false;
			}

			// Classifies the Action as either Free or Pro.
			// If Action is not pro.
			if ( false === $is_pro ) {
				// Classify them as Free.
				$items['free'][ $code ] = $structure;
			} else {
				// Otherwise, classify as Pro.
				$items['pro'][ $code ] = $structure;
			}

			// If pro is not active.
			if ( ! defined( 'UAPro_ABSPATH' ) ) {
				// Move Free Triggers to 'available'.
				$items['available'] = isset( $items['free'] ) ? $items['free'] : false;
				// As unavailable.
				$items['unavailable'] = $this->restructure_pro_item_list( $pro_item_list, $type );
			}

			// Otherwise, if Pro is active.
			if ( defined( 'UAPro_ABSPATH' ) ) {
				// Merge both Free and Pro.
				$items['available'] = array_merge( $items['free'], $items['pro'] );
				// All items should be available, so unavailable prop should be an empty array.
				$items['unavailable'] = array();
			}
		}

		// Then unset both Free and Pro classifications because we don't need them anymore.
		unset( $items['free'] );
		unset( $items['pro'] );

		return $items;
	}

	/**
	 * @param $integration_code
	 * @param $type
	 *
	 * @return array|array[]
	 */
	public function get_pro_integration_property( $integration_code, $type ) {
		$pro_only_items_list = Utilities::get_pro_only_items_list();

		$integration_items = isset( $pro_only_items_list[ $integration_code ][ $type ] ) ? $pro_only_items_list[ $integration_code ][ $type ] : array();

		$items = array(
			'free' => array(),
			'pro'  => array(),
		);

		// Assign the list of Pro items.
		$pro_item_list = $integration_items;

		if ( empty( $pro_item_list ) ) {
			return array();
		}

		foreach ( $integration_items as $code => $property ) {

			$support_link = isset( $property['support_link'] ) ? $property['support_link'] : '';

			$property_type = isset( $property['type'] ) && 'logged-in' === $property['type'] ? 'user' : 'anonymous';

			$structure = array(
				'is_pro'        => true,
				'is_deprecated' => false,
				'sentence'      => array(
					'short'   => $property['name'],
					'dynamic' => $property['name'],
				),
				'miscellaneous' => array(
					'url_support' => $support_link,
				),
			);

			// Only Triggers have type.
			if ( 'triggers' === $type ) {
				$structure['type'] = $property_type;
			}

			// Only Actions 'requires_user' attribute.
			if ( 'actions' === $type ) {
				$structure['miscellaneous']['requires_user_data'] = 'user' === $property_type;
			}

			// Classifies the Action as either Free or Pro.
			// Otherwise, classify as Pro.
			$items['pro'][ $code ] = $structure;

			// If pro is not active.
			if ( ! defined( 'UAPro_ABSPATH' ) ) {
				// Move Free Triggers to 'available'.
				$items['available'] = false;
				// As unavailable.
				$items['unavailable'] = $this->restructure_pro_item_list( $pro_item_list, $type );
			}
		}

		// Then unset both Free and Pro classifications because we don't need them anymore.
		unset( $items['free'] );
		unset( $items['pro'] );

		return $items;
	}

	/**
	 * Retrieves the recipe objects Triggers, Actions, and Actions that are inside the Loop.
	 *
	 * @return string[] The option codes.
	 */
	public function get_recipe_objects() {

		$recipe_id = $this->recipe_id;

		// Merge both triggers and top level actions.
		$top_level_objects = array_column(
			get_posts(
				array(
					'post_parent' => $recipe_id,
					'post_type'   => array( 'uo-trigger', 'uo-action' ),
					'post_status' => array( 'draft', 'publish' ),
				),
				ARRAY_A
			),
			'ID'
		);

		// Retrieve loops' actions.
		$recipe_loops = array_column(
			get_posts(
				array(
					'post_parent' => $recipe_id,
					'post_type'   => 'uo-loop',
					'post_status' => array( 'draft', 'publish' ),
				),
				ARRAY_A
			),
			'ID'
		);

		$recipe_loops_actions = array();

		foreach ( (array) $recipe_loops as $loop_id ) {

			$loop_action_ids = array_column(
				get_posts(
					array(
						'post_parent' => $loop_id,
						'post_type'   => 'uo-action',
						'post_status' => array( 'draft', 'publish' ),
					),
					ARRAY_A
				),
				'ID'
			);

			foreach ( $loop_action_ids as $action_id ) {
				$recipe_loops_actions[] = $action_id;
			}
		}

		$recipe_objects = (array) array_merge( $top_level_objects, $recipe_loops_actions );

		$recipe_objects_meta = array();

		foreach ( $recipe_objects as $recipe_object_id ) {
			$recipe_objects_meta[] = get_post_meta( $recipe_object_id, 'code', true );
		}

		return array_unique( $recipe_objects_meta );

	}

	/**
	 * Restructure the integrations objects.
	 *
	 * @return self
	 */
	public function restructure_integrations_object() {

		$items = array();

		/**
		 * Retrieves all filters.
		 *
		 * The base plugin generally just adds a filter hook that pro or any 3rd-party devs can use to inject their filters.
		 *
		 * @see Loops_Hooks_Register_Singleton::__construct
		 */
		$filters = apply_filters( 'automator_integration_loop_filters', array() );

		// List Free only items
		foreach ( (array) $this->all_integrations as $code => $props ) {

			$is_app_connected = isset( $this->active_integrations[ $code ]['connected'] )
				? $this->active_integrations[ $code ]['connected']
				: null;

			$url_settings_page = isset( $this->active_integrations[ $code ]['settings_url'] )
				? $this->active_integrations[ $code ]['settings_url']
				: null;

			$is_pro_only = isset( $props['is_pro_only'] ) && 'yes' === $props['is_pro_only'];

			// If it's Pro only, continue
			if ( $is_pro_only ) {
				continue;
			}

			$triggers = $this->get_integration_property( $code, 'triggers' );
			$actions  = $this->get_integration_property( $code, 'actions' );

			$items[ $code ] = array(
				'name'          => $props['name'],
				'icon'          => $props['icon_svg'],
				'is_available'  => $this->is_integration_active( $code ),
				'is_app'        => $this->has_settings_and_has_connected_prop( $code ),
				'miscellaneous' => array(
					'is_app_connected'  => $is_app_connected,
					'url_settings_page' => $url_settings_page,
				),
				'triggers'      => $triggers,
				'actions'       => $actions,
				'conditions'    => $this->restructure_conditions( $code ),
				'loop_filters'  => isset( $filters[ $code ] ) ? $filters[ $code ] : array(),
			);
		}

		// List Pro only items
		foreach ( (array) $this->all_integrations as $code => $props ) {

			$is_pro_only = isset( $props['is_pro_only'] ) && 'yes' === $props['is_pro_only'];
			// If it's not a Pro only, continue
			if ( ! $is_pro_only ) {
				continue;
			}

			$triggers = $this->get_pro_integration_property( $code, 'triggers' );
			$actions  = $this->get_pro_integration_property( $code, 'actions' );

			$items[ $code ] = array(
				'name'          => $props['name'],
				'icon'          => $props['icon_svg'],
				'is_available'  => $this->is_integration_active( $code ),
				'is_app'        => $this->has_settings_and_has_connected_prop( $code ),
				'miscellaneous' => array(),
				'triggers'      => $triggers,
				'actions'       => $actions,
				'conditions'    => $this->restructure_conditions( $code ),
				'loop_filters'  => isset( $filters[ $code ] ) ? $filters[ $code ] : array(),
			);
		}

		ksort( $items );

		$this->structure = apply_filters( 'automator_integration_items', $items, $this );

		return $this;

	}

	/**
	 * Restructures the conditions to append the fields of the conditions that are active in the recipe.
	 *
	 * @param string $integration_code The integration code.
	 *
	 * @return mixed[]
	 */
	protected function restructure_conditions( $integration_code ) {

		/**
		 * Retrieves all conditions.
		 *
		 * @see Actions_Conditions::send_to_ui
		 */
		$conditions = (array) self::$action_conditions;

		$conditions_in_json = (array) json_decode( $this->recipe_action_conditions, true );

		$recipe_actions_conditions = array();

		foreach ( $conditions_in_json as $action_condition ) {
			if ( isset( $action_condition['conditions'] ) ) {
				$condition_codes = array_column( $action_condition['conditions'], 'condition' );
				foreach ( (array) $condition_codes as $code ) {
					$recipe_actions_conditions[] = $code;
				}
			}
		}

		$conditions = isset( $conditions[ $integration_code ] ) ? $conditions[ $integration_code ] : array();

		foreach ( (array) $conditions as $condition_code => $props ) { // We dont need conditions props. We're interested in the code.

			// All conditions should have fields prop with empty array as default value.
			$conditions[ $condition_code ]['fields'] = array();

			if ( in_array( $condition_code, $recipe_actions_conditions, true ) ) {

				$conditions[ $condition_code ]['fields'] = apply_filters(
					'automator_pro_actions_conditions_fields',
					array(),
					$integration_code,
					$condition_code
				);

			}
		}

		return $conditions;

	}

	/**
	 * JSON encodes the structure property.
	 *
	 * @return string the JSON encoded property of the structure.
	 */
	public function toJSON() { //phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return (string) wp_json_encode( $this->structure );
	}

}
