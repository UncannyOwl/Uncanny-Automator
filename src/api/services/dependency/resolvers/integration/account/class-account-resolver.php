<?php
/**
 * Account Resolver
 *
 * Resolves app connection/account dependencies.
 *
 * @package Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\Account
 * @since 7.0.0
 */

namespace Uncanny_Automator\Api\Services\Dependency\Resolvers\Integration\Account;

use Uncanny_Automator\Api\Components\Integration\Integration;
use Uncanny_Automator\Api\Services\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\Api\Services\Dependency\Resolvers\Abstract_Resolver;

/**
 * Resolves account dependencies (app connection requirements).
 *
 * Also handles third-party integrations that utilize settings pages and require connection.
 *
 * NOTE: This resolver is Integration-specific and will not evaluate for other entity types.
 *
 * @todo Review - Should we handle webhook triggers here - if not enabled
 * - Would check check if app integration has webhooks and add it true false up the line
 *
 * @since 7.0.0
 *
 * @property Integration $entity
 * @property Account_Scenario $scenario
 */
class Account_Resolver extends Abstract_Resolver {

	/**
	 * Check if should be evaluated.
	 *
	 * Evaluate for app integrations or third-party integrations that require connection.
	 * Returns false for non-Integration entities.
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Integration, Block, etc)
	 * @param array|null $item Item data (null for entity-level)
	 *
	 * @return bool True if should be evaluated
	 */
	public function should_evaluate( Dependency_Evaluatable $entity, $item = null ) {
		$this->set_properties( $entity, $item );

		// Only evaluate for Integration entities that require connection.
		return $this->is_integration() && $this->entity->requires_connection();
	}

	/**
	 * Evaluate account dependency.
	 *
	 * @return array Array of Dependency objects
	 */
	public function evaluate() {
		$is_connected = $this->entity->is_connected();

		/**
		 * TODO REVIEW
		 * - App Integration Trigger - Should check if enabled (requirements met)
		 */

		return array( $this->resolve( $is_connected ) );
	}

	/**
	 * Resolve account dependency.
	 *
	 * @param bool $is_connected Whether the account is connected
	 *
	 * @return Dependency
	 */
	private function resolve( bool $is_connected ) {
		$scenario_id    = $this->scenario->get_scenario_id();
		$settings_url   = $this->get_integration_settings_url();
		$developer_site = $this->entity->get_details()->get_developer()->get_site();
		$account_name   = $this->get_account_name();

		return $this->create_dependency(
			array(
				'type'        => 'account',
				'id'          => sprintf( 'account-%s', $this->get_code() ),
				'name'        => sprintf( $this->scenario->get_name( $scenario_id ), $account_name ),
				'description' => $this->scenario->get_description( $scenario_id, $account_name ),
				'is_met'      => $is_connected,
				'cta'         => $this->scenario->create_cta( $scenario_id, $account_name, $settings_url, $developer_site ),
				'scenario_id' => $scenario_id,
				'icon'        => $this->get_account_icon(),
				'tags'        => $this->get_tags(),
			)
		);
	}

	/**
	 * Get tags for this account dependency.
	 *
	 * @return array Array of tag arrays.
	 */
	private function get_tags(): array {
		return array(
			array(
				'scenario_id' => 'account',
				'label'       => 'Account',
				'icon'        => 'user',
			),
		);
	}

	/**
	 * Get integration settings URL.
	 *
	 * @return string Integration settings URL
	 */
	private function get_integration_settings_url() {
		$account = $this->entity->get_details()->get_account();
		return $account && $account->has_settings_url()
			? $account->get_settings_url()
			: '';
	}

	/**
	 * Get account name.
	 *
	 * @return string Account name.
	 */
	private function get_account_name() {
		$account = $this->entity->get_details()->get_account();
		return $account && $account->has_name()
			? $account->get_name()
			: $this->get_name();
	}

	/**
	 * Get account icon.
	 *
	 * @return string Account icon.
	 */
	private function get_account_icon() {
		$account = $this->entity->get_details()->get_account();
		return $account && $account->has_icon()
			? $account->get_icon()
			: $this->entity->get_details()->get_icon();
	}
}
