<?php
/**
 * Account Resolver
 *
 * Resolves app connection/account dependencies.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Account
 * @since 7.0.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Account;

use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Abstract_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Scope_Tag\License_Tag_Catalog;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Registry_Service;
use Uncanny_Automator\App\Plan\Domain\Plan_Levels;

/**
 * Resolves account dependencies (app connection requirements).
 *
 * Also handles third-party integrations that utilize settings pages and require connection.
 *
 * NOTE: This resolver is Integration-specific and will not evaluate for other entity types.
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
		$scenario_id     = $this->scenario->get_scenario_id();
		$settings_url    = $this->get_integration_settings_url();
		$developer_site  = $this->entity->get_details()->get_developer()->get_site();
		$account_name    = $this->get_account_name();
		$blocked_by_tier = $this->blocking_tier_label();
		$is_installed    = Integration_Registry_Service::get_instance()->has_integration( $this->get_code() );

		return $this->create_dependency(
			array(
				'type'         => 'account',
				'id'           => sprintf( 'account-%s', $this->get_code() ),
				'name'         => sprintf( $this->scenario->get_name( $scenario_id ), $account_name ),
				'description'  => $this->scenario->get_description( $scenario_id, $account_name, $blocked_by_tier, $is_installed ),
				'is_met'       => $is_connected,
				'is_disabled'  => '' !== $blocked_by_tier || ! $is_installed,
				'dependencies' => $this->blocking_dependency_ids( $blocked_by_tier, $is_installed ),
				'cta'          => $this->scenario->create_cta( $scenario_id, $account_name, $settings_url, $developer_site ),
				'scenario_id'  => $scenario_id,
				'icon'         => $this->get_account_icon(),
				'tags'         => $this->get_tags(),
			)
		);
	}

	/**
	 * Ids of the rows that have to be resolved before this account can connect.
	 *
	 * Both are named when both apply: an Elite licence and the add-on carrying the
	 * integration are separate things to obtain, and a user missing both should
	 * see both rather than discovering the second after buying the first.
	 *
	 * @param string $blocked_by_tier Tier display name, empty when the tier is met.
	 * @param bool   $is_installed    Whether the integration is loaded on this site.
	 *
	 * @return array<string>
	 */
	private function blocking_dependency_ids( string $blocked_by_tier, bool $is_installed ): array {
		$ids = array();

		if ( '' !== $blocked_by_tier ) {
			$ids[] = $this->license_dependency_id( self::LICENSE_TYPE_TIER, $this->get_code() );
		}

		if ( ! $is_installed ) {
			// The install row belongs to whatever is actually installable: this
			// integration when it is a plugin, otherwise the parent carrying it. An
			// app is not something a site installs, so it never owns that row.
			$plugin = $this->entity->get_details()->get_plugin();
			$code   = $this->entity->is_plugin()
				? $this->get_code()
				: ( $plugin ? $plugin->get_integration_required() : '' );

			if ( '' !== $code ) {
				$ids[] = sprintf( 'installable-%s', $code );
			}
		}

		return $ids;
	}

	/**
	 * Display name of the license tier blocking this connection, if any.
	 *
	 * An Elite app on a Plus licence is not installed, so the settings page the
	 * connect CTA targets does not exist. Declaring the account dependency
	 * blocked by the license one is the same relationship an uninstalled parent
	 * plugin has with its child — the license row is what the user must resolve
	 * first, and the connect CTA should not invite a click until they have.
	 *
	 * Returns the tier's display name rather than a bool so the copy can name
	 * what is missing; a disabled button with no reason reads as a broken one.
	 *
	 * Note this answers only the licence question. An integration whose code ships
	 * in a separate plugin - the Elite add-on - can clear the tier gate and still
	 * be absent, so resolve() checks the registry as well.
	 *
	 * @return string Tier display name, e.g. "Elite". Empty when the tier is met.
	 */
	private function blocking_tier_label(): string {
		$tier = $this->get_required_tier();

		if ( Plan_Levels::LITE === $tier ) {
			return '';
		}

		$is_met = $this->context->is_pro_license_valid()
			&& $this->context->get_plan_service()->meets_tier_requirement( $tier );

		if ( $is_met ) {
			return '';
		}

		$tag = License_Tag_Catalog::for_tier( $tier );

		return $tag['label'] ?? '';
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
