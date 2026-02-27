<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Recipe;

use Uncanny_Automator\Api\Components\Condition\Registry\WP_Action_Condition_Registry;
use Uncanny_Automator\Api\Database\Stores\WP_Recipe_Store;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Factory;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Locator;
use Uncanny_Automator\Api\Services\Condition\Utilities\Condition_Validator;
use Uncanny_Automator\Api\Services\Traits\Service_Response_Formatter;
use Uncanny_Automator\Api\Database\Stores\Action_Condition_Store;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Action_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Group_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Management_Service;
use Uncanny_Automator\Api\Services\Condition\Services\Condition_Query_Service;
use WP_Error;

/**
 * Recipe Condition Service - WordPress Application Layer.
 *
 * WordPress-aware service layer for managing action conditions within recipes.
 * Coordinates smaller, focused condition services to handle business logic for
 * creating, updating, and managing condition groups that control when actions execute in recipes.
 *
 * This service follows the Facade pattern, delegating to specialized services:
 * - Condition_Group_Service: Group lifecycle management
 * - Condition_Action_Service: Action assignment/removal
 * - Condition_Management_Service: Individual condition operations
 * - Condition_Query_Service: Read operations
 *
 * @since 7.0.0
 */
class Recipe_Condition_Service {

	use Service_Response_Formatter;

	/**
	 * Singleton instance.
	 *
	 * @var Recipe_Condition_Service|null
	 */
	private static $instance = null;

	private Action_Condition_Store $repository;

	private Condition_Validator $validation;

	private Condition_Group_Service $group_service;

	private Condition_Action_Service $action_service;

	private Condition_Management_Service $management_service;

	private Condition_Query_Service $query_service;

	/**
	 * Constructor.
	 *
	 * @param WP_Recipe_Store              $recipe_store Recipe storage implementation.
	 * @param WP_Action_Condition_Registry $condition_registry Condition registry implementation.
	 */
	public function __construct(
		?WP_Recipe_Store $recipe_store = null,
		?WP_Action_Condition_Registry $condition_registry = null,
		?Action_CRUD_Service $action_service = null,
		?Action_Condition_Store $repository = null,
		?Condition_Validator $validation = null,
		?Condition_Group_Service $group_service = null,
		?Condition_Action_Service $action_service_condition = null,
		?Condition_Management_Service $management_service = null,
		?Condition_Query_Service $query_service = null
	) {
		$recipe_store       = $recipe_store ?? new WP_Recipe_Store();
		$condition_registry = $condition_registry ?? new WP_Action_Condition_Registry();
		$action_service     = $action_service ?? Action_CRUD_Service::instance();

		$this->validation = $validation ?? new Condition_Validator( $condition_registry, $action_service );
		$this->repository = $repository ?? new Action_Condition_Store( $recipe_store );

		// Initialize condition services with shared dependencies
		$assembler     = new Condition_Factory( $this->validation );
		$group_locator = new Condition_Locator();

		$this->group_service      = $group_service ?? new Condition_Group_Service( $this->repository, $assembler, $group_locator );
		$this->action_service     = $action_service_condition ?? new Condition_Action_Service( $this->repository, $group_locator, $this->validation );
		$this->management_service = $management_service ?? new Condition_Management_Service( $this->repository, $assembler, $group_locator );
		$this->query_service      = $query_service ?? new Condition_Query_Service( $this->repository );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Recipe_Condition_Service Service instance.
	 */
	public static function instance(): Recipe_Condition_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
	/**
	 * Add condition group.
	 *
	 * @param int $recipe_id The ID.
	 * @param array $action_ids The ID.
	 * @param string $mode The mode.
	 * @param array $conditions The condition.
	 * @return mixed
	 */
	public function add_condition_group( int $recipe_id, array $action_ids, string $mode, array $conditions ) {
		$format_result = $this->validation->validate_action_ids_format( $action_ids );
		if ( is_wp_error( $format_result ) ) {
			return $format_result;
		}

		return $this->group_service->add_condition_group( $recipe_id, $action_ids, $mode, $conditions );
	}
	/**
	 * Update condition group.
	 *
	 * @param int $recipe_id The ID.
	 * @param string $group_id The ID.
	 * @param string $mode The mode.
	 * @param int $priority The priority.
	 * @return mixed
	 */
	public function update_condition_group( int $recipe_id, string $group_id, ?string $mode = null, ?int $priority = null ) {
		return $this->group_service->update_condition_group( $recipe_id, $group_id, $mode, $priority );
	}
	/**
	 * Remove condition group.
	 *
	 * @param int $recipe_id The ID.
	 * @param string $group_id The ID.
	 * @return mixed
	 */
	public function remove_condition_group( int $recipe_id, string $group_id ) {
		return $this->group_service->remove_condition_group( $recipe_id, $group_id );
	}
	/**
	 * Get recipe conditions.
	 *
	 * @param int $recipe_id The ID.
	 * @return mixed
	 */
	public function get_recipe_conditions( int $recipe_id ) {
		return $this->query_service->get_recipe_conditions( $recipe_id );
	}
	/**
	 * Remove action conditions.
	 *
	 * @param int $recipe_id The ID.
	 * @param int $action_id The ID.
	 * @return mixed
	 */
	public function remove_action_conditions( int $recipe_id, int $action_id ) {
		return $this->action_service->remove_action_conditions( $recipe_id, $action_id );
	}
	/**
	 * Add empty condition group.
	 *
	 * @param int      $recipe_id The recipe ID.
	 * @param string   $mode      The mode (any or all).
	 * @param int      $priority  The priority.
	 * @param int|null $parent_id The parent ID (recipe or loop). Defaults to recipe_id.
	 * @return mixed
	 */
	public function add_empty_condition_group( int $recipe_id, string $mode = 'any', int $priority = 20, ?int $parent_id = null ) {
		return $this->group_service->add_empty_condition_group( $recipe_id, $mode, $priority, $parent_id );
	}
	/**
	 * Add actions to condition group.
	 *
	 * @param int $recipe_id The ID.
	 * @param string $group_id The ID.
	 * @param array $action_ids The ID.
	 * @return mixed
	 */
	public function add_actions_to_condition_group( int $recipe_id, string $group_id, array $action_ids ) {
		return $this->action_service->add_actions_to_condition_group( $recipe_id, $group_id, $action_ids );
	}
	/**
	 * Remove actions from condition group.
	 *
	 * @param int $recipe_id The ID.
	 * @param string $group_id The ID.
	 * @param array $action_ids The ID.
	 * @return mixed
	 */
	public function remove_actions_from_condition_group( int $recipe_id, string $group_id, array $action_ids ) {
		return $this->action_service->remove_actions_from_condition_group( $recipe_id, $group_id, $action_ids );
	}
	/**
	 * Add condition to group.
	 *
	 * @param int $recipe_id The ID.
	 * @param string $group_id The ID.
	 * @param string $integration_code The integration code.
	 * @param string $condition_code The condition.
	 * @param array $fields The fields.
	 * @return mixed
	 */
	public function add_condition_to_group( int $recipe_id, string $group_id, string $integration_code, string $condition_code, array $fields ) {
		return $this->management_service->add_condition_to_group( $recipe_id, $group_id, $integration_code, $condition_code, $fields );
	}
	/**
	 * Update condition.
	 *
	 * @param string $condition_id The ID.
	 * @param string $group_id The ID.
	 * @param int $recipe_id The ID.
	 * @param array $fields The fields.
	 * @return mixed
	 */
	public function update_condition( string $condition_id, string $group_id, int $recipe_id, array $fields ) {
		return $this->management_service->update_condition( $condition_id, $group_id, $recipe_id, $fields );
	}
	/**
	 * Remove condition from group.
	 *
	 * @param string $condition_id The ID.
	 * @param string $group_id The ID.
	 * @param int $recipe_id The ID.
	 * @return mixed
	 */
	public function remove_condition_from_group( string $condition_id, string $group_id, int $recipe_id ) {
		return $this->management_service->remove_condition_from_group( $condition_id, $group_id, $recipe_id );
	}
}
