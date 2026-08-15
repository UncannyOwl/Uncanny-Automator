<?php
/**
 * Automator-owned Page Builder availability.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Page_Builder;

use Uncanny_Automator\App\Application\Page_Builder\Can_Load_Page_Builder;
use Uncanny_Automator\App\Application\Page_Builder\Page_Builder_Availability_Port;

use function Uncanny_Automator\App\Infrastructure\automator_feature_state_query;

/**
 * Backward-compatible adapter for the former Page Builder availability port.
 *
 * @since 7.5
 * @deprecated 7.5.1.1 Use Get_Feature_State with PAGE_BUILDER_MENU.
 */
final class Page_Builder_Availability implements Page_Builder_Availability_Port {

	/**
	 * Check the current Page Builder menu policy.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		if ( ! function_exists( 'Uncanny_Automator\App\Infrastructure\automator_feature_state_query' ) ) {
			return false;
		}

		try {
			return ( new Can_Load_Page_Builder( automator_feature_state_query() ) )->execute();
		} catch ( \Throwable $error ) {
			unset( $error );

			return false;
		}
	}
}
