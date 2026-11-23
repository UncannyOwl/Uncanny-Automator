<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\Dependency;

/**
 * The ids of the rows an item states beyond its integration, built from the requirements the registration pass
 * stored with it: its own plugin row (`installable-{INTEGRATION}-{ITEM}`), one row per setup step
 * (`setup-{INTEGRATION}-{key}`), and the install rows of the integration it requires (`installable-{CODE}`, the
 * id every integration's own install row has). The resolvers that build those rows and the filter that keeps them
 * beside the integration's own rows read the formats from here.
 *
 * @since 8.1.0
 */
final class Stated_Row_Id {

	/**
	 * The id of the plugin row an item states.
	 *
	 * @since 8.1.0
	 *
	 * @param string $integration_code The item's integration code.
	 * @param string $item_code        The item code.
	 *
	 * @return string
	 */
	public static function plugin( string $integration_code, string $item_code ): string {
		return 'installable-' . $integration_code . '-' . $item_code;
	}

	/**
	 * The id of an integration's own install row.
	 *
	 * @since 8.1.0
	 *
	 * @param string $integration_code The integration code.
	 *
	 * @return string
	 */
	public static function integration( string $integration_code ): string {
		return 'installable-' . $integration_code;
	}

	/**
	 * The ids of the install rows built for the integration an item requires: that integration's own row and, through
	 * the ids each row depends on, the rows of the integrations it needs in turn, among the item's rows.
	 *
	 * @since 8.1.0
	 *
	 * @param string       $required_code The integration the item requires; '' when none.
	 * @param Dependency[] $rows          The item's rows.
	 *
	 * @return string[]
	 */
	public static function required_integration( string $required_code, array $rows ): array {
		$by_id = array();
		foreach ( $rows as $row ) {
			$by_id[ $row->get_id() ] = $row;
		}

		$ids     = array();
		$pending = '' === $required_code ? array() : array( self::integration( $required_code ) );

		while ( ! empty( $pending ) ) {
			$id = array_shift( $pending );
			if ( isset( $ids[ $id ] ) || ! isset( $by_id[ $id ] ) ) {
				continue;
			}

			$ids[ $id ] = true;
			$pending    = array_merge( $pending, $by_id[ $id ]->get_dependencies() );
		}

		return array_keys( $ids );
	}

	/**
	 * The id of the row for one setup step an item states.
	 *
	 * @since 8.1.0
	 *
	 * @param string $integration_code The item's integration code.
	 * @param string $step_key         The step's key.
	 *
	 * @return string
	 */
	public static function setup( string $integration_code, string $step_key ): string {
		return 'setup-' . $integration_code . '-' . $step_key;
	}

	/**
	 * Whether a row is one the item stated: its own plugin row or a setup row of its integration.
	 *
	 * Setup rows match by integration, not item: the only rows checked here are the item's own resolved rows, so a
	 * setup row of its integration can only be one this item stated.
	 *
	 * @since 8.1.0
	 *
	 * @param string $id               The row id.
	 * @param string $integration_code The item's integration code.
	 * @param string $item_code        The item code.
	 *
	 * @return bool
	 */
	public static function is_stated( string $id, string $integration_code, string $item_code ): bool {
		return self::plugin( $integration_code, $item_code ) === $id
			|| 0 === strpos( $id, self::setup( $integration_code, '' ) );
	}
}
