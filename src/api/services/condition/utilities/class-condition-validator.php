<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Condition\Utilities;

use Uncanny_Automator\Api\Components\Condition\Value_Objects\Condition_Fields;
use Uncanny_Automator\Api\Components\Condition\Dtos\Condition_Backup_Info;
use Uncanny_Automator\Api\Components\Condition\Registry\WP_Action_Condition_Registry;
use Uncanny_Automator\Api\Services\Action\Services\Action_CRUD_Service;
use WP_Error;

class Condition_Validator {

	private WP_Action_Condition_Registry $condition_registry;

	private Action_CRUD_Service $action_service;

	private array $integration_fallback_names = array(
		'WP'  => 'WordPress',
		'GEN' => 'General',
		'LD'  => 'LearnDash',
		'WC'  => 'WooCommerce',
		'UOA' => 'Automator',
	);

	/**
	 * Constructor.
	 *
	 * @param WP_Action_Condition_Registry $condition_registry Condition registry.
	 * @param Action_CRUD_Service          $action_service     Action service.
	 */
	public function __construct( WP_Action_Condition_Registry $condition_registry, Action_CRUD_Service $action_service ) {
		$this->condition_registry = $condition_registry;
		$this->action_service     = $action_service;
	}
	/**
	 * Validate action ids format.
	 *
	 * @param array $action_ids The ID.
	 * @return mixed
	 */
	public function validate_action_ids_format( array $action_ids ) {
		foreach ( $action_ids as $action_id ) {
			if ( ! is_int( $action_id ) || $action_id <= 0 ) {
				return new WP_Error(
					'invalid_action_id',
					esc_html_x( 'Action IDs must be positive integers.', 'Condition validator error', 'uncanny-automator' )
				);
			}
		}

		return true;
	}
	/**
	 * Assert actions in recipe.
	 *
	 * @param int $recipe_id The ID.
	 * @param array $action_ids The ID.
	 * @return mixed
	 */
	public function assert_actions_in_recipe( int $recipe_id, array $action_ids ) {
		if ( empty( $action_ids ) ) {
			return true;
		}

		$recipe_actions = $this->action_service->get_recipe_actions( $recipe_id );
		if ( is_wp_error( $recipe_actions ) ) {
			return $recipe_actions;
		}

		$existing_action_ids = array_column( $recipe_actions['actions'] ?? array(), 'action_id' );
		$invalid_actions     = array_diff( $action_ids, $existing_action_ids );

		if ( ! empty( $invalid_actions ) ) {
			return new WP_Error(
				'invalid_actions',
				sprintf(
					/* translators: %s Comma-separated action IDs. */
					esc_html_x( 'Action IDs not found in recipe: %s', 'Condition validator error', 'uncanny-automator' ),
					implode( ', ', $invalid_actions )
				)
			);
		}

		return true;
	}
	/**
	 * Ensure condition exists.
	 *
	 * @param string $integration_code The integration code.
	 * @param string $condition_code The condition.
	 * @return mixed
	 */
	public function ensure_condition_exists( string $integration_code, string $condition_code ) {
		if ( $this->condition_registry->condition_exists( $integration_code, $condition_code ) ) {
			return true;
		}

		return new WP_Error(
			'invalid_condition',
			sprintf(
				/* translators: 1: integration code, 2: condition code. */
				esc_html_x( 'Condition %1$s/%2$s not found. Use discovery tools to locate available conditions.', 'Condition validator error', 'uncanny-automator' ),
				$integration_code,
				$condition_code
			)
		);
	}
	/**
	 * Create backup info.
	 *
	 * @param string $integration_code The integration code.
	 * @param string $condition_code The condition.
	 * @param array $fields The fields.
	 * @return mixed
	 */
	public function create_backup_info( string $integration_code, string $condition_code, array $fields ) {
		$definition = $this->condition_registry->get_condition_definition( $integration_code, $condition_code );

		if ( ! $definition ) {
			return new WP_Error(
				'condition_not_found',
				esc_html_x( 'Condition definition not found.', 'Condition validator error', 'uncanny-automator' )
			);
		}

		$dynamic_name     = $definition['dynamic_name'] ?? 'Condition placeholder';
		$integration_name = $definition['integration_name'] ?? $this->get_integration_name( $integration_code );

		$title_html = sprintf(
			'<span class="uap-dynamic-sentence">%s</span>',
			esc_html( $dynamic_name )
		);

		return new Condition_Backup_Info(
			$dynamic_name,
			$title_html,
			$integration_name
		);
	}
	/**
	 * Get integration name.
	 *
	 * @param string $integration_code The integration code.
	 * @return string
	 */
	public function get_integration_name( string $integration_code ): string {
		return $this->integration_fallback_names[ $integration_code ] ?? $integration_code;
	}
}
