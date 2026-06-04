<?php

namespace Uncanny_Automator\Integrations\Thrive_Ovation;

use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Thrive_Ovation_Helpers
 *
 * @package Uncanny_Automator\Integrations\Thrive_Ovation
 */
class Thrive_Ovation_Helpers extends Abstract_Helpers {

	/**
	 * Lazy-instantiated shared tokens collaborator.
	 *
	 * @var Thrive_Ovation_Tokens|null
	 */
	private $tokens = null;

	/**
	 * Access the shared tokens collaborator.
	 *
	 * @return Thrive_Ovation_Tokens
	 */
	public function tokens() {

		if ( null === $this->tokens ) {
			$this->tokens = new Thrive_Ovation_Tokens( $this );
		}

		return $this->tokens;
	}
}
