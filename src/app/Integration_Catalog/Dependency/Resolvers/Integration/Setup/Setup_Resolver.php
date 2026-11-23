<?php
/**
 * Setup Resolver
 *
 * Resolves the setup steps an item needs on the site, for plugin and app integrations alike.
 *
 * @package Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Setup
 * @since 8.1.0
 */

namespace Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Integration\Setup;

use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Evaluatable;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Resolvers\Abstract_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Stated_Row_Id;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Value_Objects\Dependency_Cta;
use Uncanny_Automator\App\Integration_Catalog\Integration\Integration;
use Uncanny_Automator\App\Integration_Catalog\Services\Utilities\Store\Integration_Details_Normalizer;

/**
 * One row per setup step an item (trigger, action, condition or loop filter) needs on the site, built from the
 * requirements the registration pass stored with it (`$item['requirements']['setup']`); the item is never asked
 * again.
 *
 * The registration pass works the steps out only once the item's plugin and required integration are met, so a
 * plugin integration needs no gate of its own here. An app integration (one that requires a connection) shows its
 * steps only once connected: until then its account row is the step to take.
 *
 * A row links to the step's own `url` when it gives one (the item knows where its plugin's setting lives),
 * otherwise to the integration's settings page.
 *
 * The rows are typed account, with the account row's scenario id, because the builder's dependency union has no
 * setup type; their tag names the Settings category.
 *
 * Integration-specific and item-level: never evaluates for other entity types or for an integration on its own.
 *
 * @since 8.1.0
 *
 * @property Integration $entity
 */
class Setup_Resolver extends Abstract_Resolver {

	/**
	 * Evaluate for an item that stored setup steps, on a plugin integration or a connected app integration.
	 *
	 * @since 8.1.0
	 *
	 * @param Dependency_Evaluatable $entity Entity object (Integration, Block, etc).
	 * @param array|null             $item   Item data (null for entity-level).
	 *
	 * @return bool True if should be evaluated.
	 */
	public function should_evaluate( Dependency_Evaluatable $entity, $item = null ) {
		$this->set_properties( $entity, $item );

		if ( ! $this->is_integration() || empty( $this->get_steps() ) ) {
			return false;
		}

		return ! $this->entity->requires_connection() || $this->entity->is_connected();
	}

	/**
	 * One row per stored step, with the step's answer.
	 *
	 * A row needs a page to link to: the step's own `url` when it is an absolute http or https URL, otherwise the
	 * integration's settings page (the full page, in a new tab: without the connect popup's args). It also needs a
	 * key (its id) and a description from the step. A step with no name of its own is named after the integration's
	 * settings page. A step with no page to link to, or missing its key or description, gives no row; each is logged
	 * once instead, and the item's other rows still show.
	 *
	 * @since 8.1.0
	 *
	 * @return Dependency[]
	 */
	public function evaluate() {
		$settings_url = $this->get_settings_url();
		$rows         = array();

		if ( '' !== $settings_url ) {
			$settings_url = remove_query_arg( Integration_Details_Normalizer::SETTINGS_URL_POPUP_ARGS, $settings_url );
		}

		foreach ( $this->get_steps() as $index => $step ) {
			if ( ! $this->is_complete_step( $step ) ) {
				automator_log( sprintf( 'Setup row %s of %s left out: a step needs a key and a description.', (string) $index, $this->get_item_code() ), 'Setup_Resolver' );

				continue;
			}

			$url = $this->get_step_url( $step );
			$url = '' !== $url ? $url : $settings_url;

			if ( '' === $url ) {
				automator_log( sprintf( 'Setup row %s of %s left out: neither the step nor the integration has a settings page.', (string) $index, $this->get_item_code() ), 'Setup_Resolver' );

				continue;
			}

			$rows[] = $this->create_dependency(
				array(
					'type'        => 'account',
					'id'          => Stated_Row_Id::setup( $this->get_code(), (string) $step['key'] ),
					'name'        => $this->get_step_name( $step ),
					'description' => (string) $step['description'],
					'is_met'      => true === ( $step['is_met'] ?? false ),
					'cta'         => $this->create_cta( $url ),
					'scenario_id' => 'account-app',
					'icon'        => $this->get_icon(),
					'tags'        => array(
						array(
							'scenario_id' => 'account',
							'label'       => esc_html_x( 'Settings', 'Dependency', 'uncanny-automator' ),
							'icon'        => 'gear',
						),
					),
				)
			);
		}

		return $rows;
	}

	/**
	 * The setup steps the item being resolved stored.
	 *
	 * @since 8.1.0
	 *
	 * @return array Empty at integration level, or when the item stored no steps.
	 */
	private function get_steps(): array {
		return null !== $this->item ? (array) ( $this->item['requirements']['setup'] ?? array() ) : array();
	}

	/**
	 * The code of the item being resolved, for the log lines.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	private function get_item_code(): string {
		return (string) ( $this->item['code'] ?? '' );
	}

	/**
	 * Whether a stored setup step has what its row needs: a key for its id and a description.
	 *
	 * @since 8.1.0
	 *
	 * @param mixed $step The stored step.
	 *
	 * @return bool
	 */
	private function is_complete_step( $step ): bool {
		if ( ! is_array( $step ) ) {
			return false;
		}

		foreach ( array( 'key', 'description' ) as $field ) {
			if ( '' === (string) ( $step[ $field ] ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * The row's name: the step's own, or "{Integration} settings" when the step gives none.
	 *
	 * @since 8.1.0
	 *
	 * @param array $step The stored step.
	 *
	 * @return string
	 */
	private function get_step_name( array $step ): string {
		$name = (string) ( $step['name'] ?? '' );

		if ( '' !== $name ) {
			return $name;
		}

		return sprintf(
			/* translators: %s: The integration's name */
			esc_html_x( '%s settings', 'Setup step name', 'uncanny-automator' ),
			$this->get_name()
		);
	}

	/**
	 * The step's own page, when it gives an absolute http or https URL with a host; empty otherwise.
	 *
	 * @since 8.1.0
	 *
	 * @param array $step The stored step.
	 *
	 * @return string
	 */
	private function get_step_url( array $step ): string {
		$url = is_string( $step['url'] ?? null ) ? esc_url_raw( $step['url'], array( 'http', 'https' ) ) : '';

		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Call to action for a setup step: the page where the step is done.
	 *
	 * @since 8.1.0
	 *
	 * @param string $settings_url The step's own page, or the integration's full settings page.
	 *
	 * @return Dependency_Cta
	 */
	private function create_cta( string $settings_url ): Dependency_Cta {
		return new Dependency_Cta(
			array(
				'type'  => 'link-external',
				'label' => sprintf(
					// translators: %s: Integration name
					esc_attr_x( 'Open %s settings', 'Dependency CTA', 'uncanny-automator' ),
					esc_html( $this->get_name() )
				),
				'url'   => $settings_url,
			)
		);
	}

	/**
	 * The integration's settings page, as its details carry it; empty when they carry none.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	private function get_settings_url(): string {
		$account = $this->entity->get_details()->get_account();

		return $account && $account->has_settings_url()
			? $account->get_settings_url()
			: '';
	}

	/**
	 * The row's icon: an app integration's account icon (its integration icon when it has none), a plugin
	 * integration's own icon.
	 *
	 * @since 8.1.0
	 *
	 * @return string
	 */
	private function get_icon(): string {
		$details = $this->entity->get_details();
		$account = $details->get_account();

		return $this->entity->requires_connection() && $account && $account->has_icon()
			? $account->get_icon()
			: $details->get_icon();
	}
}
