<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Traits;

use WP_Post;

/**
 * Universal property base for any post-typed recipe entity.
 *
 * Every recipe entity — items (triggers / actions), blocks
 * (filter / loop / delay_schedule), and block-modifiers (filter
 * conditions / loop filters) — is stored as a WordPress post. This
 * trait carries the storage that's universally true: the post id, the
 * per-grouping type discriminator, and an optional cached `WP_Post`
 * for handlers that need to resolve `post_parent` etc.
 *
 * Integration-aware entities (items + modifiers) extend this with
 * `Integration_Entity_Properties` which adds the `code` + `integration_code`
 * pair plus the `_from_meta()` hydration helpers.
 */
trait Recipe_Entity_Properties {

	/**
	 * The recipe-entity post id. Null until the request is parsed or the
	 * service responds with the persisted id.
	 *
	 * @var int|null
	 */
	private ?int $post_id = null;

	/**
	 * The per-grouping type discriminator (e.g. `'action'`, `'filter'`,
	 * `'loop_filter'`). Open string — the URL segment is sanitised at the
	 * route layer; this stores the post-sanitisation value.
	 *
	 * @var string
	 */
	private string $type = '';

	/**
	 * Optional cached `WP_Post` for handlers that need to resolve
	 * `post_parent` during update / delete (e.g. a modifier whose
	 * `parent_id` isn't sent in the request body).
	 *
	 * @var WP_Post|null
	 */
	private ?WP_Post $wp_post = null;

	/**
	 * Record the WordPress post backing this entity, or null before it is saved.
	 *
	 * @param int|null $post_id
	 * @return void
	 */
	protected function set_post_id( ?int $post_id ): void {
		$this->post_id = $post_id;
	}

	/**
	 * The WordPress post backing this entity, or null when it has not been saved yet.
	 *
	 * @return int|null
	 */
	protected function get_post_id(): ?int {
		return $this->post_id;
	}

	/**
	 * Record which kind of entity this is within its grouping.
	 *
	 * @param string $type
	 * @return void
	 */
	protected function set_type( string $type ): void {
		$this->type = $type;
	}

	/**
	 * Which kind of entity this is within its grouping — the discriminator callers switch on.
	 *
	 * @return string
	 */
	protected function get_type(): string {
		return $this->type;
	}

	/**
	 * Cache the loaded post so repeated reads do not hit the database again.
	 *
	 * @param WP_Post $wp_post
	 * @return void
	 */
	protected function set_wp_post( WP_Post $wp_post ): void {
		$this->wp_post = $wp_post;
	}

	/**
	 * The cached post, or null when nothing has loaded it yet.
	 *
	 * @return WP_Post|null
	 */
	protected function get_wp_post(): ?WP_Post {
		return $this->wp_post;
	}
}
