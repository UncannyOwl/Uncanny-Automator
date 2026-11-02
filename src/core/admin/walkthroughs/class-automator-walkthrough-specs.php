<?php

namespace Uncanny_Automator;

use Exception;

/**
 * Class Automator_Walkthrough_Specs
 *
 * Resolves the active walkthrough spec for a surface plus the current user's resume pointer, in the shape the frontend engine consumes. A walkthrough is active when the user has neither dismissed nor completed it.
 *
 * @package Uncanny_Automator
 */
class Automator_Walkthrough_Specs {

	/**
	 * Resolves the active spec and progress for a surface.
	 *
	 * @param string $surface The surface to resolve, e.g. recipe-edit.
	 * @param int    $user_id The current user.
	 *
	 * @return array { active: array|null, progress: array|null }
	 */
	public static function get_active( $surface, $user_id ) {

		if ( defined( 'AUTOMATOR_DISABLE_WALKTHROUGHS' ) && AUTOMATOR_DISABLE_WALKTHROUGHS ) {
			return array(
				'active'   => null,
				'progress' => null,
			);
		}

		foreach ( self::registered_specs( $surface ) as $spec ) {

			// As in 7.x, the create-recipe tour is for people who have not published a recipe. A failed read shows nothing.
			if ( Automator_Create_Recipe_Walkthrough::ID === $spec['id'] && false !== automator_user_has_published_recipe( (int) $user_id ) ) {
				continue;
			}

			$progress  = self::get_progress( $spec['id'], $user_id );
			$dismissed = 1 === $progress['dismissed'];
			$completed = $progress['progress'] >= 100;

			if ( ! $dismissed && ! $completed ) {
				return array(
					'active'   => $spec,
					'progress' => $progress,
				);
			}
		}

		return array(
			'active'   => null,
			'progress' => null,
		);
	}

	/**
	 * Collects the specs relevant to a surface.
	 *
	 * @param string $surface
	 *
	 * @return array
	 */
	private static function registered_specs( $surface ) {

		$specs = array();

		foreach ( self::all_specs() as $spec ) {
			if ( in_array( $surface, self::spec_surfaces( $spec ), true ) ) {
				$specs[] = $spec;
			}
		}

		/**
		 * Filters the walkthrough specs available on a surface.
		 *
		 * @param array  $specs   Ordered list of `{ id, steps }` specs.
		 * @param string $surface The surface being resolved.
		 */
		return apply_filters( 'automator_walkthrough_specs', $specs, $surface );
	}

	/**
	 * Every registered walkthrough spec, regardless of surface.
	 *
	 * @return array
	 */
	private static function all_specs() {
		return array(
			Automator_Create_Recipe_Walkthrough::get_spec(),
		);
	}

	/**
	 * The distinct surfaces a spec touches, read from its steps. A spec is served on every surface it has a step for, which is what lets one walkthrough resume across pages: each page resolves the same spec and the engine runs the steps for that page.
	 *
	 * @param array $spec
	 *
	 * @return array
	 */
	private static function spec_surfaces( $spec ) {

		$surfaces = array();

		if ( ! isset( $spec['steps'] ) || ! is_array( $spec['steps'] ) ) {
			return $surfaces;
		}

		foreach ( $spec['steps'] as $step ) {
			if ( isset( $step['surface'] ) && ! in_array( $step['surface'], $surfaces, true ) ) {
				$surfaces[] = $step['surface'];
			}
		}

		return $surfaces;
	}

	/**
	 * Reads a user's progress for one walkthrough in the frontend wire shape.
	 *
	 * @param string $walkthrough_id
	 * @param int    $user_id
	 *
	 * @return array { id, step, progress, dismissed, show }
	 */
	private static function get_progress( $walkthrough_id, $user_id ) {

		$stored = array(
			'show'      => 0,
			'step'      => '',
			'progress'  => 0,
			'dismissed' => 0,
		);

		if ( $user_id > 0 ) {
			try {
				$walkthroughs = new Automator_User_Walkthroughs( $user_id );
				$stored       = $walkthroughs->get_progress_by_id( $walkthrough_id );
			} catch ( Exception $e ) {
				unset( $e );
			}
		}

		return array(
			'id'        => $walkthrough_id,
			'step'      => isset( $stored['step'] ) ? (string) $stored['step'] : '',
			'progress'  => isset( $stored['progress'] ) ? (int) $stored['progress'] : 0,
			'dismissed' => empty( $stored['dismissed'] ) ? 0 : 1,
			'show'      => empty( $stored['show'] ) ? 0 : 1,
		);
	}
}
