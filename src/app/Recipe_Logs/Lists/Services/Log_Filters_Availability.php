<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Lists\Services;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Whether this site can filter the Logs page lists.
 *
 * Filtering by recipe, user, status, integration, endpoint, item and date
 * needs Automator Pro, as on the legacy Logs tables (decision L3). Searching
 * and sorting work on every site.
 *
 * @since 8.1.0
 */
final class Log_Filters_Availability {

	/**
	 * Whether the lists take filters.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		$manager = automator_license_manager();

		return null !== $manager && $manager->is_pro_active();
	}
}
