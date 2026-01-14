<?php
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.WP.I18n
declare(strict_types=1);
namespace Uncanny_Automator\Api\Components\Action;

use Uncanny_Automator\Api\Components\Action\Registry\WP_Action_Registry;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Id;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Integration_Code;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Code;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_User_Type;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Meta;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Meta_Code;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Deprecated;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Recipe_Id;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Parent_Id;
use Uncanny_Automator\Api\Components\Action\Value_Objects\Action_Status_Value;

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
	 * Convert to array.
	 *
	 * @return array Action data as array.
	 */
	public function to_array(): array {
		$meta = $this->action_meta->to_array();

		// Get all available configuration fields from registry
		$config = $this->get_complete_configuration();

		// Get async configuration
		$async_config = $this->action_meta->get_async_as_flat_array();

		return array(
			'action_id'                    => $this->action_id->get_value(),
			'action_code'                  => $this->action_code->get_value(),
			'action_meta_code'             => $this->action_meta_code->get_value(),
			'integration'                  => $this->action_integration_code->get_value(),
			'user_type'                    => $this->action_type->get_value(),
			'recipe_id'                    => $this->action_recipe_id->get_value(),
			'parent_id'                    => null !== $this->parent_id ? $this->parent_id->get_value() : null,
			'status'                       => null !== $this->status ? $this->status->get_value() : null,
			'sentence_human_readable'      => $meta['sentence_human_readable'] ?? '',
			'sentence_human_readable_html' => $meta['sentence_human_readable_html'] ?? '',
			'is_deprecated'                => $this->deprecated->get_value(),
			'config'                       => $config,
			'async'                        => $async_config,
		);
	}

	/**
	 * Get complete configuration with all available fields.
	 *
	 * Shows all fields from registry definition, even if not configured.
	 *
	 * @return array Complete configuration with all available fields.
	 */
	private function get_complete_configuration(): array {
		$meta            = $this->action_meta->to_array();
		$registry_fields = array( 'recipe_id', 'sentence_human_readable', 'sentence_human_readable_html' );

		// Get current user configuration (everything except registry fields)
		$current_config = array();
		foreach ( $meta as $key => $value ) {
			if ( ! in_array( $key, $registry_fields, true ) ) {
				$current_config[ $key ] = $value;
			}
		}

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
					// $field_config contains field schema but we only need the field_code for now
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
	 * Check if action has async configuration.
	 *
	 * @return bool
	 */
	public function has_async_configuration(): bool {
		return null !== $this->action_meta->get_async_mode();
	}

	/**
	 * Check if action is scheduled for delayed execution.
	 *
	 * @return bool
	 */
	public function is_delayed(): bool {
		$async_mode = $this->action_meta->get_async_mode();
		return null !== $async_mode && $async_mode->is_delay();
	}

	/**
	 * Check if action is scheduled for specific time.
	 *
	 * @return bool
	 */
	public function is_scheduled(): bool {
		$async_mode = $this->action_meta->get_async_mode();
		return null !== $async_mode && $async_mode->is_schedule();
	}

	/**
	 * Check if action has custom async timing.
	 *
	 * @return bool
	 */
	public function has_custom_async(): bool {
		$async_mode = $this->action_meta->get_async_mode();
		return null !== $async_mode && $async_mode->is_custom();
	}

	/**
	 * Get async sentence for display.
	 *
	 * @return string
	 */
	public function get_async_sentence(): string {
		return $this->action_meta->get_async_sentence();
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
