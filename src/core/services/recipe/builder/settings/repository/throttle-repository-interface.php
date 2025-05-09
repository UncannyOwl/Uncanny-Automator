<?php

namespace Uncanny_Automator\Services\Recipe\Builder\Settings\Repository;

interface Throttle_Repository_Interface {
	public function get_last_run( $recipe_id, $user_id );
	public function update_last_run( $recipe_id, $user_id, $timestamp );
}
