<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Items;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Duplication_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_CRUD_Service;
use Uncanny_Automator\App\Recipe_Builder\Trigger\Services\Trigger_Registry_Service;
use WP_Error;

/**
 * Trigger REST handler.
 */
class Trigger_Rest extends Recipe_Item_Rest {

	/**
	 * Read one of the item's post meta values through its store.
	 *
	 * @param string $key Meta key.
	 *
	 * @return mixed The stored value, or '' when there is none.
	 */
	protected function item_meta( string $key ) {
		return Database::get_recipe_trigger_store()->get_meta( $this->get_post_id(), $key );
	}

	/**
	 * The stored value of each key a save sent, read from what the trigger store wrote.
	 *
	 * The store respells keys (it upper-cases field codes), so each sent key is
	 * matched to the stored configuration without regard to case.
	 *
	 * @since 8.0.0
	 *
	 * @param string[] $keys Keys the save sent.
	 *
	 * @return array<string, mixed>
	 */
	protected function saved_field_values( array $keys ): array {
		$store   = Database::get_recipe_trigger_store();
		$post    = $store->get_wp_post( $this->get_post_id() );
		$trigger = null === $post ? null : $store->hydrate_trigger_from_post( $post );
		$stored  = null === $trigger ? array() : array_change_key_case( (array) $trigger->get_trigger_configuration()->get_value(), CASE_LOWER );

		$values = array();
		foreach ( $keys as $key ) {
			$values[ $key ] = $stored[ strtolower( $key ) ] ?? '';
		}

		return $values;
	}

	/**
	 * Add a trigger to recipe.
	 *
	 * @return array|WP_Error
	 */
	protected function do_add_item() {
		$config = $this->build_trigger_config();
		if ( null === $config ) {
			return $this->failure( 'Trigger not found in registry.', 404, 'automator_trigger_not_found' );
		}

		$service = Trigger_CRUD_Service::instance();
		$result  = $service->add_to_recipe(
			$this->get_recipe_id(),
			$this->get_code(),
			$config
		);

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_add_trigger_to_recipe_failed' );
		}

		$trigger_id = (int) $result['trigger_id'];
		$this->set_post_id( $trigger_id );

		return array(
			'item_id'   => $trigger_id,
			'item_data' => $result['trigger'] ?? array(),
		);
	}

	/**
	 * Update trigger config.
	 *
	 * @return array|WP_Error
	 */
	protected function do_update_item() {
		$store     = Database::get_recipe_trigger_store();
		$item_post = $store->get_wp_post( $this->get_post_id() );
		if ( ! $item_post ) {
			return $this->failure( 'Trigger not found.', 404, 'automator_trigger_not_found' );
		}

		$this->set_wp_post( $item_post );
		$this->set_code( (string) $store->get_meta( $this->get_post_id(), 'code' ) );
		$this->set_integration_code( (string) $store->get_meta( $this->get_post_id(), 'integration' ) );

		$config  = $this->prepare_field_config_for_services();
		$service = Trigger_CRUD_Service::instance();
		$result  = $service->update_trigger( (int) $this->get_post_id(), $config, $this->get_recipe_id() );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_update_trigger_failed' );
		}

		return array(
			'success'   => true,
			'item_data' => $result['trigger'] ?? array(),
			'config'    => $config,
		);
	}

	/**
	 * Delete trigger from recipe.
	 *
	 * @return bool|WP_Error
	 */
	protected function do_delete_item() {
		// Capture code from meta before deletion (needed for hook payload).
		$this->set_code( (string) Database::get_recipe_trigger_store()->get_meta( $this->get_post_id(), 'code' ) );

		$service = Trigger_CRUD_Service::instance();
		$result  = $service->remove_from_recipe( $this->get_recipe_id(), $this->get_post_id() );

		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_delete_trigger_failed' );
		}

		return true;
	}

	/**
	 * Toggle the trigger's post_status.
	 *
	 * @param bool $is_on true → publish, false → draft.
	 * @return array|WP_Error
	 */
	protected function do_set_status( bool $is_on ) {
		return Trigger_CRUD_Service::instance()->set_status(
			$this->get_post_id(),
			$this->get_recipe_id(),
			$is_on
		);
	}

	/**
	 * Duplicate this trigger within the recipe.
	 *
	 * A second trigger on an anonymous recipe is rejected downstream by
	 * `Trigger_CRUD_Service::add_to_recipe`, so the helper returns a
	 * `WP_Error` that surfaces here as a 400.
	 *
	 * @return int|WP_Error New trigger id.
	 */
	protected function do_duplicate_item() {
		$service = new Recipe_Duplication_Service();
		$result  = $service->duplicate_entity( $this->get_recipe_id(), $this->get_post_id() );
		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, 'automator_duplicate_trigger_failed' );
		}

		return (int) $result->get_root_id();
	}

	/**
	 * Resolve `meta_code` from the integration-catalog definition.
	 *
	 * @return string Empty string when the catalog row can't be resolved.
	 */
	private function resolve_meta_code(): string {
		$definition = $this->get_item_definition();
		if ( null === $definition ) {
			return '';
		}

		return $definition->to_array()['meta_code'] ?? '';
	}

	/**
	 * Build trigger configuration for CRUD service.
	 *
	 * @return array|null
	 */
	private function build_trigger_config(): ?array {
		$registry = Trigger_Registry_Service::instance()->get_trigger_by_code( $this->get_code() );
		if ( empty( $registry ) ) {
			return null;
		}

		return array(
			'integration'             => $this->get_integration_code(),
			'meta_code'               => $this->resolve_meta_code(),
			'sentence'                => $registry['sentence'] ?? '',
			'sentence_human_readable' => $registry['select_option_name'] ?? '',
			'type'                    => $registry['type'] ?? 'anonymous',
			'hook'                    => $registry['action'] ?? '',
		);
	}
}
