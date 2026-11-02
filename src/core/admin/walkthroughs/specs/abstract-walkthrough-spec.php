<?php

namespace Uncanny_Automator;

/**
 * Class Automator_Walkthrough_Spec
 *
 * Base for a backend-authored walkthrough spec consumed by the frontend engine. A subclass declares an ID constant and the ordered steps; this base assembles the spec and provides the wire-format constructors (targets, body markup, buttons) every spec shares. Keys match the frontend wire contract: each step carries an id, surface, title, body_html, an optional target ({ kind: selector|shadow_path }), attach side, wait_for targets, an advance condition ({ kind: event|button|navigate }), optional safe actions, and optional buttons.
 *
 * @package Uncanny_Automator
 */
abstract class Automator_Walkthrough_Spec {

	/**
	 * The ordered steps of the walkthrough.
	 *
	 * @return array
	 */
	abstract protected static function get_steps();

	/**
	 * Builds the full spec from the subclass ID constant and its steps.
	 *
	 * @return array
	 */
	public static function get_spec() {
		return array(
			'id'    => static::ID,
			'steps' => static::get_steps(),
		);
	}

	/**
	 * A light-DOM target.
	 *
	 * @param string $selector
	 *
	 * @return array
	 */
	protected static function selector( $selector ) {
		return array(
			'kind'     => 'selector',
			'selector' => $selector,
		);
	}

	/**
	 * A shadow-piercing target.
	 *
	 * @param array $path
	 *
	 * @return array
	 */
	protected static function shadow( $path ) {
		return array(
			'kind' => 'shadow_path',
			'path' => $path,
		);
	}

	/**
	 * Wraps an already-escaped, localized string in a paragraph for a step body.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	protected static function paragraph( $text ) {
		return '<p>' . $text . '</p>';
	}

	/**
	 * A single button row carrying one labelled tour action.
	 *
	 * @param string $label
	 * @param string $action One of next, back, complete.
	 *
	 * @return array
	 */
	protected static function button( $label, $action ) {
		return array(
			array(
				'label'  => $label,
				'action' => $action,
			),
		);
	}
}
