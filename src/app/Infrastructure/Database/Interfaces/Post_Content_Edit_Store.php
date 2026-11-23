<?php
/**
 * Persistence boundary for a checked native content edit.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

interface Post_Content_Edit_Store {

	/**
	 * The site's posts storage can't lock a row or roll back, so the edit never ran.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const ERROR_STORAGE_UNAVAILABLE = 'post_content_edit_storage_unavailable';

	/**
	 * The database didn't confirm the commit, so the edit may or may not have landed.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const ERROR_UNVERIFIED = 'post_content_edit_unverified';

	/** Run the edit while its existing WordPress post row is locked. */
	public function with_post_lock( int $post_id, callable $edit );
}
