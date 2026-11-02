<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Admin\Notices;

/**
 * The admin screen the notices are drawn on: its id, whether it is one of
 * Automator's own, and whether Automator's component bundle is loaded on it,
 * which the Automator look needs.
 *
 * @since 8.0.0
 */
final class Notice_Screen {

	/**
	 * @var string
	 */
	private $id;

	/**
	 * @var bool
	 */
	private $is_automator;

	/**
	 * @var bool
	 */
	private $has_components;

	/**
	 * @param string $id             The WP_Screen id; '' when unknown.
	 * @param bool   $is_automator   Whether it is an Automator screen.
	 * @param bool   $has_components Whether Automator's component bundle is loaded.
	 */
	public function __construct( string $id, bool $is_automator, bool $has_components ) {

		$this->id             = $id;
		$this->is_automator   = $is_automator;
		$this->has_components = $has_components;
	}

	/**
	 * @return string
	 */
	public function id(): string {

		return $this->id;
	}

	/**
	 * @return bool
	 */
	public function is_automator(): bool {

		return $this->is_automator;
	}

	/**
	 * Whether notices take Automator's look here: an Automator screen with
	 * the components to draw it.
	 *
	 * @return bool
	 */
	public function takes_automator_look(): bool {

		return $this->is_automator && $this->has_components;
	}
}
