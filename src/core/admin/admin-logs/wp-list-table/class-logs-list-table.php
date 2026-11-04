<?php

namespace Uncanny_Automator;

use Uncanny_Automator\App\Infrastructure\Database\Database;
use Uncanny_Automator_Pro\Pro_Filters;
use WP_List_Table;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . '/wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Logs_List_Table
 *
 * @todo Move query to Traits DB.
 * @todo Refactor long methods.
 * @todo Convert magic numbers to class constant. (done)
 * @todo Refactor formatters logic
 * @todo Possibly cover with Unit-tests.
 *
 * @package Uncanny_Automator
 */
class Logs_List_Table extends WP_List_Table {

	/**
	 * The current tab.
	 *
	 * @var
	 */
	public $tab;

	/**
	 * $column_list     ARRAY| Setting table heading/columns
	 */
	private $column_list;

	/**
	 * $sortable_columns     ARRAY| Setting sortable table heading/columns
	 */
	private $sortable_columns;
	/**
	 * Integration codes per recipe id, read once per page.
	 *
	 * @var array<int, string[]>
	 */
	private $recipe_integration_codes = array();

	/**
	 * Class constructor.
	 */
	public function __construct() {

		parent::__construct(
			array(
				'singular' => 'wp_list_logs_link',
				'plural'   => 'wp_list_logs_links',
				'ajax'     => false,
			)
		);

		add_filter( 'automator_action_log_error', array( self::class, 'format_all_upgrade_links' ), 10, 2 );
	}

	/**
	 * Setting tab
	 *
	 * @param string $tab
	 */
	public function set_tab( $tab ) {
		$this->tab = $tab;
	}

	/**
	 * Setting columns
	 *
	 * @param array $columns
	 */
	public function set_columns( $columns ) {
		$this->column_list = $columns;
	}

	/**
	 * @param $provider
	 * @param $tab
	 *
	 * @return mixed|string
	 */
	private function get_query_provider( $provider = '', $tab = 'recipe-log' ) {

		$tabs_query = array(
			'recipe-log'      => $this->get_recipe_query(),
			'recipe-activity' => $this->get_recipe_query(),
			'trigger-log'     => $this->get_trigger_query(),
			'action-log'      => $this->get_action_query(),
		);

		if ( 'pro' === $provider ) {
			$tabs_query = array(
				'recipe-log'      => Pro_Filters::get_recipe_query(),
				'recipe-activity' => Pro_Filters::get_recipe_query(),
				'trigger-log'     => Pro_Filters::get_trigger_query(),
				'action-log'      => Pro_Filters::get_action_query(),
			);
		}

		// Allow others to hook and extend.
		$tabs_query = apply_filters( 'automator_class_list_table_get_query_adapter', $tabs_query, $this );

		// Allow others to run code after query composition.
		do_action( 'automator_class_list_table_get_query_adapter_after', $this );

		return isset( $tabs_query[ $tab ] ) ? $tabs_query[ $tab ] : '';
	}

	/**
	 * @return mixed|string
	 */
	private function get_query() {
		return class_exists( '\Uncanny_Automator_Pro\Pro_Filters' ) ?
			$this->get_query_provider( 'pro', $this->tab ) : // <-- Pro
			$this->get_query_provider( null, $this->tab ); //<-- Free
	}

	/**
	 * Prepare items/data
	 */
	public function prepare_items() {

		global $wpdb, $_wp_column_headers;

		$hidden   = array();
		$screen   = get_current_screen();
		$columns  = $this->get_columns();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Get the query.
		$query = $this->get_query();

		/* -- Ordering parameters -- */
		// Disallows any other inputs aside from 'asc', and 'desc'. Sanitizes the order input.
		$order = $this->sanitize_order( automator_filter_input( 'order' ) );

		if ( 'recipe-log' === $this->tab ) {
			$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? automator_filter_input( 'orderby' ) : 'recipe_date_time';
		} elseif ( 'recipe-activity' === $this->tab ) {
			$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? automator_filter_input( 'orderby' ) : 'recipe_date_time';
		} elseif ( 'trigger-log' === $this->tab ) {
			$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? automator_filter_input( 'orderby' ) : 'trigger_date';
		} elseif ( 'action-log' === $this->tab ) {
			$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? automator_filter_input( 'orderby' ) : 'action_date';
		}

		// Disallows other inputs aside from available columns. Also, sanitizes the input properly.
		$orderby = $this->sanitize_order_by( $orderby, array_keys( $columns ) );

		if ( ! empty( $query ) && ! empty( $orderby ) && ! empty( $order ) ) {

			$query .= ' ORDER BY ' . $orderby . ' ' . $order . ', recipe_log_id DESC'; // Added recipe ID in case the recipe log has equal dates.

		}

		/* -- Pagination parameters -- */
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom log views; live state read. The query is built in get_query() from escaped values, a whitelisted ORDER BY and an int-cast LIMIT.
		$total_items = $wpdb->query( $query );

		$perpage = 100;

		$paged = $this->get_pagenum();

		if ( empty( $paged ) || ! is_numeric( $paged ) || $paged <= 0 ) {
			$paged = 1;
		}

		$totalpages = ceil( $total_items / $perpage ); //Adjust the query to take pagination into account.

		if ( ! empty( $paged ) && ! empty( $perpage ) ) {
			$offset = ( $paged - 1 ) * $perpage;
			$query .= ' LIMIT ' . (int) $offset . ',' . $perpage;
		}

		/* -- Register the pagination -- */
		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'total_pages' => $totalpages,
				'per_page'    => $perpage,
			)
		);

		/* -- Register the Columns -- */
		$columns = $this->get_columns();

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Core's list-table column headers global, set as WP_List_Table does.
		$_wp_column_headers[ $screen->id ] = $columns;

		/* -- Fetch the items -- */
		if ( 'recipe-log' === $this->tab ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom log views; live state read. The query is built in get_query() from escaped values, a whitelisted ORDER BY and an int-cast LIMIT.
			$recipes = $wpdb->get_results( $query );
			_prime_post_caches( wp_parse_id_list( array_column( $recipes, 'automator_recipe_id' ) ), false, true );
			$this->items = $this->format_recipe_data( $recipes );
		} elseif ( 'recipe-activity' === $this->tab ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom log views; live state read. The query is built in get_query() from escaped values, a whitelisted ORDER BY and an int-cast LIMIT.
			$this->items = $this->format_recipe_activity_data( $wpdb->get_results( $query ) );
		} elseif ( 'trigger-log' === $this->tab ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom log views; live state read. The query is built in get_query() from escaped values, a whitelisted ORDER BY and an int-cast LIMIT.
			$triggers = $wpdb->get_results( $query );
			_prime_post_caches( wp_parse_id_list( array_merge( array_column( $triggers, 'automator_recipe_id' ), array_column( $triggers, 'automator_trigger_id' ) ) ), false, true );
			$this->items = $this->format_trigger_data( $triggers );
		} elseif ( 'action-log' === $this->tab ) {
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- Custom log views; live state read. The query is built in get_query() from escaped values, a whitelisted ORDER BY and an int-cast LIMIT.
			$actions = $wpdb->get_results( $query );
			_prime_post_caches( wp_parse_id_list( array_merge( array_column( $actions, 'automator_recipe_id' ), array_column( $actions, 'automator_action_id' ) ) ), false, true );
			$this->items = $this->format_action_data( $actions );
		}
	}

	/**
	 * Getting columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return $this->column_list;
	}

	/**
	 * Override method
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return $this->sortable_columns;
	}

	/**
	 * Setter function
	 *
	 * @param array $sortable_columns list of columns
	 */
	public function set_sortable_columns( $sortable_columns ) {
		$this->sortable_columns = $sortable_columns;
	}

	/**
	 * Prepare query for recipe
	 *
	 * @return string query
	 */
	private function get_recipe_query() {
		global $wpdb;
		$view_exists = Automator_DB::is_view_exists();

		if ( ! $view_exists ) {
			$search_conditions = ' 1=1 AND r.completed != -1 ';
		} else {
			$search_conditions = ' 1=1 AND completed != -1 ';
		}
		if ( automator_filter_has_var( 'search_key' ) && '' !== automator_filter_input( 'search_key' ) ) {
			$search_like = '%' . $wpdb->esc_like( sanitize_text_field( automator_filter_input( 'search_key' ) ) ) . '%';
			if ( ! $view_exists ) {
				$search_conditions .= $wpdb->prepare( ' AND ( (p.recipe_title LIKE %s) OR (u.display_name  LIKE %s ) OR (u.user_email  LIKE %s ) ) ', $search_like, $search_like, $search_like );
			} else {
				$search_conditions .= $wpdb->prepare( ' AND ( (recipe_title LIKE %s) OR (display_name  LIKE %s ) OR (user_email  LIKE %s ) ) ', $search_like, $search_like, $search_like );
			}
		}

		if ( automator_filter_has_var( 'recipe_id' ) && '' !== automator_filter_input( 'recipe_id' ) ) {
			if ( ! $view_exists ) {
				$search_conditions .= " AND r.automator_recipe_id = '" . absint( automator_filter_input( 'recipe_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND automator_recipe_id = '" . absint( automator_filter_input( 'recipe_id' ) ) . "' ";
			}
		}

		if ( automator_filter_has_var( 'recipe_log_id' ) && '' !== automator_filter_input( 'recipe_log_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND recipe_log_id = '" . absint( automator_filter_input( 'recipe_log_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND r.ID = '" . absint( automator_filter_input( 'recipe_log_id' ) ) . "' ";
			}
		}

		if ( $view_exists ) {
			return "SELECT * FROM {$wpdb->prefix}uap_recipe_logs_view WHERE $search_conditions";
		} else {
			$sql = Automator_DB::recipe_log_view_query();

			return "$sql WHERE $search_conditions";
		}
	}

	/**
	 * Prepare query for triggers
	 *
	 * @return string query
	 */
	private function get_trigger_query() {
		global $wpdb;
		$view_exists       = Automator_DB::is_view_exists( 'trigger' );
		$search_conditions = ' 1=1 ';
		if ( automator_filter_has_var( 'search_key' ) && '' !== automator_filter_input( 'search_key' ) ) {
			$search_like = '%' . $wpdb->esc_like( sanitize_text_field( automator_filter_input( 'search_key' ) ) ) . '%';
			if ( $view_exists ) {
				$search_conditions .= $wpdb->prepare( ' AND ( (recipe_title LIKE %s) OR (trigger_title LIKE %s) OR (display_name  LIKE %s ) OR (user_email  LIKE %s ) ) ', $search_like, $search_like, $search_like, $search_like );
			} else {
				$search_conditions .= $wpdb->prepare( ' AND ( (p.post_title LIKE %s) OR (pt.post_title LIKE %s) OR (u.display_name  LIKE %s ) OR (u.user_email  LIKE %s ) ) ', $search_like, $search_like, $search_like, $search_like );

			}
		}

		if ( automator_filter_has_var( 'recipe_id' ) && '' !== automator_filter_input( 'recipe_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND automator_recipe_id = '" . absint( automator_filter_input( 'recipe_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND t.automator_recipe_id = '" . absint( automator_filter_input( 'recipe_id' ) ) . "' ";
			}
		}

		if ( automator_filter_has_var( 'recipe_log_id' ) && '' !== automator_filter_input( 'recipe_log_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND recipe_log_id = '" . absint( automator_filter_input( 'recipe_log_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND t.automator_recipe_log_id = '" . absint( automator_filter_input( 'recipe_log_id' ) ) . "' ";
			}
		}

		if ( automator_filter_has_var( 'run_number' ) && '' !== automator_filter_input( 'run_number' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND recipe_run_number = '" . absint( automator_filter_input( 'run_number' ) ) . "' ";
			} else {
				$search_conditions .= " AND r.run_number = '" . absint( automator_filter_input( 'run_number' ) ) . "' ";
			}
		}
		if ( automator_filter_has_var( 'user_id' ) && '' !== automator_filter_input( 'user_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND user_id = '" . absint( automator_filter_input( 'user_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND u.ID = '" . absint( automator_filter_input( 'user_id' ) ) . "' ";
			}
		}
		if ( $view_exists ) {
			return "SELECT * FROM {$wpdb->prefix}uap_trigger_logs_view WHERE ($search_conditions) ";
		} else {
			$sql = Automator_DB::trigger_log_view_query();
			$sql = str_ireplace( 'GROUP BY a.ID', '', $sql );

			return "$sql WHERE ($search_conditions)";
		}
	}

	/**
	 * Prepare query for actions
	 *
	 * @return string query
	 */
	private function get_action_query() {
		global $wpdb;
		$view_exists       = Automator_DB::is_view_exists( 'action' );
		$search_conditions = ' 1=1 ';
		if ( automator_filter_has_var( 'search_key' ) && '' !== automator_filter_input( 'search_key' ) ) {
			$search      = sanitize_text_field( automator_filter_input( 'search_key' ) );
			$search_like = '%' . $wpdb->esc_like( $search ) . '%';
			if ( $view_exists ) {
				$notes_match        = Database::get_action_error_store()->notes_search_sql( "{$wpdb->prefix}uap_action_logs_view.action_log_id", "{$wpdb->prefix}uap_action_logs_view.error_message", $search );
				$search_conditions .= $wpdb->prepare( ' AND ( (recipe_title LIKE %s) OR (action_title LIKE %s) OR (display_name LIKE %s ) OR (user_email LIKE %s ) OR ', $search_like, $search_like, $search_like, $search_like ) . $notes_match . ' ) ';
			} else {
				$notes_match        = Database::get_action_error_store()->notes_search_sql( 'a.ID', 'a.error_message', $search );
				$search_conditions .= $wpdb->prepare( ' AND ( (p.post_title LIKE %s) OR (pa.post_title LIKE %s) OR (u.display_name LIKE %s ) OR (u.user_email LIKE %s ) OR ', $search_like, $search_like, $search_like, $search_like ) . $notes_match . ' ) ';
			}
		}

		if ( automator_filter_has_var( 'recipe_id' ) && '' !== automator_filter_input( 'recipe_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND automator_recipe_id = '" . absint( automator_filter_input( 'recipe_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND a.automator_recipe_id = '" . absint( automator_filter_input( 'recipe_id' ) ) . "' ";
			}
		}

		if ( automator_filter_has_var( 'recipe_log_id' ) && '' !== automator_filter_input( 'recipe_log_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND recipe_log_id = '" . absint( automator_filter_input( 'recipe_log_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND a.automator_recipe_log_id = '" . absint( automator_filter_input( 'recipe_log_id' ) ) . "' ";
			}
		}

		if ( automator_filter_has_var( 'run_number' ) && '' !== automator_filter_input( 'run_number' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND recipe_run_number = '" . absint( automator_filter_input( 'run_number' ) ) . "' ";
			} else {
				$search_conditions .= " AND r.run_number = '" . absint( automator_filter_input( 'run_number' ) ) . "' ";
			}
		}

		if ( automator_filter_has_var( 'user_id' ) && '' !== automator_filter_input( 'user_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND user_id = '" . absint( automator_filter_input( 'user_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND u.ID = '" . absint( automator_filter_input( 'user_id' ) ) . "' ";
			}
		}
		if ( $view_exists ) {
			return "SELECT * FROM {$wpdb->prefix}uap_action_logs_view WHERE ($search_conditions)";
		} else {
			$sql = Automator_DB::action_log_view_query( false );

			return "$sql WHERE ($search_conditions) GROUP BY a.ID";
		}
	}

	/**
	 * Format recipes log data
	 *
	 * @param array $recipes list of objects
	 *
	 * @return array
	 */
	private function format_recipe_data( $recipes ) {

		$data = array();
		foreach ( $recipes as $recipe ) {
			$recipe_log_id = $recipe->recipe_log_id;

			$recipe_link = '#';
			$recipe_id   = 0;

			if ( isset( $recipe->automator_recipe_id ) ) {
				$recipe_link = $this->get_edit_link( absint( $recipe->automator_recipe_id ) );
				$recipe_id   = $recipe->automator_recipe_id;
			}

			// Recipe name.
			$recipe_name = ! empty( $recipe->recipe_title )
				? $recipe->recipe_title
				// translators: %1$s: Recipe ID
				: sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $recipe_id );

			if ( '#' !== $recipe_link ) {
				//$recipe_name = '<a href="' . esc_url( $recipe_link ) . '" class="uap-log-table__recipe-name">' . esc_html( $recipe_name ) . '</a>';
				$recipe_name = $this->build_recipe_title_with_icons( $recipe_id, $recipe_link, esc_html( $recipe_name ), 'uap-log-table__recipe-name', $recipe );
			}

			// User
			$user_name = $this->get_user_html( $recipe );

			// Recipe status
			$recipe_status_class = sprintf( 'uap-logs-recipe-status--%s', $this->get_status_class_name( $recipe->recipe_completed ) );

			$replay_badge = $this->get_replay_badge( $recipe->recipe_log_id );

			$recipe_status = '
				<div class="uap-logs-recipe-status ' . sanitize_html_class( $recipe_status_class ) . '">' .
					esc_html( $this->get_status_name( (int) $recipe->recipe_completed ) ) .
					$replay_badge . '
				</div>';

			// Recipe complation date
			$recipe_date_completed = in_array(
				absint( $recipe->recipe_completed ),
				array(
					Automator_Status::COMPLETED,
					Automator_Status::COMPLETED_WITH_ERRORS,
					Automator_Status::DID_NOTHING,
					Automator_Status::COMPLETED_WITH_NOTICE,
				),
				true
			) ?
				$recipe->recipe_date_time :
				'';

			$recipe_date_html = $this->get_completion_date_html( $recipe_date_completed, 'recipe' );

			if ( false !== $recipe_date_html ) {
				$recipe_date_completed = $recipe_date_html;
			}

			$current_type = Automator()->utilities->get_recipe_type( $recipe_id );

			$run_number = '';

			// Only show run numbers for non errors non in progress, or status with zero.
			$status_list = array(
				Automator_Status::NOT_COMPLETED,
			);

			$recipe_status_not_in_list = ! in_array( absint( $recipe->recipe_completed ), $status_list, true );

			if ( $recipe_status_not_in_list ) {

				// Run #.
				$run_number = 'anonymous' === $current_type ? '&nbsp;' : $recipe->run_number;

				// Run # when it is a anonymous recipe.
				if ( 'anonymous' === $current_type ) {
					$run_number = $this->format_recipe_run_number( $recipe, $run_number, 'user-slash' );
				}

				// Run # when it is a logged-in recipe
				if ( is_numeric( $run_number ) ) {
					$run_number = $this->format_recipe_run_number( $recipe, $run_number, 'user' );
				}
			}

			/* translators: Recipe type. Logged-in recipes are triggered only by logged-in users */
			$recipe_type_name = esc_attr_x( 'Logged-in', 'Recipe', 'uncanny-automator' );

			if ( ! empty( $current_type ) ) {
				if ( 'user' === $current_type ) {
					/* translators: Recipe type. Logged-in recipes are triggered only by logged-in users */
					$recipe_type_name = esc_attr_x( 'Logged-in', 'Recipe', 'uncanny-automator' );
				} elseif ( 'anonymous' === $current_type ) {
					/* translators: Recipe type. Anonymous recipes can be triggered by logged-in or anonymous users. Anonymous recipes can create new users or modify existing users. */
					$recipe_type_name = esc_attr_x( 'Anonymous', 'Recipe', 'uncanny-automator' );
				}
			}

			$run_number_log = 'anonymous' === $current_type ? 0 === absint( $recipe->run_number ) ? 1 : $recipe->run_number : $recipe->run_number;

			$delete_url = sprintf(
				'%s?post_type=%s&page=%s&recipe_id=%d&run_number=%d&recipe_log_id=%d&delete_specific_activity=1&wpnonce=' . wp_create_nonce( AUTOMATOR_FREE_ITEM_NAME ),
				admin_url( 'edit.php' ),
				AUTOMATOR_POST_TYPE_RECIPE,
				'uncanny-automator-admin-logs',
				$recipe_id,
				$run_number_log,
				absint( $recipe_log_id )
			);

			// Per-row "Details" link. Just an anchor that writes the hash route; the page-level <uap-f-recipe-log> picks it up and opens its dialog.
			$actions = array(
				'view' => sprintf(
					'<uas-button color="secondary" size="xs" href="%1$s">%2$s</uas-button>',
					esc_attr( '#/recipe-log/recipe/' . absint( $recipe_id ) . '/run/' . absint( $run_number_log ) . '/log/' . absint( $recipe_log_id ) ),
					esc_html__( 'Details', 'uncanny-automator' )
				),
			);

			// Delete button. The custom element owns the tooltip, icon, and confirmation copy; PHP only passes the nonced URL.
			if ( true === apply_filters( 'automator_allow_users_to_delete_in_progress_recipe_runs', true, $recipe_id ) ) {
				$actions['delete'] = sprintf(
					'<uap-f-recipe-log-btn-delete log-delete-url="%s"></uap-f-recipe-log-btn-delete>',
					esc_attr( esc_url( $delete_url ) )
				);
			}

			// Layout
			$actions = '<div class="uap-logs-action-buttons">' . ( isset( $actions['delete'] ) ? $actions['delete'] : '' ) . $actions['view'] . '</div>';

			$data[] = array(
				'recipe_type'            => $recipe_type_name,
				'recipe_title'           => $recipe_name,
				'recipe_date_time'       => $recipe_date_completed,
				'display_name'           => $user_name,
				'recipe_completed'       => $recipe_status,
				'recipe_class_name'      => $this->get_status_class_name( $recipe->recipe_completed ),
				'recipe_class_name_type' => 'recipe',
				'run_number'             => $run_number,
				'actions'                => $actions, // Added.
			);
		}

		return $data;
	}

	/**
	 * Formats the recipe run number.
	 *
	 * @param int $recipe
	 * @param int $run_number
	 * @param string $icon
	 *
	 * @return string
	 */
	private function format_recipe_run_number( $recipe, $run_number, $icon ) {

		$log_number = $this->get_log_number( $recipe );

		// @TODO: Convert to CSS class.
		$style = 'justify-content:space-between;';

		if ( '' === $log_number ) {
			$style = 'justify-content:right;';
		}

		return sprintf(
			'<div style="%1$s" class="uap-logs-run-number">
				<div class="uap-logs-run-number--log-number">%2$s</div>
				<div class="uap-logs-run-number--user">
					<uas-icon code="%3$s" icon-style="solid" style="margin-top:2.5px;opacity: 0.75;"></uas-icon>
					%4$s
				</div>
			</div>',
			esc_attr( $style ),
			esc_html( $log_number ),
			esc_attr( $icon ),
			esc_html( $run_number )
		);
	}

	/**
	 * @param object $recipe
	 *
	 * @return string
	 */
	private function get_log_number( $recipe ) {
		return $recipe->log_number ?? '';
	}

	/**
	 * Get a "Replayed" badge if this run was replayed from another.
	 *
	 * @param int $recipe_log_id The recipe log ID.
	 *
	 * @return string HTML badge or empty string.
	 */
	private function get_replay_badge( $recipe_log_id ) {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$source = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM {$wpdb->prefix}uap_recipe_log_meta WHERE recipe_log_id = %d AND meta_key = 'replay_source' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				absint( $recipe_log_id )
			)
		);

		if ( empty( $source ) ) {
			return '';
		}

		return ' <span class="uap-logs-replay-badge" title="' . esc_attr__( 'Replayed', 'uncanny-automator' ) . '"><uas-icon code="repeat" style="font-size: 11px; opacity: 0.6;"></uas-icon></span>';
	}

	/**
	 * Format trigger log data
	 *
	 * @param array $triggers list of objects
	 *
	 * @return array
	 */
	private function format_trigger_data( $triggers ) {

		$data = array();

		foreach ( $triggers as $trigger ) {

			$trigger_code = $this->item_code( absint( $trigger->automator_trigger_id ) );

			$trigger_date_completed = $trigger->trigger_date;

			// Show trigger run time instead of the completion date.
			$trigger_date_html = $this->get_completion_date_html( $trigger->trigger_run_time, 'trigger' );

			if ( false !== $trigger_date_html ) {
				$trigger_date_completed = $trigger_date_html;
			}

			$current_type = Automator()->utilities->get_recipe_type( $trigger->automator_recipe_id );

			$run_number_log = 'anonymous' === $current_type ? 0 === absint( $trigger->recipe_run_number ) ? 1 : $trigger->recipe_run_number : $trigger->recipe_run_number;

			$actions = sprintf(
				'<uas-button color="secondary" size="xs" href="%1$s">%2$s</uas-button>',
				esc_attr( '#/recipe-log/recipe/' . absint( $trigger->automator_recipe_id ) . '/run/' . absint( $run_number_log ) . '/log/' . absint( $trigger->recipe_log_id ) ),
				esc_html__( 'Details', 'uncanny-automator' )
			);

			$recipe_link = $this->get_edit_link( absint( $trigger->automator_recipe_id ) );

			/* translators: 1: Post ID */
			$recipe_name = ! empty( $trigger->recipe_title ) ? $trigger->recipe_title : sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $trigger->automator_recipe_id );

			if ( ! empty( $recipe_link ) ) {
				//$recipe_name = '<a href="' . esc_url( $recipe_link ) . '" class="uap-log-table__recipe-name">' . esc_html( $recipe_name ) . '</a>';
				$recipe_name = $this->build_recipe_title_with_icons( $trigger->automator_recipe_id, $recipe_link, esc_html( $recipe_name ), 'uap-log-table__recipe-name', $trigger );
			}

			$recipe_status = '
				<div class="uap-logs-recipe-status uap-logs-recipe-status--' . sanitize_html_class( $this->get_status_class_name( $trigger->recipe_completed ) ) . '">' .
					esc_html( $this->get_status_name( $trigger->recipe_completed ) ) . '
				</div>';

			$recipe_date_completed = ( 1 === absint( $trigger->recipe_completed ) || 2 === absint( $trigger->recipe_completed ) || 9 === absint( $trigger->recipe_completed ) ) ? $trigger->recipe_date_time : '';

			// User
			$user_name = $this->get_user_html( $trigger );

			/* translators: 1. Trigger ID */
			$trigger_name = sprintf( esc_attr__( 'Trigger deleted: %1$s', 'uncanny-automator' ), $trigger->automator_trigger_id );

			$trigger_run_number  = ( 0 === absint( $trigger->trigger_run_number ) || empty( $trigger->trigger_run_number ) ) ? 1 : absint( $trigger->trigger_run_number );
			$trigger_total_times = ( 0 === absint( $trigger->trigger_total_times ) || empty( $trigger->trigger_total_times ) ) ? 1 : $trigger->trigger_total_times;

			$trigger_run_number_sentence = $trigger_total_times > 1 ?
				/* translators: The run number */
				sprintf( esc_html__( 'Run #: %1$d of %2$d', 'uncanny-automator' ), $trigger_run_number, $trigger_total_times ) :
				'';

			if ( $trigger_code ) {
				// get the trigger title
				$trigger_title = $trigger->trigger_title;
				// get the triggers completed sentence
				$trigger_sentence = $trigger->trigger_sentence;
				if ( empty( $trigger_title ) && ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
					/* translators: 1. Trademarked term */
					$trigger_name = sprintf( esc_attr__( '(Reactivate %1$s to view)', 'uncanny-automator' ), 'Uncanny Automator Pro' );
				} else {
					if ( empty( $trigger_sentence ) ) {
						$trigger_name = '<div class="uap-logs-table__item-main-sentence">' . esc_html( $trigger_title ) . '</div>';
					} else {
						$trigger_name  = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $trigger_sentence ) . '</div>';
						$trigger_name .= '<div class="uap-logs-table__item-secondary-sentence">' . esc_html( $trigger_title ) . '</div>';
					}
				}

				$trigger_name = '
					<div class="uap-logs-table-trigger">
						<div class="uap-logs-table__item-main-sentence uap-logs-table__trigger-name">' .
							$this->format_human_readable_sentence( $trigger_sentence ) .
						'</div>
						<div class="uap-logs-table__trigger-run-number">' .
								esc_html( $trigger_run_number_sentence ) . '
						</div>
					</div>';
			}

			$recipe_run_number = '&mdash;';

			if ( ! in_array(
				absint( $trigger->recipe_completed ),
				array(
					0,
					Automator_Status::COMPLETED_WITH_ERRORS,
					Automator_Status::IN_PROGRESS,
				),
				true
			) ) {

				$recipe_run_number = 'anonymous' === (string) Automator()->utilities->get_recipe_type( absint( $trigger->automator_recipe_id ) ) ? '&mdash;' : absint( $trigger->recipe_run_number );

				// Run # when it is a logged-in recipe
				if ( is_numeric( $recipe_run_number ) ) {
					$recipe_run_number = '
						<div class="uap-logs-run-number">
							<div class="uap-logs-run-number--user">' .
								esc_html( $recipe_run_number ) . '
							</div>
						</div>';
				} else {
					// Anon.
					$recipe_run_number = '
						<div class="uap-logs-run-number">
							<div class="uap-logs-run-number--user">
								<uas-icon code="user-slash" icon-style="solid"></uas-icon>
							</div>
						</div>';
				}
			}

			$data[] = array(
				'trigger_id'             => $trigger->ID,
				'trigger_title'          => $trigger_name,
				'trigger_date'           => $trigger_date_completed,
				'recipe_title'           => $recipe_name,
				'recipe_completed'       => $recipe_status,
				'recipe_class_name'      => $this->get_status_class_name( $trigger->recipe_completed ),
				'recipe_class_name_type' => 'trigger',
				'recipe_date_time'       => $recipe_date_completed,
				'recipe_run_number'      => $recipe_run_number,
				/* translators: 1. Trigger run number 2. Trigger total times */
				'trigger_run_number'     => sprintf( esc_attr__( '%1$d of %2$d', 'uncanny-automator' ), $trigger_run_number, $trigger_total_times ),
				'display_name'           => $user_name,
				'actions'                => $actions,
			);
		}

		return $data;
	}

	/**
	 * The item's code, or '' when the item no longer exists.
	 *
	 * Read off the item's own post, so an action nested in a filter, delay or
	 * loop block is found like one at the recipe root.
	 *
	 * @param int $item_id Trigger or action post id.
	 *
	 * @return string
	 */
	private function item_code( $item_id ) {
		return (string) get_post_meta( absint( $item_id ), 'code', true );
	}

	/**
	 * Method format_human_readable_sentence.
	 *
	 * Wrap the sentence tokens with <span>s
	 *
	 * This will convert convert:
	 *
	 * > input: "User views {{Homepage}}"
	 * > output: "User views <span>Homepage</span>"
	 *
	 * Note: Consider that if a sentence token has an item token
	 * like {{user_email}}, then the sentence would be:
	 *
	 * > input: "Send an email to {{{{user_email}}}}"
	 *
	 * (In that case, we want to keep the curly brackets from the item token,
	 * and replace the curly brackets of the sentence token with the <span>)
	 *
	 * > output: "Send an email to <span>{{user_email}}</span>"
	 *
	 * @param string $sentence
	 *
	 * @return string
	 */
	private function format_human_readable_sentence( $sentence = '' ) {

		if ( ! empty( $sentence ) ) {
			$sentence = preg_replace(
				'({{(.*?)}}(?=\s|$))',
				'<span class="uap-logs-table-item-name__token">$1</span>',
				$sentence
			);
		}

		return $sentence;
	}

	/**
	 * Format action log data
	 *
	 * @param array $actions list of objects
	 *
	 * @return array
	 */
	private function format_action_data( $actions ) {

		$data  = array();
		$notes = Database::get_action_error_store()->get_messages_by_action_logs( array_column( $actions, 'action_log_id' ) );

		foreach ( $actions as $action ) {

			$current_type = Automator()->utilities->get_recipe_type( $action->automator_recipe_id );

			$run_number_log = 'anonymous' === $current_type ? 0 === absint( $action->recipe_run_number ) ? 1 : $action->recipe_run_number : $action->recipe_run_number;

			$action_column = sprintf(
				'<uas-button color="secondary" size="xs" href="%1$s">%2$s</uas-button>',
				esc_attr( '#/recipe-log/recipe/' . absint( $action->automator_recipe_id ) . '/run/' . absint( $run_number_log ) . '/log/' . absint( $action->recipe_log_id ) ),
				esc_html__( 'Details', 'uncanny-automator' )
			);

			// Action status
			$action_status_html = '
				<div class="uap-logs-action-status uap-logs-action-status--' . sanitize_html_class( $this->get_status_class_name( $action->action_completed ) ) . '">' .
					esc_html( $this->get_status_name( $action->action_completed ) ) . '
				</div>';

			$action_code = $this->item_code( absint( $action->automator_action_id ) );
			/* translators: 1. Action ID */
			$action_name = sprintf( esc_attr__( 'Action deleted: %1$s', 'uncanny-automator' ), $action->automator_action_id );

			if ( $action_code ) {
				// get the action title
				$action_title = $action->action_title;
				// get the action completed sentence
				$action_sentence = $action->action_sentence;

				if ( empty( $action_title ) && ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
					/* translators: 1. Trademarked term */
					$action_name = sprintf( esc_attr__( '(Reactivate %1$s to view)', 'uncanny-automator' ), 'Uncanny Automator Pro' );
				} else {
					if ( empty( $action_sentence ) ) {
						$action_name = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $action_title ) . '</div>';
					} else {
						$action_name  = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $action_sentence ) . '</div>';
						$action_name .= '<div class="uap-logs-table__item-secondary-sentence">' . esc_html( $action_title ) . '</div>';
					}
				}

				$action_name = '
					<div class="uap-logs-table-action">
						<div class="uap-logs-table__item-main-sentence uap-logs-table__action-name">' .
								$this->format_human_readable_sentence( $action_sentence ) . '
						</div>
					</div>';

			}

			// Only show a completion date once the action has actually completed.
			// A Scheduled / In-progress action (e.g. a delayed action) has not run
			// yet, so showing its log time under "Completion date" is misleading.
			// Mirrors the recipe-row gate above.
			$action_date_completed = in_array(
				absint( $action->action_completed ),
				array(
					Automator_Status::COMPLETED,
					Automator_Status::COMPLETED_WITH_ERRORS,
					Automator_Status::DID_NOTHING,
					Automator_Status::COMPLETED_WITH_NOTICE,
				),
				true
			) ?
				$action->action_date :
				'';

			$action_date_html = $this->get_completion_date_html( $action_date_completed, 'action' );

			if ( false !== $action_date_html ) {
				$action_date_completed = $action_date_html;
			}

			$action_status = apply_filters( 'automator_action_log_status', $action_status_html, $action );
			$error_message = apply_filters( 'automator_action_log_error', $this->action_notes( $notes[ absint( $action->action_log_id ) ] ?? array(), (string) $action->error_message ), $action );

			/**
			 * Replaced get_edit_post_link() to get_edit_link
			 *
			 * <https://developer.wordpress.org/reference/functions/get_edit_post_link/>
			 *
			 * The function get_edit_post_link is making a call to get_post() which is uncached.
			 * Doing this will make Automator query every post just to construct an edit link.
			 *
			 * @since 4.5
			 */
			$recipe_link = $this->get_edit_link( absint( $action->automator_recipe_id ) );

			//$recipe_name = '<a href="' . esc_url( $recipe_link ) . '" class="uap-log-table__recipe-name">' . esc_html( $action->recipe_title ) . '</a>';
			$recipe_name = $this->build_recipe_title_with_icons( $action->automator_recipe_id, $recipe_link, esc_html( $action->recipe_title ), 'uap-log-table__recipe-name', $action );

			$recipe_status = '
				<div class="uap-logs-action-status uap-logs-action-status--' . sanitize_html_class( $this->get_status_class_name( $action->recipe_completed ) ) . '">' .
					esc_html( $this->get_status_name( $action->recipe_completed ) ) . '
				</div>';

			$recipe_date_completed = ( 1 === absint( $action->recipe_completed ) || 2 === absint( $action->recipe_completed ) || 9 === absint( $action->recipe_completed ) ) ? $action->recipe_date_time : '';

			$recipe_run_number = '&mdash;';

			if ( ! in_array(
				absint( $action->recipe_completed ),
				array(
					0,
					Automator_Status::COMPLETED_WITH_ERRORS,
					Automator_Status::IN_PROGRESS,
				),
				true
			) ) {

				$recipe_run_number = 'anonymous' === (string) Automator()->utilities->get_recipe_type( absint( $action->automator_recipe_id ) ) ? '&mdash;' : $action->recipe_run_number;

				if ( is_numeric( $recipe_run_number ) ) {

					$recipe_run_number = '
						<div class="uap-logs-run-number">
							<div class="uap-logs-run-number--user">' .
								esc_html( $recipe_run_number ) . '
							</div>
						</div>';

				} else {

					$recipe_run_number = '
						<div class="uap-logs-run-number">
							<div class="uap-logs-run-number--user">
								<uas-icon code="user-slash" icon-style="solid"></uas-icon>
							</div>
						</div>';

				}
			}

			// Avatar.
			$user_name = $this->get_user_html( $action );

			$buttons = array();

			// Only load this button when modal is opened.
			// @ticket https://app.clickup.com/t/3f8rhte
			if ( 'uncanny-automator-recipe-activity-details' === automator_filter_input( 'page' ) ) {

				$api_request = Automator()->db->api->get_by_log_id( 'action', $action->action_log_id );

				if ( ! empty( $api_request->params ) ) {
					$buttons['resend'] = Api_Log::resend_button_html( $action->action_log_id );
				}
			}

			$data[] = array(
				'action_title'           => $action_name,
				'action_date'            => $action_date_completed,
				'action_completed'       => $action_status,
				'error_message'          => $error_message,
				'recipe_title'           => $recipe_name,
				'recipe_class_name'      => $this->get_status_class_name( $action->action_completed ),
				'recipe_class_name_type' => 'action',
				'recipe_completed'       => $recipe_status,
				'recipe_date_time'       => $recipe_date_completed,
				'recipe_run_number'      => $recipe_run_number,
				'display_name'           => $user_name,
				'buttons'                => join( ' ', $buttons ),
				'actions'                => $action_column,
			);
		}

		return $data;
	}

	/**
	 * The Notes text of an action log: the errors the run recorded for it, or, for an action logged before 8.0 with
	 * none recorded, its legacy message. The recorded errors keep the markup a legacy message could hold.
	 *
	 * @since 8.0.1
	 *
	 * @param string[] $messages       The errors recorded for the action log, oldest first.
	 * @param string   $legacy_message The action log's legacy message.
	 *
	 * @return string
	 */
	private function action_notes( array $messages, string $legacy_message ) {

		if ( empty( $messages ) ) {
			return $legacy_message;
		}

		return wp_kses(
			implode( ' ', $messages ),
			array(
				'a'  => array(
					'href'   => array(),
					'title'  => array(),
					'target' => array(),
				),
				'br' => array(),
			)
		);
	}

	/**
	 * Replaces {{automator_upgrade_link}} with actual upgrade link.
	 *
	 * @param string $message The message.
	 *
	 * @return string The message with upgrade link.
	 */
	public static function format_all_upgrade_links( $message, $action ) {

		$link = 'https://automatorplugin.com/pricing/?utm_source=uncanny-automator&utm_medium=in-plugin&utm_content=recipe-log&utm_campaign=upgrade-to-pro';

		$upgrade_link = sprintf( '<a target="_blank" href="%1$s" title="%2$s">%2$s</a>', $link, esc_html__( 'Please upgrade for unlimited app credits', 'uncanny-automator' ) );

		return str_replace( '{{automator_upgrade_link}}', $upgrade_link, $message );
	}

	/**
	 * Override function for table navigation.
	 *
	 * @param string $which "top" or "bottom"
	 */
	protected function extra_tablenav( $which ) {

		if ( 'top' === $which ) {

			do_action( 'automator_admin_logs_list_table_extra_nav_before' );

			// Use PRO filters if available.
			if ( class_exists( '\uncanny_automator_pro\Pro_Filters' ) ) {
				echo Pro_Filters::activities_filters_html( $this->tab ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				do_action( 'automator_admin_logs_list_table_extra_nav_after' );

				return;
			}

			// Otherwise, use placeholder.
			include Utilities::automator_get_view( 'admin-logs/filters.php' );
			do_action( 'automator_admin_logs_list_table_extra_nav_after' );

			return;

		}
	}

	/**
	 * Override method
	 *
	 * @param object $item
	 * @param string $column_name
	 *
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {

		return $item[ $column_name ];
	}

	/**
	 * @return mixed|string[]|null
	 */
	protected function get_table_classes() {

		$mode = get_user_setting( 'posts_list_mode', 'list' );

		$mode_class = esc_attr( 'table-view-' . $mode );

		return apply_filters(
			'automator_core_class_logs_list_table',
			array( 'uncanny-automator', 'widefat', 'striped', $mode_class, $this->_args['plural'] ),
			$this
		);
	}

	/**
	 * Generates content for a single row of the table.
	 *
	 * @param object|array $item The current item
	 *
	 * @since 3.1.0
	 */
	public function single_row( $item ) {

		$classes = array_unique(
			array(
				'uap-logs-row-recipe-status',
				'uap-logs-row-recipe-status--' . sanitize_html_class( $item['recipe_class_name'] ),
				'uap-logs-row-' . sanitize_html_class( $item['recipe_class_name_type'] ) . '-status',
				'uap-logs-row-' . sanitize_html_class( $item['recipe_class_name_type'] ) . '-status--' . sanitize_html_class( $item['recipe_class_name'] ),
			)
		);

		echo '<tr class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Get date and time formatted.
	 *
	 * @param string $time_string The formatted time in Y-m-d H:i:s format.
	 *
	 * @return array|boolean Returns ['time','date'], or false if $time_string is empty.
	 */
	private function get_datetime_formatted( $time_string = '' ) {

		if ( empty( $time_string ) ) {
			return false;
		}

		$datetime = \DateTime::createFromFormat( 'Y-m-d H:i:s', $time_string );

		if ( false === $datetime ) {
			return false;
		}

		return array(
			'date' => $datetime->format( get_option( 'date_format' ) ),
			'time' => $datetime->format( get_option( 'time_format' ) ),
		);
	}

	/**
	 * Get completion date HTML with relative time appended for same-day dates.
	 *
	 * Same-day: "March 26, 2026 11:45 AM (17 minutes ago)"
	 * Older: "March 25, 2026 2:30 PM"
	 *
	 * @param string $time_string The datetime in Y-m-d H:i:s format.
	 * @param string $css_modifier CSS modifier class (e.g. 'recipe', 'trigger').
	 *
	 * @return string|false The formatted HTML string, or false if $time_string is empty.
	 */
	private function get_completion_date_html( $time_string = '', $css_modifier = 'recipe' ) {

		$datetime_parts = $this->get_datetime_formatted( $time_string );

		if ( false === $datetime_parts ) {
			return false;
		}

		$full_date = $datetime_parts['date'] . ' ' . $datetime_parts['time'];
		$wp_tz     = new \DateTimeZone( Automator()->get_timezone_string() );

		try {
			$now_dt       = new \DateTime( 'now', $wp_tz );
			$timestamp_dt = new \DateTime( $time_string, $wp_tz );
		} catch ( \Exception $e ) {
			$now_dt       = null;
			$timestamp_dt = null;
		}

		// Same calendar day and in the past — append relative time.
		if ( null !== $now_dt && null !== $timestamp_dt && $now_dt->format( 'Y-m-d' ) === $timestamp_dt->format( 'Y-m-d' ) ) {

			$diff_seconds = $now_dt->getTimestamp() - $timestamp_dt->getTimestamp();

			if ( $diff_seconds >= 0 && $diff_seconds < DAY_IN_SECONDS ) {
				/* translators: %s is a human-readable time difference, e.g. "17 minutes" */
				$relative = sprintf( esc_html_x( '%s ago', 'Recipe log relative time', 'uncanny-automator' ), human_time_diff( $timestamp_dt->getTimestamp(), $now_dt->getTimestamp() ) );

				return sprintf(
					'<div class="uap-logs-complation-date uap-logs-%s-complation-date">%s<div class="uap-logs-complation-date__time">(%s)</div></div>',
					sanitize_html_class( $css_modifier ),
					esc_html( $full_date ),
					esc_html( $relative )
				);
			}
		}

		// Fallback: standard date + time format.
		return sprintf(
			'<div class="uap-logs-complation-date uap-logs-%s-complation-date">%s</div>',
			sanitize_html_class( $css_modifier ),
			esc_html( $full_date )
		);
	}

	/**
	 * Constructs the user HTML of any log
	 *
	 * @param mixed $item_object - The item object.
	 *
	 * @return string The HTML of the user log.
	 */
	private function get_user_html( $item_object ) {

		// Safely convert all user id to integer.
		$user_id = absint( $item_object->user_id );

		// Handle 'everyone' recipe log.
		if ( empty( $user_id ) ) {
			return '&mdash;';
		}

		$deleted = empty( $item_object->display_name ) && empty( $item_object->user_email ) && 0 !== absint( $user_id );

		// Handle logs where user is deleted.
		if ( $deleted ) {
			return '
            <div class="uap-logs-user">
			    <div class="uap-logs-user__avatar-container">
				    <img class="uap-logs-user__avatar" src="' . esc_url( Utilities::automator_get_asset( 'build/img/gravatar-user-deleted.svg' ) ) . '">
				</div>
			<div class="uap-logs-user__info">
			    <div class="uap-logs-user__display-name--deleted-user">' . esc_html__( 'Deleted user', 'uncanny-automator' ) . '</div>'
					/* translators: Recipe log user ID column row value. */
					. '<div class="uap-logs-user__id">' . sprintf( esc_html__( 'User ID #%1$s', 'uncanny-automator' ), $user_id ) . '</div>
			</div>';
		}

		// Otherwise, proceed with the normal flow.

		$user_link = add_query_arg( 'user_id', $user_id, self_admin_url( 'user-edit.php' ) ); // Directly constructed the link to avoid extra query.

		return '
        <div class="uap-logs-user">
			<div class="uap-logs-user__avatar-container">'
				. get_avatar( $user_id, 80, '', '', array( 'class' => 'uap-logs-user__avatar' ) ) . '
			</div>
		    <div class="uap-logs-user__info">
			    <a href="' . esc_url( $user_link ) . '" class="uap-logs-user__display-name">' . esc_html( $item_object->display_name ) . '</a>
		    <div class="uap-logs-user__email">' . esc_html( $item_object->user_email ) . '</div>
		</div>';
	}

	/**
	 * @param $status
	 *
	 * @return string
	 */
	private function get_status_name( $status ) {

		if ( 0 === absint( $status ) ) {
			$status = Automator_Status::IN_PROGRESS;
		}

		return Automator_Status::name( $status );
	}

	/**
	 * @param $status
	 *
	 * @return mixed
	 */
	private function get_status_class_name( $status ) {

		if ( 0 === absint( $status ) ) {
			$status = Automator_Status::IN_PROGRESS;
		}

		return Automator_Status::get_class_name( $status );
	}

	/**
	 * @param $recipe_id
	 * @param $run_number_log
	 * @param $recipe_log_id
	 *
	 * @return string
	 */
	private function get_details_url( $recipe_id = 0, $run_number_log = 0, $recipe_log_id = 0 ) {

		return sprintf(
			'%s?page=%s&recipe_id=%d&run_number=%d&recipe_log_id=%d&automator_minimal=1',
			admin_url( 'options.php' ),
			'uncanny-automator-recipe-activity-details',
			absint( $recipe_id ),
			absint( $run_number_log ),
			absint( $recipe_log_id )
		);
	}

	/**
	 * Sanitize given $order input.
	 *
	 * @param string $order The order input.
	 *
	 * @return string The clean order value.
	 */
	private function sanitize_order( $order ) {

		$order = strtoupper( $order );

		$allowed_order = array( 'ASC', 'DESC' );

		if ( ! in_array( $order, $allowed_order, true ) ) {
			return 'DESC';
		}

		return filter_var( $order, FILTER_UNSAFE_RAW );
	}

	/**
	 * Restricts order by input to only provided sortable fields and sanitizes the input before its returned.
	 *
	 * @param string $order_by The input for order by.
	 * @param array $sortable_fields The input for fields used in order by to be allowed.
	 *
	 * @return string The clean order by input.
	 */
	private function sanitize_order_by( $order_by = '', $sortable_fields = array() ) {

		$order_by = strtolower( $order_by );

		if ( ! in_array( $order_by, $sortable_fields, true ) ) {
			return 'recipe_date_time';
		}

		return filter_var( $order_by, FILTER_UNSAFE_RAW );
	}


	/**
	 * @param $post_id
	 *
	 * @return string
	 */
	private function get_edit_link( $post_id = 0 ) {

		return add_query_arg(
			array(
				'action' => 'edit',
				'post'   => absint( $post_id ),
			),
			admin_url( 'post.php' )
		);
	}

	/**
	 * @param $recipe_id
	 * @param $recipe_link
	 * @param $recipe_name
	 * @param $recipe
	 *
	 * @return string
	 */
	private function build_recipe_title_with_icons( $recipe_id, $recipe_link, $recipe_name, $css_class, $recipe ) {

		$recipe_id = (int) $recipe_id;

		if ( ! isset( $this->recipe_integration_codes[ $recipe_id ] ) ) {
			$this->recipe_integration_codes[ $recipe_id ] = automator_get_recipe_integration_codes( $recipe_id );
		}

		$icons = '';

		foreach ( $this->recipe_integration_codes[ $recipe_id ] as $unique_integration ) {
			$icons .= '<uap-d-integrations-icon code="' . esc_attr( $unique_integration ) . '" size="xs" hide-missing show-tooltip></uap-d-integrations-icon>';
		}

		$recipe_name = sprintf(
			'<div class="uap-log-table-recipe-container">
				<a href="%s" class="%s">%s</a>
				<div class="uap-log-table__recipe-integrations">
					<div class="uap-log-table-recipe-integration-triggers">
						%s
					</div>
				</div>
			</div>',
			esc_url( $recipe_link ),
			$css_class,
			esc_html( $recipe_name ),
			$icons
		);

		return $recipe_name;
	}
}
