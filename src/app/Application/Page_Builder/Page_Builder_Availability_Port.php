<?php
/**
 * Page Builder availability port.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Page_Builder;

/**
 * Supplies the host decision that permits Page Builder.
 *
 * @since 7.5
 */
interface Page_Builder_Availability_Port {

	/**
	 * Check if the host permits Page Builder.
	 *
	 * @return bool
	 */
	public function is_available(): bool;
}
