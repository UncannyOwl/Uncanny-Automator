<?php

namespace Uncanny_Automator\Migrations;

/**
 * Class Remove_Completed_Recipes_Option.
 *
 * `automator_completed_recipes` backed a running counter that 7.7 removed: it
 * was incremented on every `automator_recipe_completed`, a hook that fires on
 * every recipe finalization whatever the resolved status, so it never measured
 * completions. Nothing reads it now, and without this it would sit in
 * uap_options on every existing install indefinitely.
 *
 * A migration rather than a healthcheck callback, so the DELETE runs once
 * instead of daily on every site forever.
 *
 * @package Uncanny_Automator
 */
class Remove_Completed_Recipes_Option extends Migration {

	/**
	 * The option 7.7 orphaned.
	 */
	const ORPHANED_OPTION = 'automator_completed_recipes';

	/**
	 * __construct
	 *
	 * @return void
	 */
	public function __construct() {
		parent::__construct( '77_remove_completed_recipes_option' );
	}

	/**
	 * conditions_met
	 *
	 * @return bool
	 */
	public function conditions_met() {
		return true;
	}

	/**
	 * migrate
	 *
	 * @return void
	 */
	public function migrate() {

		automator_delete_option( self::ORPHANED_OPTION );

		$this->complete();
	}
}

new Remove_Completed_Recipes_Option();
