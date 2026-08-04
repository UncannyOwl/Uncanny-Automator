<?php
/**
 * Automator-owned Page Builder availability.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Page_Builder;

use Uncanny_Automator\App\Application\Page_Builder\Page_Builder_Availability_Port;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Reports whether the active Automator license permits Page Builder.
 *
 * @since 7.5
 */
final class Page_Builder_Availability implements Page_Builder_Availability_Port {

	/**
	 * Check for an active Automator Pro license.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		if ( ! function_exists( 'Uncanny_Automator\App\Infrastructure\automator_license_manager' ) ) {
			return false;
		}

		try {
			$license_manager = automator_license_manager();

			// TODO: When Page Builder supports Free, replace this Pro type check with a Free license existence check.
			return is_object( $license_manager )
				&& method_exists( $license_manager, 'get_type' )
				&& 'pro' === $license_manager->get_type();
		} catch ( \Throwable $error ) {
			unset( $error );

			return false;
		}
	}
}
