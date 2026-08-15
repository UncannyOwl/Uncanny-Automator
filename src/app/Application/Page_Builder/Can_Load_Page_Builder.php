<?php
/**
 * Page Builder new-page policy use case.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Application\Page_Builder;

use Uncanny_Automator\App\Feature_State\Application\Get_Feature_State;
use Uncanny_Automator\App\Feature_State\Domain\Feature_State;

/**
 * Decides if users can start new Page Builder pages.
 *
 * @since 7.5
 */
final class Can_Load_Page_Builder {

	/**
	 * Feature-state query.
	 *
	 * @var Get_Feature_State|null
	 */
	private $feature_state;

	/**
	 * Create the use case.
	 *
	 * The legacy availability port is still accepted so existing integrations do
	 * not fatal, but it is no longer a policy source.
	 *
	 * @param Get_Feature_State|Page_Builder_Availability_Port $feature_state Feature-state query or deprecated availability port.
	 */
	public function __construct( $feature_state ) {
		if ( $feature_state instanceof Get_Feature_State ) {
			$this->feature_state = $feature_state;
			return;
		}

		if ( $feature_state instanceof Page_Builder_Availability_Port ) {
			// Retain construction compatibility without treating the obsolete port
			// as policy evidence. Composition belongs at the Infrastructure edge.
			$this->feature_state = null;
			return;
		}

		throw new \InvalidArgumentException( 'Page Builder requires the Automator feature-state query.' );
	}

	/**
	 * Check if users can start new Page Builder pages.
	 *
	 * @return bool
	 */
	public function execute(): bool {
		return $this->feature_state instanceof Get_Feature_State
			&& $this->feature_state->execute()->is_visible( Feature_State::PAGE_BUILDER_MENU );
	}
}
