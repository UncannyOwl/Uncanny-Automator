<?php
/**
 * Page Builder availability port.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Page_Builder;

/**
 * Supplies the legacy host decision that permitted Page Builder.
 *
 * @since 7.5
 * @deprecated 7.5.1.1 Use Get_Feature_State with PAGE_BUILDER_MENU.
 */
interface Page_Builder_Availability_Port {

	/**
	 * Check if the host permits Page Builder.
	 *
	 * @return bool
	 */
	public function is_available(): bool;
}
