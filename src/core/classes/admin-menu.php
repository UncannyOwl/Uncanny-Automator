<?php

namespace Uncanny_Automator;


/**
 * Class Admin_Menu
 * @package Uncanny_Automator
 */
class Admin_Menu {

	/*
	 * Setting Page title
	 */
	/**
	 * @var
	 */
	public $settings_page_slug;

	/**
	 * class constructor
	 */
	function __construct() {

		// Setup Theme Options Page Menu in Admin
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'register_options_menu_page' ) );
			add_action( 'admin_menu', array( $this, 'override_pro_menu' ), 10 );
			add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
			// add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ), 2 );
			add_action( 'admin_footer', [ $this, 'override_pro_filters' ] );
		}
	}

	/**
	 * TODO: Remove this function after pro 2.1.1 release
	 */
	public function override_pro_filters() {
		if ( defined( 'AUTOMATOR_PRO_FILE' ) || class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
			$post_type = isset( $_GET['post_type'] ) ? sanitize_text_field( $_GET['post_type'] ) : 'uo-recipe';
			$page      = isset( $_GET['page'] ) ? sanitize_text_field( $_GET['page'] ) : 'uncanny-automator-recipe-log';

			?>
            <script>
                jQuery(document).ready(function () {
                    jQuery('form.uap-pro-filters').attr('action', '<?php echo admin_url( 'edit.php' ) ?>').append('<input type="hidden" name="post_type" value="<?php echo $post_type; ?>" />');
                    jQuery('form.uap-pro-filters input[name="page"]').val('<?php echo $page; ?>');
                })
            </script>
			<?php
		}
	}

	/**
	 * @param $hook
	 */
	public function scripts( $hook ) {
		$is_a_log = ( strpos( $hook, 'uncanny-automator-recipe-log' ) !== false ) || ( strpos( $hook, 'uncanny-automator-trigger-log' ) !== false ) || ( strpos( $hook, 'uncanny-automator-action-log' ) !== false );

		if ( $is_a_log ) {
			Utilities::enqueue_global_assets();
			// Automator assets
			wp_enqueue_style( 'uap-logs-free', Utilities::get_css( 'admin/logs.css' ), array(), Utilities::get_version() );
		}
	}

	/**
	 * Override license menu in free so that its not broken in older pro plugin
	 */
	public function override_pro_menu() {
		if ( defined( 'AUTOMATOR_PRO_FILE' ) || class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
			if ( class_exists( '\Uncanny_Automator_Pro\Boot' ) ) {
				$boot = \Uncanny_Automator_Pro\Boot::get_instance();
				remove_action( 'admin_menu', [ $boot, 'uap_automator_license_menu' ], 11 );
			}

			add_submenu_page( $this->settings_page_slug,
				/* translators: 1. Trademarked term */
				sprintf( __( '%1$s license activation', 'uncanny-automator' ), 'Uncanny Automator' ),
				__( 'License activation', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-license-activation', array(
				$boot,
				'uap_automator_license_page'
			) );
		}
	}

	/**
	 * Create Plugin options menu
	 */
	public function register_options_menu_page() {

		//$page_title = __( 'Uncanny Automator', 'uncanny-automator' );

		//$capability = 'manage_options';

		//$menu_title               = $page_title;
		$parent_slug              = 'edit.php?post_type=uo-recipe';
		$this->settings_page_slug = $parent_slug;
		$function                 = array( $this, 'options_menu_page_output' );

		//$icon_url = 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz48c3ZnIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDU4MSA2NDAiIHZlcnNpb249IjEuMSIgdmlld0JveD0iMCAwIDU4MSA2NDAiIHhtbDpzcGFjZT0icHJlc2VydmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHBhdGggZD0ibTUyNi40IDM0LjFjMC42IDUgMSAxMC4xIDEuMyAxNS4xIDAuNSAxMC4zIDEuMiAyMC42IDAuOCAzMC45LTAuNSAxMS41LTEgMjMtMi4xIDM0LjQtMi42IDI2LjctNy44IDUzLjMtMTYuNSA3OC43LTcuMyAyMS4zLTE3LjEgNDEuOC0yOS45IDYwLjQtMTIgMTcuNS0yNi44IDMzLTQzLjggNDUuOS0xNy4yIDEzLTM2LjcgMjMtNTcuMSAyOS45LTI1LjEgOC41LTUxLjUgMTIuNy03Ny45IDEzLjggNzAuMyAyNS4zIDEwNi45IDEwMi44IDgxLjYgMTczLjEtMTguOSA1Mi42LTY4LjEgODguMS0xMjQgODkuNWgtNi4xYy0xMS4xLTAuMi0yMi4xLTEuOC0zMi45LTQuNy0yOS40LTcuOS01NS45LTI2LjMtNzMuNy01MC45LTI5LjItNDAuMi0zNC4xLTkzLjEtMTIuNi0xMzgtMjUgMjUuMS00NC41IDU1LjMtNTkuMSA4Ny40LTguOCAxOS43LTE2LjEgNDAuMS0yMC44IDYxLjEtMS4yLTE0LjMtMS4yLTI4LjYtMC42LTQyLjkgMS4zLTI2LjYgNS4xLTUzLjIgMTIuMi03OC45IDUuOC0yMS4yIDEzLjktNDEuOCAyNC43LTYwLjlzMjQuNC0zNi42IDQwLjYtNTEuM2MxNy4zLTE1LjcgMzcuMy0yOC4xIDU5LjEtMzYuOCAyNC41LTkuOSA1MC42LTE1LjIgNzYuOC0xNy4yIDEzLjMtMS4xIDI2LjctMC44IDQwLjEtMi4zIDI0LjUtMi40IDQ4LjgtOC40IDcxLjMtMTguMyAyMS05LjIgNDAuNC0yMS44IDU3LjUtMzcuMiAxNi41LTE0LjkgMzAuOC0zMi4xIDQyLjgtNTAuOCAxMy0yMC4yIDIzLjQtNDIuMSAzMS42LTY0LjcgNy42LTIxLjEgMTMuNC00Mi45IDE2LjctNjUuM3ptLTI3OS40IDMyOS41Yy0xOC42IDEuOC0zNi4yIDguOC01MC45IDIwLjQtMTcuMSAxMy40LTI5LjggMzIuMi0zNi4yIDUyLjktNy40IDIzLjktNi44IDQ5LjUgMS43IDczIDcuMSAxOS42IDE5LjkgMzcuMiAzNi44IDQ5LjYgMTQuMSAxMC41IDMwLjkgMTYuOSA0OC40IDE4LjZzMzUuMi0xLjYgNTEtOS40YzEzLjUtNi43IDI1LjQtMTYuMyAzNC44LTI4LjEgMTAuNi0xMy40IDE3LjktMjkgMjEuNS00NS43IDQuOC0yMi40IDIuOC00NS43LTUuOC02Ni45LTguMS0yMC0yMi4yLTM3LjYtNDAuMy00OS4zLTE4LTExLjctMzkuNS0xNy02MS0xNS4xeiIgZmlsbD0iIzgyODc4QyIvPjxwYXRoIGQ9Im0yNDIuNiA0MDIuNmM2LjItMS4zIDEyLjYtMS44IDE4LjktMS41LTExLjQgMTEuNC0xMi4yIDI5LjctMS44IDQyIDExLjIgMTMuMyAzMS4xIDE1LjEgNDQuNCAzLjkgNS4zLTQuNCA4LjktMTAuNCAxMC41LTE3LjEgMTIuNCAxNi44IDE2LjYgMzkuNCAxMSA1OS41LTUgMTguNS0xOCAzNC42LTM1IDQzLjUtMzQuNSAxOC4yLTc3LjMgNS4xLTk1LjUtMjkuNS0xLTItMi00LTIuOS02LjEtOC4xLTE5LjYtNi41LTQzIDQuMi02MS4zIDEwLTE3IDI2LjgtMjkuMiA0Ni4yLTMzLjR6IiBmaWxsPSIjODI4NzhDIi8+PC9zdmc+';

		//$position = 81; // 81 - Above Settings Menu

		//add_menu_page( $page_title, $menu_title, $capability, $parent_slug, '', $icon_url, $position );

		//add_submenu_page( $parent_slug, __( 'All Recipes', 'uncanny-automator' ), __( 'All Recipes', 'uncanny-automator' ), 'manage_options', $parent_slug );
		//add_submenu_page( $parent_slug, __( 'New Recipe', 'uncanny-automator' ), __( 'New Recipe', 'uncanny-automator' ), 'manage_options', 'post-new.php?post_type=uo-recipe' );
		//add_submenu_page( $parent_slug, __( 'Categories', 'uncanny-automator' ), __( 'Categories', 'uncanny-automator' ), 'manage_options', 'edit-tags.php?taxonomy=recipe_category&post_type=uo-recipe' );
		//add_submenu_page( $parent_slug, __( 'Tags', 'uncanny-automator' ), __( 'Tags', 'uncanny-automator' ), 'manage_options', 'edit-tags.php?taxonomy=recipe_tag&post_type=uo-recipe' );
		add_submenu_page( $parent_slug, __( 'Recipe log', 'uncanny-automator' ), __( 'Recipe log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-recipe-log', $function );
		add_submenu_page( $parent_slug, __( 'Trigger log', 'uncanny-automator' ), __( 'Trigger log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-trigger-log', $function );
		add_submenu_page( $parent_slug, __( 'Action log', 'uncanny-automator' ), __( 'Action log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-action-log', $function );
	}

	/**
	 * Create Page view
	 */
	public function options_menu_page_output() {
		$current_tab = 'recipe-log';
		//isset( $_GET['page'] ) ? str_replace( 'uncanny-automator-', '', sanitize_text_field( $_GET['page'] ) ) : 'recipe-log';
		$available_tabs = array(
			'uncanny-automator-recipe-log',
			'uncanny-automator-trigger-log',
			'uncanny-automator-action-log',
			'uncanny-automator-activity-log'
		);
		if ( isset( $_GET['page'] ) && in_array( $_GET['page'], $available_tabs ) ) {
			$current_tab = str_replace( 'uncanny-automator-', '', sanitize_text_field( $_GET['page'] ) );
		}
		?>
        <div class="wrap uap">
            <nav class="nav-tab-wrapper uap-nav-tab-wrapper">
                <a href="<?php echo admin_url( 'edit.php' ) ?>?post_type=uo-recipe&page=uncanny-automator-recipe-log"
                   class="nav-tab <?php echo ( 'recipe-log' == $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php echo __( 'Recipe log', 'uncanny-automator' ); ?>
                </a>
                <a href="<?php echo admin_url( 'edit.php' ) ?>?post_type=uo-recipe&page=uncanny-automator-trigger-log"
                   class="nav-tab <?php echo ( 'trigger-log' == $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php echo __( 'Trigger log', 'uncanny-automator' ); ?>
                </a>
                <a href="<?php echo admin_url( 'edit.php' ) ?>?post_type=uo-recipe&page=uncanny-automator-action-log"
                   class="nav-tab <?php echo ( 'action-log' == $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php echo __( 'Action log', 'uncanny-automator' ); ?>
                </a>
            </nav>
            <section class="uap-logs">
                <div class="uap-log-table-container">
					<?php

					switch ( $current_tab ) {

						case 'recipe-log':
							$headings = array(
								/* translators: Log column. */
								'recipe_type'      => __( 'Recipe type', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_title'     => __( 'Recipe', 'uncanny-automator' ),
								/* translators: Log column. The recipe status */
								'recipe_completed' => __( 'Status', 'uncanny-automator' ),
								/* translators: Log column. The recipe completion date */
								'recipe_date_time' => __( 'Completion date', 'uncanny-automator' ),
								//'display_name'=>__( 'User Name', 'uncanny-automator' ),
								/* translators: Log column. Noun. The recipe iteration */
								'run_number'       => __( 'Run #', 'uncanny-automator' ),
								/* translators: Log column. */
								'display_name'     => __( 'User', 'uncanny-automator' ), 
							);

							$sortables = array(
								//'recipe_type'      => array( 'recipe_type', true ),
								'recipe_title'     => array( 'recipe_title', true ),
								'recipe_date_time' => array( 'recipe_date_time', true ),
								'display_name'     => array( 'display_name', true ),
								//'user_email'=>array('user_email',true), // linked
								'recipe_completed' => array( 'recipe_completed', true ),
								'run_number'       => array( 'run_number', true ),

							);

							//Prepare Table of elements
							$wp_list_table = new Logs_List_Table();
							$wp_list_table->set_columns( $headings );
							$wp_list_table->set_sortable_columns( $sortables );
							$wp_list_table->set_tab( $current_tab );
							$wp_list_table->prepare_items();
							$wp_list_table->display();

							break;

						case 'trigger-log':
							$headings = array(
								/* translators: Log column. */
								'trigger_title'     => __( 'Trigger', 'uncanny-automator' ),
								/* translators: Log column. The trigger completion date */
								'trigger_date'      => __( 'Completion date', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_title'      => __( 'Recipe', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_completed'  => __( 'Recipe status', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_date_time'  => __( 'Recipe completion date', 'uncanny-automator' ),
								/* translators: Log column. Noun. The recipe iteration */
								'recipe_run_number' => __( 'Recipe run #', 'uncanny-automator' ),
								/* translators: Log column. */
								'display_name'      => __( 'User', 'uncanny-automator' ),
							);

							$sortables = array(
								'trigger_title'     => array( 'trigger_title', true ),
								'trigger_date'      => array( 'trigger_date', true ),
								'recipe_title'      => array( 'recipe_title', true ),
								'recipe_completed'  => array( 'recipe_completed', true ), // linked
								'recipe_date_time'  => array( 'recipe_date_time', true ),
								'recipe_run_number' => array( 'recipe_run_number', true ),
								'display_name'      => array( 'display_name', true ),

							);

							//Prepare Table of elements
							$wp_list_table = new Logs_List_Table();
							$wp_list_table->set_columns( $headings );
							$wp_list_table->set_sortable_columns( $sortables );
							$wp_list_table->set_tab( $current_tab );
							$wp_list_table->prepare_items();
							$wp_list_table->display();
							break;

						case 'action-log':
							$headings = array(
								/* translators: Log column. */
								'action_title'      => __( 'Action', 'uncanny-automator' ),
								/* translators: Log column. The action completion date */
								'action_date'       => __( 'Completion date', 'uncanny-automator' ),
								/* translators: Log column. The action status */
								'action_completed'  => __( 'Status', 'uncanny-automator' ),
								/* translators: Log column. */
								'error_message'     => __( 'Notes', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_title'      => __( 'Recipe', 'uncanny-automator' ), 
								/* translators: Log column. */
								'recipe_completed'  => __( 'Recipe status', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_date_time'  => __( 'Recipe completion date', 'uncanny-automator' ),
								/* translators: Log column. Noun. The recipe iteration */
								'recipe_run_number' => __( 'Recipe run #', 'uncanny-automator' ),
								/* translators: Log column. */
								'display_name'      => __( 'User', 'uncanny-automator' ), // linked

							);

							$sortables = array(
								'action_title'      => array( 'action_title', true ),
								'action_date'       => array( 'action_date', true ),
								'action_completed'  => array( 'action_completed', true ),
								'error_message'     => array( 'error_message', true ),
								'recipe_title'      => array( 'recipe_title', true ),
								'recipe_completed'  => array( 'recipe_completed', true ), // linked
								'recipe_date_time'  => array( 'recipe_date_time', true ),
								'recipe_run_number' => array( 'recipe_run_number', true ),
								'display_name'      => array( 'display_name', true ),

							);

							//Prepare Table of elements
							$wp_list_table = new Logs_List_Table();
							$wp_list_table->set_columns( $headings );
							$wp_list_table->set_sortable_columns( $sortables );
							$wp_list_table->set_tab( $current_tab );
							$wp_list_table->prepare_items();
							$wp_list_table->display();
							break;

						case 'activity-log':
							break;

						default:
							break;
					}

					?>
                </div>
            </section>
        </div>
		<?php
	}

}