<?php
/**
 * Persistence boundary for a checked native content edit.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

interface Post_Content_Edit_Store {

	/** Run the edit while its existing WordPress post row is locked. */
	public function with_post_lock( int $post_id, callable $edit );
}
