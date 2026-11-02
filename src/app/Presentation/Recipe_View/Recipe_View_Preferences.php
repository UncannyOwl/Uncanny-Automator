<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Recipe_View;

/**
 * One person's recipe view preferences, as the REST read and write answer
 * them: the view they last picked (none until they pick one), their default
 * view, and whether they dismissed the introduction to the diagram.
 *
 * @since 8.0.0
 */
final class Recipe_View_Preferences {

	/**
	 * The view the person last picked; null until they pick one.
	 *
	 * @var Recipe_View|null
	 */
	private $view;

	/**
	 * What a recipe opens as while the person has not picked.
	 *
	 * @var Recipe_View
	 */
	private $default_view;

	/**
	 * Whether the person dismissed the introduction to the diagram.
	 *
	 * @var bool
	 */
	private $diagram_intro_dismissed;

	/**
	 * Hold one person's preferences.
	 *
	 * @since 8.0.0
	 *
	 * @param Recipe_View|null $view                    The picked view, or null.
	 * @param Recipe_View      $default_view            The person's default view.
	 * @param bool             $diagram_intro_dismissed Whether the introduction was dismissed.
	 */
	public function __construct( ?Recipe_View $view, Recipe_View $default_view, bool $diagram_intro_dismissed ) {

		$this->view                    = $view;
		$this->default_view            = $default_view;
		$this->diagram_intro_dismissed = $diagram_intro_dismissed;
	}

	/**
	 * The wire record. Exactly these three keys: the frontend parses it strictly.
	 *
	 * @since 8.0.0
	 *
	 * @return array{view: string|null, default_view: string, diagram_intro_dismissed: bool}
	 */
	public function to_array(): array {

		return array(
			'view'                    => null === $this->view ? null : $this->view->value(),
			'default_view'            => $this->default_view->value(),
			'diagram_intro_dismissed' => $this->diagram_intro_dismissed,
		);
	}
}
