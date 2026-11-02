<?php
declare(strict_types=1);
namespace Uncanny_Automator\App\Recipe_Builder\Action;

use Uncanny_Automator\App\Recipe_Builder\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Custom_Item_Name_Field;
use Uncanny_Automator\App\Recipe_Builder\Field\Services\Item_Field_Filter;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Id;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Integration_Code;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Code;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_User_Type;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Meta;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Meta_Code;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Deprecated;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Recipe_Id;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Parent_Id;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Path_Code;
use Uncanny_Automator\App\Recipe_Builder\Action\Value_Objects\Action_Status_Value;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Value_Objects\Recipe_Id;

/**
 * Action Aggregate.
 *
 * Pure domain object representing an action instance within a recipe.
 * Contains zero WordPress dependencies - pure PHP business logic only.
 *
 * @since 7.0.0
 */
class Action {

	private Action_Id $action_id;
	private Action_Integration_Code $action_integration_code;
	private Action_Code $action_code;
	private Action_Meta_Code $action_meta_code;
	private Action_User_Type $action_type;
	private Action_Meta $action_meta;
	private Action_Recipe_Id $action_recipe_id;
	private Action_Deprecated $deprecated;
	private ?Action_Parent_Id $parent_id = null;

	/**
	 * Which branch of the parent block this action sits in; null when it sits at the recipe level.
	 *
	 * @var Action_Path_Code|null
	 */
	private ?Action_Path_Code $path_code = null;
	private ?Action_Status_Value $status = null;

	/**
	 * Constructor.
	 *
	 * @param Action_Config $config Action configuration object.
	 */
	public function __construct( Action_Config $config ) {

		// Use value objects to ensure data integrity on instance creation instead of runtime.
		// This way, once the instance is created, we can be sure it's valid.
		// Any invalid data will throw an exception here.
		// This also makes the class immutable after creation.
		// Any changes require creating a new instance with new data.
		// This way LLMs can reason and drift all they want but at the end of the day,
		// truth lives in our business logic, not in the LLM's head. ~ Joseph Gabito
		$this->action_id               = new Action_Id( $config->get_id() );
		$this->action_integration_code = new Action_Integration_Code( $config->get_integration_code() );
		$this->action_code             = new Action_Code( $config->get_code() );
		$this->action_meta_code        = new Action_Meta_Code( $config->get_meta_code() );
		$this->action_type             = new Action_User_Type( $config->get_user_type() );
		$this->action_meta             = new Action_Meta( $config->get_meta() );

		// Extract recipe_id from meta - actions must always belong to a recipe
		$this->action_recipe_id = new Action_Recipe_Id( $config->get_recipe_id() );
		$this->deprecated       = new Action_Deprecated( $config->get_is_deprecated() );

		// Set parent_id - wraps the Parent_Id interface (Recipe_ID or Loop_ID)
		if ( null !== $config->get_parent_id() ) {
			$this->parent_id = new Action_Parent_Id( $config->get_parent_id() );
		}

		// Set path_code - the branch of the parent block this action lives in.
		if ( null !== $config->get_path_code() ) {
			$this->path_code = new Action_Path_Code( $config->get_path_code() );
		}

		// Set status - defaults to draft
		if ( null !== $config->get_status() ) {
			$this->status = new Action_Status_Value( $config->get_status() );
		}

		$this->validate_business_rules();
	}

	/**
	 * Get action ID.
	 *
	 * @return Action_Id Action ID.
	 */
	public function get_action_id(): Action_Id {
		return $this->action_id;
	}

	/**
	 * Get action integration code.
	 *
	 * @return Action_Integration_Code Action integration code.
	 */
	public function get_action_integration_code(): Action_Integration_Code {
		return $this->action_integration_code;
	}

	/**
	 * Get action code.
	 *
	 * @return Action_Code Action code.
	 */
	public function get_action_code(): Action_Code {
		return $this->action_code;
	}

	/**
	 * Get action type.
	 *
	 * @return Action_User_Type Action type.
	 */
	public function get_action_type(): Action_User_Type {
		return $this->action_type;
	}

	/**
	 * Get action meta.
	 *
	 * @return Action_Meta Action meta.
	 */
	public function get_action_meta(): Action_Meta {
		return $this->action_meta;
	}

	/**
	 * Get action recipe ID.
	 *
	 * @return Action_Recipe_Id Action recipe ID.
	 */
	public function get_action_recipe_id(): Action_Recipe_Id {
		return $this->action_recipe_id;
	}

	/**
	 * Get action parent ID.
	 *
	 * @return Action_Parent_Id|null Action parent ID or null.
	 */
	public function get_parent_id(): ?Action_Parent_Id {
		return $this->parent_id;
	}

	/**
	 * Get the action's block path code (branch of its parent block).
	 *
	 * @return Action_Path_Code|null
	 */
	public function get_path_code(): ?Action_Path_Code {
		return $this->path_code;
	}

	/**
	 * Get action status.
	 *
	 * @return Action_Status_Value|null Action status or null.
	 */
	public function get_status(): ?Action_Status_Value {
		return $this->status;
	}

	/**
	 * Get action meta code.
	 *
	 * @return Action_Meta_Code Action meta code.
	 */
	public function get_action_meta_code(): Action_Meta_Code {
		return $this->action_meta_code;
	}

	/**
	 * Get action deprecated status.
	 *
	 * @return Action_Deprecated Action deprecated status.
	 */
	public function get_deprecated(): Action_Deprecated {
		return $this->deprecated;
	}

	/**
	 * Create a new Action with a different parent ID.
	 *
	 * Returns a new immutable Action instance with the updated parent and no path: the path belonged to the
	 * old parent, and the store reconciles one the new parent declares, or its default, or clears it at the
	 * recipe root. Used when moving an action between the recipe root and a block.
	 *
	 * @param int $new_parent_id New parent ID (recipe or loop).
	 * @return Action New Action instance with updated parent.
	 */
	public function with_parent_id( int $new_parent_id ): Action {
		$config = ( new Action_Config() )
			->id( $this->action_id->get_value() )
			->integration_code( $this->action_integration_code->get_value() )
			->code( $this->action_code->get_value() )
			->meta_code( $this->action_meta_code->get_value() )
			->user_type( $this->action_type->get_value() )
			->recipe_id( $this->action_recipe_id->get_value() )
			->meta( $this->action_meta->to_array() )
			->is_deprecated( $this->deprecated->get_value() )
			->parent_id( new Recipe_Id( $new_parent_id ) );

		if ( null !== $this->status ) {
			$config->status( $this->status->get_value() );
		}

		return new Action( $config );
	}

	/**
	 * Create a new Action on a different block path.
	 *
	 * Returns a new immutable Action with the updated path_code, preserving
	 * parent_id and status. Used when moving an action between block branches.
	 *
	 * @param string|null $new_path_code New path code, or null to clear it.
	 * @return Action New Action instance with updated path.
	 */
	public function with_path_code( ?string $new_path_code ): Action {
		$config = ( new Action_Config() )
			->id( $this->action_id->get_value() )
			->integration_code( $this->action_integration_code->get_value() )
			->code( $this->action_code->get_value() )
			->meta_code( $this->action_meta_code->get_value() )
			->user_type( $this->action_type->get_value() )
			->recipe_id( $this->action_recipe_id->get_value() )
			->meta( $this->action_meta->to_array() )
			->is_deprecated( $this->deprecated->get_value() )
			->path_code( $new_path_code );

		if ( null !== $this->parent_id ) {
			$config->parent_id( $this->parent_id->get_parent() );
		}

		if ( null !== $this->status ) {
			$config->status( $this->status->get_value() );
		}

		return new Action( $config );
	}

	/**
	 * Convert to array.
	 *
	 * @return array Action data as array.
	 */
	public function to_array(): array {
		$meta = $this->action_meta->to_array();

		// Get all available configuration fields from registry
		$config = $this->get_complete_configuration();

		return array(
			'action_id'                    => $this->action_id->get_value(),
			'action_code'                  => $this->action_code->get_value(),
			'action_meta_code'             => $this->action_meta_code->get_value(),
			'integration'                  => $this->action_integration_code->get_value(),
			'user_type'                    => $this->action_type->get_value(),
			'recipe_id'                    => $this->action_recipe_id->get_value(),
			'parent_id'                    => null !== $this->parent_id ? $this->parent_id->get_value() : null,
			'path_code'                    => null !== $this->path_code ? $this->path_code->get_value() : null,
			'status'                       => null !== $this->status ? $this->status->get_value() : null,
			'sentence_human_readable'      => $meta['sentence_human_readable'] ?? '',
			'sentence_human_readable_html' => $meta['sentence_human_readable_html'] ?? '',
			'is_deprecated'                => $this->deprecated->get_value(),
			'config'                       => $config,
		);
	}

	/**
	 * Get the action's declarative field map — registry-declared field
	 * codes mapped to their saved values, plus any `*_readable` snapshots
	 * persisted alongside.
	 *
	 * Structural meta (sentence snapshots, recipe_id) is excluded via
	 * {@see Item_Field_Filter}. Use {@see self::get_sentence()} and
	 * {@see self::get_sentence_html()} for those. The custom label IS
	 * included — it is a declared field — and {@see self::get_custom_name()}
	 * exposes the same value for the wire's top-level `custom_name`.
	 *
	 * @return array<string, mixed> Flat code → value map.
	 */
	public function get_fields(): array {
		$current = Item_Field_Filter::omit_structural_keys( $this->action_meta->to_array() );

		try {
			$action_registry   = new WP_Action_Registry();
			$action_code_vo    = new Action_Code( $this->action_code->get_value() );
			$action_definition = $action_registry->get_action_definition( $action_code_vo );

			if ( $action_definition && isset( $action_definition['meta_structure'] ) ) {
				$complete = array();

				// Every declared field code, populated from current values
				// (empty string default for codes the user hasn't set).
				foreach ( $action_definition['meta_structure'] as $field_code => $_field_schema ) {
					$complete[ $field_code ] = $current[ $field_code ] ?? '';
				}

				// Preserve extras the registry doesn't declare (e.g. `*_readable`
				// snapshots from select fields, integration-specific spillover).
				foreach ( $current as $key => $value ) {
					if ( ! isset( $complete[ $key ] ) ) {
						$complete[ $key ] = $value;
					}
				}

				return $complete;
			}
		} catch ( \Exception $e ) {
			// Registry lookup failed — fall through to raw meta.
			unset( $e );
		}

		return $current;
	}

	/**
	 * Read only fields the owner configured, preserving absence separately from an explicit empty value.
	 *
	 * The legacy complete field APIs remain available through get_fields() and to_array().
	 * Storage and the builder wire use this sparse map so defaults apply only to unsaved fields.
	 *
	 * @since 8.0.0
	 * @return array<string, mixed>
	 */
	public function get_saved_fields(): array {
		return Item_Field_Filter::omit_structural_keys( $this->action_meta->to_array() );
	}

	/**
	 * Get the user-configured custom name (the global `Custom action label`
	 * field). Returns null when unset.
	 */
	public function get_custom_name(): ?string {
		$value = $this->action_meta->get( Custom_Item_Name_Field::FIELD_CODE );
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Get the action's rendered sentence snapshot (plain text).
	 */
	public function get_sentence(): string {
		return (string) $this->action_meta->get( 'sentence_human_readable', '' );
	}

	/**
	 * Get the action's rendered sentence snapshot (HTML).
	 */
	public function get_sentence_html(): string {
		return (string) $this->action_meta->get( 'sentence_human_readable_html', '' );
	}

	/**
	 * Get complete configuration with all available fields.
	 *
	 * Shows all fields from registry definition, even if not configured.
	 * Used internally by {@see self::to_array()}. Retains
	 * `_automator_custom_item_name_` for `to_array()` back-compat; the
	 * stricter public surface is {@see self::get_fields()}. Structural meta
	 * (an integration's stored secret among it) never reaches the config.
	 *
	 * @return array Complete configuration with all available fields.
	 */
	private function get_complete_configuration(): array {
		$current_config = Item_Field_Filter::omit_structural_keys( $this->action_meta->to_array() );

		// Get all available fields from registry
		try {
			$action_registry   = new WP_Action_Registry();
			$action_code_vo    = new Action_Code( $this->action_code->get_value() );
			$action_definition = $action_registry->get_action_definition( $action_code_vo );

			if ( $action_definition && isset( $action_definition['meta_structure'] ) ) {
				$complete_config = array();

				// Include all available fields from registry
				foreach ( $action_definition['meta_structure'] as $field_code => $field_config ) {
					$complete_config[ $field_code ] = $current_config[ $field_code ] ?? '';
				}

				// Add any additional configured fields that might not be in registry
				foreach ( $current_config as $key => $value ) {
					if ( ! isset( $complete_config[ $key ] ) ) {
						$complete_config[ $key ] = $value;
					}
				}

				return $complete_config;
			}
		} catch ( \Exception $e ) {
			// Fallback to current config if registry lookup fails.
			return $current_config;
		}

		// Fallback: return just the current configuration
		return $current_config;
	}

	/**
	 * Check if action is persisted.
	 *
	 * @return bool True if action has been saved to database.
	 */
	public function is_persisted(): bool {
		return $this->action_id->get_value() > 0;
	}

	/**
	 * Validate business rules.
	 *
	 * @throws \InvalidArgumentException If business rules are violated.
	 */
	private function validate_business_rules(): void {
		// Business rule: Actions must belong to a recipe
		if ( $this->action_recipe_id->is_null() ) {
			throw new \InvalidArgumentException( 'Action must belong to a recipe (recipe_id required)' );
		}

		// Business rule: Action integration and code must be compatible
		// This would typically check against a registry or validation service
		// For now, we'll just ensure both are present (already validated by VOs)
	}
}
