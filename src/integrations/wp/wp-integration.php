<?php

namespace Uncanny_Automator\Integrations\Wp;

use Uncanny_Automator\Integrations\Wp\Migrations\WP_Token_Aliases_Migration;
use Uncanny_Automator\Integrations\Wp\Tokens\Trigger\Loopable\Post_Categories;
use Uncanny_Automator\Integrations\Wp\Tokens\Trigger\Loopable\Post_Tags;
use Uncanny_Automator\Integrations\Wp\Tokens\Universal\Loopable\Post_Categories as Universal_Post_Categories;
use Uncanny_Automator\Integrations\Wp\Tokens\Universal\Loopable\Post_Tags as Universal_Post_Tags;

/**
 * Class Wp_Integration
 *
 * @package Uncanny_Automator\Integrations\Wp
 */
class Wp_Integration extends \Uncanny_Automator\Integration {

	/**
	 * Integration setup.
	 *
	 * @return void
	 */
	protected function setup() {
		$this->helpers = new Wp_Helpers();
		$this->set_integration( 'WP' );
		$this->set_name( 'WordPress' );
		$this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/wordpress-icon.svg' );
		$this->register_hooks();

		Wp_Token_Aliases::register( Wp_Token_Aliases::default_map() );
		add_filter( 'automator_resolve_token_pieces', array( Wp_Token_Aliases::class, 'rewrite_pieces' ) );

		// One-shot postmeta + options migration — runs only on admin requests,
		// guarded by an internal uap_options version flag (Section 2.5).
		// Skip on admin-ajax / admin-post — long postmeta scan would block
		// user-facing requests; admin pages only, not ajax / cron.
		if ( is_admin() && ! wp_doing_ajax() ) {
			add_action( 'admin_init', static function () {
				( new WP_Token_Aliases_Migration() )->maybe_run();
			} );
		}

		// Per-import targeted patching. Listens on the shared action contract
		// fired by Import_Recipe::import_recipe_json() so newly-imported
		// recipes are sentinel- and alias-normalised even after the
		// site-wide pass has long since flagged itself complete.
		Wp_Sentinel_Migration::register_listeners();
		WP_Token_Aliases_Migration::register_listeners();

		// @deprecated 7.2 — Singleton shim for old Pro (54 calls to Automator()->helpers->recipe->wp).
		\Automator()->helpers->recipe->wp = $this->helpers;
	}

	/**
	 * WordPress is always active.
	 *
	 * @return bool
	 */
	public function plugin_active() {
		return true;
	}

	/**
	 * Load all triggers, actions, tokens, and closures.
	 *
	 * @return void
	 */
	public function load() {

		// Old token classes — must load before triggers for old Pro compat.
		// Pro token classes extend these (WP_Pro_Tokens extends Wp_Tokens).
		new \Uncanny_Automator\Wp_Tokens();
		new \Uncanny_Automator\Wp_Post_Tokens();

		// Closure — framework-agnostic, no overlap with migrated validate() logic.
		require_once __DIR__ . '/closures/closure-redirect.php';

		// Loopable tokens.
		if ( method_exists( $this, 'set_loopable_tokens' ) ) {
			$this->set_loopable_tokens(
				array(
					'WP_POST_TAGS'       => Post_Tags::class,
					'WP_POST_CATEGORIES' => Post_Categories::class,
				)
			);
		}

		// === Free Triggers ===

		// Anonymous triggers.
		new ANON_WP_POST_PUBLISHED_IN_TAXONOMY( $this->helpers );
		new ANON_WP_RESET_PASSWORD_LINK_SENT( $this->helpers );
		new ANON_WP_UPDATES_POST_IN_TAXONOMY( $this->helpers );
		new ANON_WP_VIEWPOSTTYPE( $this->helpers );
		new WP_ANON_UPDATES_POST( $this->helpers );

		// User triggers — post/page.
		new WP_POST_PUBLISHED( $this->helpers );
		new WP_USERCREATESPOST( $this->helpers );
		new WP_USER_UPDATES_POST( $this->helpers );
		new WP_USERS_POST_PUBLISHED( $this->helpers );
		new WP_VIEWCUSTOMPOST( $this->helpers );
		new WP_VIEWPAGE( $this->helpers );
		new WP_VIEWPOST( $this->helpers );

		// User triggers — comment.
		new WP_POSTRECEIVESCOMMENT( $this->helpers );
		new WP_SUBMITCOMMENT( $this->helpers );

		// User triggers — user/role.
		new WP_LOGIN( $this->helpers );
		new WP_LOGOUT( $this->helpers );
		new WP_USERROLEADDED( $this->helpers );
		new WP_USERROLEUPDATED( $this->helpers );

		// === Free Actions ===

		// Role actions.
		new WP_ADDROLE( $this->helpers );
		new WP_USERROLE( $this->helpers );
		new WP_CREATE_ROLE( $this->helpers );

		// Post actions.
		new WP_CREATEPOST( $this->helpers );
		new WP_DUPLICATE_POST( $this->helpers );
		new WP_DUPLICATE_PAGE( $this->helpers );
		new WP_CHANGE_POST_TYPE( $this->helpers );
		new WP_UPDATE_POST_EXCERPT( $this->helpers );

		// Comment actions.
		new WP_ADD_REPLY_TO_COMMENT( $this->helpers );

		// User actions.
		new WP_LOGOUT_USER( $this->helpers );
		new WP_ERASE_PERSONAL_USER_DATA( $this->helpers );

		// Menu actions.
		new WP_ADD_MENU( $this->helpers );
		new WP_RENAME_MENU( $this->helpers );
		new WP_DELETE_MENU( $this->helpers );
		new WP_ADD_MENU_ITEM( $this->helpers );
		new WP_UPDATE_MENU_ITEM( $this->helpers );
		new WP_DELETE_MENU_ITEM( $this->helpers );
		new WP_ASSIGN_MENU_TO_LOCATION( $this->helpers );

		// Media actions.
		new WP_ADD_FILE_TO_MEDIA_LIBRARY( $this->helpers );
		new WP_GET_MEDIA_ITEM( $this->helpers );
		new WP_UPDATE_MEDIA_ITEM( $this->helpers );
		new WP_DELETE_MEDIA_ITEM( $this->helpers );

		// Taxonomy actions.
		new WP_CREATE_TAXONOMY_TERM( $this->helpers );
		new WP_DELETE_TAXONOMY_TERM( $this->helpers );
		new WP_UPDATE_TAXONOMY_TERM( $this->helpers );

		// Post actions (additional).
		new WP_RESTORE_POST_FROM_TRASH( $this->helpers );
		new WP_SET_POST_PASSWORD( $this->helpers );
		new WP_DELETE_POST_META( $this->helpers );
		new WP_GET_POST_META( $this->helpers );
		new WP_GET_USER_META( $this->helpers );

		// Comment actions (additional).
		new WP_DELETE_COMMENT( $this->helpers );
		new WP_SET_COMMENT_STATUS( $this->helpers );

		// User actions (additional).
		new WP_GENERATE_PASSWORD_RESET_LINK( $this->helpers );

		// One-time data migrations.
		Wp_Sentinel_Migration::migrate();
	}

	/**
	 * Register hooks.
	 *
	 * Option-data endpoints are served by the unified Remote_Data REST framework
	 * — see Wp_Helpers::remote_data_get_* handlers and `'remote_data' => ...`
	 * field configs on triggers/actions.
	 *
	 * @return void
	 */
	protected function register_hooks() {

		// Role change handlers (fires automator_user_role_changed internal hook).
		$this->helpers->setup_role_change_handlers();
	}
}
