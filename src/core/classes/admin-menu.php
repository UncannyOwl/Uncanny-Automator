<?php

namespace Uncanny_Automator;


/**
 * Class Admin_Menu
 * @package Uncanny_Automator
 */
class Admin_Menu {

	/**
	 * Setting Page title
	 * @var
	 */
	public $settings_page_slug;

	/**
	 * @var array
	 */
	public static $tabs = [];

	/**
	 * class constructor
	 */
	public function __construct() {

		// Setup Theme Options Page Menu in Admin
		if ( is_admin() ) {


			add_action( 'admin_init', array( $this, 'plugins_loaded' ), 1 );
			add_action( 'admin_menu', array( $this, 'register_options_menu_page' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ) );
			// add_action( 'admin_enqueue_scripts', array( $this, 'scripts' ), 2 );
			//add_action( 'admin_footer', [ $this, 'override_pro_filters' ] );
			//add_action( 'admin_init', array( $this, 'uap_automator_register_option' ), 999 );
		}
	}

	/**
	 *
	 */
	public function plugins_loaded() {
		$tabs = [
			'settings' => [
				'name'        => esc_attr__( 'Settings', 'uncanny_automator' ),
				'title'       => esc_attr__( 'Auto-prune activity logs', 'uncanny-automator' ),
				'description' => esc_attr__( 'Enter a number of days below to have trigger and action log entries older than the specified number of days automatically deleted from your site daily. Trigger and action log entries will only be deleted for recipes with "Completed" status.', 'uncanny-automator' ),
				'is_pro'      => true,
				'fields'      => [ /* see implementation in pro*/ ],
			],
		];

		self::$tabs = apply_filters( 'uap_settings_tabs', $tabs );
		if ( self::$tabs ) {
			$tabs = json_decode( json_encode( self::$tabs ), false );
			foreach ( $tabs as $tab => $tab_settings ) {
				if ( $tab_settings->fields ) {
					foreach ( $tab_settings->fields as $field_id => $field_settings ) {
						$args = isset( $field_settings->field_args ) ? $field_settings->field_args : [];
						if ( empty( $args ) ) {
							register_setting( $tab_settings->settings_field, $field_id );
						} else {
							register_setting( $tab_settings->settings_field, $field_id, $args );
						}
					}
				}
			}
		}
	}

	/**
	 * TODO: Remove this function after pro 2.1.1 release
	 * @deprecated v2.3
	 *
	 */
	public function override_pro_filters() {
		if ( defined( 'AUTOMATOR_PRO_FILE' ) || class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
			$pro_version = \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;
			if ( $pro_version > 2.1 ) {
				return;
			}

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

		if ( 'uo-recipe_page_uncanny-automator-settings' === (string) $hook ) {
			Utilities::enqueue_global_assets();
			// Automator assets.
			wp_enqueue_style( 'uap-admin-settings', Utilities::get_css( 'admin/performance.css' ), array(), Utilities::get_version() );
		}
	}

	/**
	 * Create Plugin options menu
	 */
	public function register_options_menu_page() {
		$parent_slug              = 'edit.php?post_type=uo-recipe';
		$this->settings_page_slug = $parent_slug;
		$function                 = array( $this, 'logs_options_menu_page_output' );
		add_submenu_page( $parent_slug, esc_attr__( 'Recipe log', 'uncanny-automator' ), esc_attr__( 'Recipe log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-recipe-log', $function );
		add_submenu_page( $parent_slug, esc_attr__( 'Trigger log', 'uncanny-automator' ), esc_attr__( 'Trigger log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-trigger-log', $function );
		add_submenu_page( $parent_slug, esc_attr__( 'Action log', 'uncanny-automator' ), esc_attr__( 'Action log', 'uncanny-automator' ), 'manage_options', 'uncanny-automator-action-log', $function );

		if ( defined( 'AUTOMATOR_PRO_FILE' ) || class_exists( '\Uncanny_Automator_Pro\InitializePlugin' ) ) {
			$pro_version = \Uncanny_Automator_Pro\InitializePlugin::PLUGIN_VERSION;
			if ( version_compare( $pro_version, '2.3', '<' ) ) {
				return;
			}
		}
		/* translators: 1. Trademarked term */
		$page_title               = sprintf( esc_attr__( '%1$s settings', 'uncanny-automator' ), 'Uncanny Automator' );
		$capability               = 'manage_options';
		$menu_title               = esc_attr__( 'Settings', 'uncanny-automator' );
		$menu_slug                = 'uncanny-automator-settings';
		$this->settings_page_slug = $menu_slug;
		$function                 = array( $this, 'options_menu_settings_page_output' );

		add_submenu_page( 'edit.php?post_type=uo-recipe', $page_title, $menu_title, $capability, $menu_slug, $function );

	}

	/**
	 * Create Page view
	 */
	public function logs_options_menu_page_output() {
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
		// Set default query for list table.
		if ( ! isset( $_GET['order'] ) && ! isset( $_GET['orderby'] ) ) {
			$_GET['order'] = 'desc';
			if ( 'recipe-log' === $current_tab ) {
				$_GET['orderby'] = 'recipe_date_time';
			} elseif ( 'trigger-log' === $current_tab ) {
				$_GET['orderby'] = 'trigger_date';
			} elseif ( 'action-log' === $current_tab ) {
				$_GET['orderby'] = 'action_date';
			}
		}
		?>
        <div class="wrap uap">
            <nav class="nav-tab-wrapper uap-nav-tab-wrapper">
                <a href="<?php echo admin_url( 'edit.php' ) ?>?post_type=uo-recipe&page=uncanny-automator-recipe-log"
                   class="nav-tab <?php echo ( 'recipe-log' == $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_attr__( 'Recipe log', 'uncanny-automator' ); ?>
                </a>
                <a href="<?php echo admin_url( 'edit.php' ) ?>?post_type=uo-recipe&page=uncanny-automator-trigger-log"
                   class="nav-tab <?php echo ( 'trigger-log' == $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_attr__( 'Trigger log', 'uncanny-automator' ); ?>
                </a>
                <a href="<?php echo admin_url( 'edit.php' ) ?>?post_type=uo-recipe&page=uncanny-automator-action-log"
                   class="nav-tab <?php echo ( 'action-log' == $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_attr__( 'Action log', 'uncanny-automator' ); ?>
                </a>
            </nav>
            <section class="uap-logs">
                <div class="uap-log-table-container">
					<?php

					switch ( $current_tab ) {

						case 'recipe-log':
							$headings = array(
								/* translators: Log column. */
								'recipe_type'      => esc_attr__( 'Recipe type', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_title'     => esc_attr__( 'Recipe', 'uncanny-automator' ),
								/* translators: Log column. The recipe status */
								'recipe_completed' => esc_attr__( 'Status', 'uncanny-automator' ),
								/* translators: Log column. The recipe completion date */
								'recipe_date_time' => esc_attr__( 'Completion date', 'uncanny-automator' ),
								//'display_name'=> esc_attr__( 'User Name', 'uncanny-automator' ),
								/* translators: Log column. Noun. The recipe iteration */
								'run_number'       => esc_attr__( 'Run #', 'uncanny-automator' ),
								/* translators: Log column. */
								'display_name'     => esc_attr__( 'User', 'uncanny-automator' ),
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
								'trigger_title'      => esc_attr__( 'Trigger', 'uncanny-automator' ),
								/* translators: Log column. The trigger completion date */
								'trigger_date'       => esc_attr__( 'Completion date', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_title'       => esc_attr__( 'Recipe', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_completed'   => esc_attr__( 'Recipe status', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_date_time'   => esc_attr__( 'Recipe completion date', 'uncanny-automator' ),
								/* translators: Log column. Noun. The recipe iteration */
								'recipe_run_number'  => esc_attr__( 'Recipe run #', 'uncanny-automator' ),
								/* translators: Log column. Noun. The trigger iteration */
								'trigger_run_number' => esc_attr__( 'Trigger run #', 'uncanny-automator' ),
								/* translators: Log column. */
								'display_name'       => esc_attr__( 'User', 'uncanny-automator' ),
							);

							$sortables = array(
								'trigger_title'      => array( 'trigger_title', true ),
								'trigger_date'       => array( 'trigger_date', true ),
								'recipe_title'       => array( 'recipe_title', true ),
								'recipe_completed'   => array( 'recipe_completed', true ), // linked
								'recipe_date_time'   => array( 'recipe_date_time', true ),
								'recipe_run_number'  => array( 'recipe_run_number', true ),
								'trigger_run_number' => array( 'trigger_run_number', false ),
								'display_name'       => array( 'display_name', true ),

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
								'action_title'      => esc_attr__( 'Action', 'uncanny-automator' ),
								/* translators: Log column. The action completion date */
								'action_date'       => esc_attr__( 'Completion date', 'uncanny-automator' ),
								/* translators: Log column. The action status */
								'action_completed'  => esc_attr__( 'Status', 'uncanny-automator' ),
								/* translators: Log column. */
								'error_message'     => esc_attr__( 'Notes', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_title'      => esc_attr__( 'Recipe', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_completed'  => esc_attr__( 'Recipe status', 'uncanny-automator' ),
								/* translators: Log column. */
								'recipe_date_time'  => esc_attr__( 'Recipe completion date', 'uncanny-automator' ),
								/* translators: Log column. Noun. The recipe iteration */
								'recipe_run_number' => esc_attr__( 'Recipe run #', 'uncanny-automator' ),
								/* translators: Log column. */
								'display_name'      => esc_attr__( 'User', 'uncanny-automator' ), // linked

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

	/**
	 *
	 */
	public function options_menu_settings_page_output() {
		$this->settings_tabs();
		include( Utilities::get_include( 'automator-settings.php' ) );
	}

	/**
	 * @param string $current
	 */
	public function settings_tabs( $current = 'settings' ) {

		//self::$tabs = apply_filters( 'uap_settings_tabs', self::$tabs );
		$tabs = json_decode( json_encode( self::$tabs ), false );
		if ( isset( $_GET['tab'] ) ) {
			$current = esc_html( $_GET['tab'] );
		}

		if ( $tabs ) {
			$html = '<h2 class="nav-tab-wrapper">';
			foreach ( $tabs as $tab => $tab_settings ) {
				$class = ( (string) $tab === (string) $current ) ? 'nav-tab-active' : '';
				$url   = admin_url( 'edit.php' ) . '?post_type=uo-recipe&page=uncanny-automator-settings';
				$html  .= '<a class="nav-tab ' . $class . '" href="' . $url . '&tab=' . $tab . '">' . $tab_settings->name . '</a>';
			}
			$html .= '</h2>';
			echo $html;
		}
	}
}