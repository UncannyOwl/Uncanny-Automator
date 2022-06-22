<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Pro_Filters;
use WP_List_Table;

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . '/wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Logs_List_Table
 *
 * @package Uncanny_Automator
 */
class Logs_List_Table extends WP_List_Table {

	/**
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

		add_filter( 'automator_action_log_error', array( $this, 'format_all_upgrade_links' ), 10, 2 );
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
	 * Prepare items/data
	 */
	public function prepare_items() {
		global $wpdb, $_wp_column_headers;
		$screen = get_current_screen();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		if ( class_exists( '\Uncanny_Automator_Pro\Pro_Filters' ) ) {
			if ( 'recipe-log' === $this->tab ) {
				$query = Pro_Filters::get_recipe_query();
			} elseif ( 'recipe-activity' === $this->tab ) {
				$query = Pro_Filters::get_recipe_query();
			} elseif ( 'trigger-log' === $this->tab ) {
				$query = Pro_Filters::get_trigger_query();
			} elseif ( 'action-log' === $this->tab ) {
				$query = Pro_Filters::get_action_query();
			}
		} else {
			if ( 'recipe-log' === $this->tab ) {
				$query = $this->get_recipe_query();
			} elseif ( 'recipe-activity' === $this->tab ) {
				$query = $this->get_recipe_query();
			} elseif ( 'trigger-log' === $this->tab ) {
				$query = $this->get_trigger_query();
			} elseif ( 'action-log' === $this->tab ) {
				$query = $this->get_action_query();
			}
		}

		/* -- Ordering parameters -- */
		$order = ! empty( automator_filter_input( 'order' ) ) ? $wpdb->_real_escape( automator_filter_input( 'order' ) ) : 'DESC';

		if ( 'recipe-log' === $this->tab ) {
			$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? $wpdb->_real_escape( automator_filter_input( 'orderby' ) ) : 'recipe_date_time';
		} elseif ( 'recipe-activity' === $this->tab ) {
			$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? $wpdb->_real_escape( automator_filter_input( 'orderby' ) ) : 'recipe_date_time';
		} elseif ( 'trigger-log' === $this->tab ) {
			$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? $wpdb->_real_escape( automator_filter_input( 'orderby' ) ) : 'trigger_date';
		} elseif ( 'action-log' === $this->tab ) {
			$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? $wpdb->_real_escape( automator_filter_input( 'orderby' ) ) : 'action_date';
		}

		if ( ! empty( $query ) && ! empty( $orderby ) && ! empty( $order ) ) {
			$query .= ' ORDER BY ' . $orderby . ' ' . $order;
		}

		/* -- Pagination parameters -- */
		$total_items = $wpdb->query( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$perpage = 100;

		$paged = $this->get_pagenum();

		if ( empty( $paged ) || ! is_numeric( $paged ) || $paged <= 0 ) {
			$paged = 1;
		}

		$totalpages = ceil( $total_items / $perpage ); //adjust the query to take pagination into account
		if ( ! empty( $paged ) && ! empty( $perpage ) ) {
			$offset = ( $paged - 1 ) * $perpage;
			$query  .= ' LIMIT ' . (int) $offset . ',' . $perpage;
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
		$columns                           = $this->get_columns();
		$_wp_column_headers[ $screen->id ] = $columns;

		/* -- Fetch the items -- */
		if ( 'recipe-log' === $this->tab ) {
			$this->items = $this->format_recipe_data( $wpdb->get_results( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} elseif ( 'recipe-activity' === $this->tab ) {
			$this->items = $this->format_recipe_activity_data( $wpdb->get_results( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} elseif ( 'trigger-log' === $this->tab ) {
			$this->items = $this->format_trigger_data( $wpdb->get_results( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} elseif ( 'action-log' === $this->tab ) {
			$this->items = $this->format_action_data( $wpdb->get_results( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
			$search_key = sanitize_text_field( automator_filter_input( 'search_key' ) );
			if ( ! $view_exists ) {
				$search_conditions .= " AND ( (p.recipe_title LIKE '%$search_key%') OR (u.display_name  LIKE '%$search_key%' ) OR (u.user_email  LIKE '%$search_key%' ) ) ";
			} else {
				$search_conditions .= " AND ( (recipe_title LIKE '%$search_key%') OR (display_name  LIKE '%$search_key%' ) OR (user_email  LIKE '%$search_key%' ) ) ";
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
			$search_key = sanitize_text_field( automator_filter_input( 'search_key' ) );
			if ( $view_exists ) {
				$search_conditions .= " AND ( (recipe_title LIKE '%$search_key%') OR (trigger_title LIKE '%$search_key%') OR (display_name  LIKE '%$search_key%' ) OR (user_email  LIKE '%$search_key%' ) ) ";
			} else {
				$search_conditions .= " AND ( (p.post_title LIKE '%$search_key%') OR (pt.post_title LIKE '%$search_key%') OR (u.display_name  LIKE '%$search_key%' ) OR (u.user_email  LIKE '%$search_key%' ) ) ";

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
			$search_key = sanitize_text_field( automator_filter_input( 'search_key' ) );
			if ( $view_exists ) {
				$search_conditions .= " AND ( (recipe_title LIKE '%$search_key%') OR (action_title LIKE '%$search_key%') OR (display_name LIKE '%$search_key%' ) OR (user_email LIKE '%$search_key%' ) OR (error_message LIKE '%$search_key%' ) ) ";
			} else {
				$search_conditions .= " AND ( (p.post_title LIKE '%$search_key%') OR (pa.post_title LIKE '%$search_key%') OR (u.display_name LIKE '%$search_key%' ) OR (u.user_email LIKE '%$search_key%' ) OR (a.error_message LIKE '%$search_key%' ) ) ";
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

			if ( isset( $recipe->automator_recipe_id ) ) {
				$recipe_link = get_edit_post_link( absint( $recipe->automator_recipe_id ) );
				$recipe_id   = $recipe->automator_recipe_id;
			} else {
				$recipe_link = '#';
				$recipe_id   = 0;
			}

			$recipe_name = ! empty( $recipe->recipe_title ) ? $recipe->recipe_title : sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $recipe_id );

			if ( '#' !== $recipe_link ) {
				$recipe_name = '<a href="' . $recipe_link . '" class="uap-log-table__recipe-name">' . $recipe_name . '</a>';
			}
			if ( empty( $recipe->display_name ) ) {
				/* translators: User type */
				$user_name = esc_attr_x( 'N/A', 'User', 'uncanny-automator' );
			} else {
				$user_link = get_edit_user_link( absint( $recipe->user_id ) );
				$user_name = '<a href="' . $user_link . '">' . $recipe->display_name . '</a> <br>' . $recipe->user_email;
			}

			if ( 1 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = esc_attr_x( 'Completed', 'Recipe', 'uncanny-automator' );
			} elseif ( 2 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = esc_attr_x( 'Completed with errors', 'Recipe', 'uncanny-automator' );
			} elseif ( 9 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = esc_attr_x( 'Completed - do nothing', 'Recipe', 'uncanny-automator' );
			} else {
				/* translators: Recipe status */
				$recipe_status = esc_attr_x( 'In progress', 'Recipe', 'uncanny-automator' );
			}

			$recipe_date_completed = ( 1 === absint( $recipe->recipe_completed ) || 2 === absint( $recipe->recipe_completed ) || 9 === absint( $recipe->recipe_completed ) ) ? $recipe->recipe_date_time : '';
			$current_type          = Automator()->utilities->get_recipe_type( $recipe_id );
			$run_number            = 'anonymous' === $current_type ? 'N/A' : $recipe->run_number;

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
			$url            = sprintf(
				'%s?post_type=%s&page=%s&recipe_id=%d&run_number=%d&recipe_log_id=%d&automator_minimal=1',
				admin_url( 'edit.php' ),
				'uo-recipe',
				'uncanny-automator-recipe-activity-details',
				$recipe_id,
				$run_number_log,
				absint( $recipe_log_id )
			);
			$delete_url     = sprintf(
				'%s?post_type=%s&page=%s&recipe_id=%d&run_number=%d&recipe_log_id=%d&delete_specific_activity=1&wpnonce=' . wp_create_nonce( AUTOMATOR_FREE_ITEM_NAME ),
				admin_url( 'edit.php' ),
				'uo-recipe',
				'uncanny-automator-recipe-log',
				$recipe_id,
				$run_number_log,
				absint( $recipe_log_id )
			);

			$actions = array(
				'view' => sprintf( '<a href="%s" data-lity class="button button-primary">%s</a>', $url, esc_attr__( 'Details', 'uncanny-automator' ) ),
				//Removed: 'rerun' => sprintf( '<a href="%s" onclick="javascript: return confirm(\"%s\")">%s</a>', '#', esc_attr__( 'Are you sure you want to re-run this recipe?', 'uncanny-automator' ), esc_attr__( 'Re-run', 'uncanny-automator' ) ),
			);
			if ( true === apply_filters( 'automator_allow_users_to_delete_in_progress_recipe_runs', true, $recipe_id ) ) {
				$actions['delete'] = sprintf( '<a href="%s" class="button button-secondary" onclick="javascript: return confirm(\'%s\')">%s</a>', $delete_url, esc_attr__( 'Are you sure you want to delete this run? This action is irreversible.', 'uncanny-automator' ), esc_attr__( 'Delete', 'uncanny-automator' ) );
			}

			$data[] = array(
				'recipe_type'      => $recipe_type_name,
				'recipe_title'     => $recipe_name,
				'recipe_date_time' => $recipe_date_completed,
				'display_name'     => $user_name,
				'recipe_completed' => $recipe_status,
				'run_number'       => $run_number,
				'actions'          => join( ' ', $actions ), // Added.
			);
		}

		return $data;
	}

	/**
	 * Format recipes log data
	 *
	 * @param array $recipes list of objects
	 *
	 * @return array
	 */
	private function format_recipe_activity_data( $recipes ) {

		$data = array();
		foreach ( $recipes as $recipe ) {
			$recipe_log_id = $recipe->recipe_log_id;

			if ( isset( $recipe->automator_recipe_id ) ) {
				$recipe_link = get_edit_post_link( absint( $recipe->automator_recipe_id ) );
				$recipe_id   = $recipe->automator_recipe_id;
			} else {
				$recipe_link = '#';
				$recipe_id   = 0;
			}

			$recipe_name = ! empty( $recipe->recipe_title ) ? $recipe->recipe_title : sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $recipe_id );

			if ( '#' !== $recipe_link ) {
				$recipe_name = '<a href="' . $recipe_link . '" class="uap-log-table__recipe-name">' . $recipe_name . '</a>';
			}
			if ( empty( $recipe->display_name ) ) {
				/* translators: User type */
				$user_name = esc_attr_x( 'N/A', 'User', 'uncanny-automator' );
			} else {
				$user_link = get_edit_user_link( absint( $recipe->user_id ) );
				$user_name = '<a href="' . $user_link . '">' . $recipe->display_name . '</a> <br>' . $recipe->user_email;
			}

			if ( 1 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = esc_attr_x( 'Completed', 'Recipe', 'uncanny-automator' );
			} elseif ( 2 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = esc_attr_x( 'Completed with errors', 'Recipe', 'uncanny-automator' );
			} elseif ( 9 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = esc_attr_x( 'Completed - do nothing', 'Recipe', 'uncanny-automator' );
			} else {
				/* translators: Recipe status */
				$recipe_status = esc_attr_x( 'In progress', 'Recipe', 'uncanny-automator' );
			}

			$recipe_date_completed = ( 1 === absint( $recipe->recipe_completed ) || 2 === absint( $recipe->recipe_completed ) || 9 === absint( $recipe->recipe_completed ) ) ? $recipe->recipe_date_time : '';
			$current_type          = Automator()->utilities->get_recipe_type( $recipe_id );
			$run_number            = 'anonymous' === $current_type ? 'N/A' : $recipe->run_number;

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

			$url     = sprintf( '%s?post_type=%s&page=%s&recipe_id=%d&run_number=%d&recipe_log_id=%d&automator_minimal=1', admin_url( 'edit.php' ), 'uo-recipe', 'uncanny-automator-recipe-activity-details', $recipe_id, $run_number_log, absint( $recipe_log_id ) );
			$actions = array(
				'view'  => sprintf( '<a href="%s" data-lity>%s</a>', $url, esc_attr__( 'Details', 'uncanny-automator' ) ),
				'rerun' => sprintf( '<a href="%s" onclick="return confirm(\"%s\")">%s</a>', '#', esc_attr__( 'Are you sure you want to re-run this recipe?', 'uncanny-automator' ), esc_attr__( 'Re-run', 'uncanny-automator' ) ),
			);

			$data[] = array(
				'recipe_type'      => $recipe_type_name,
				'recipe_title'     => $recipe_name,
				'recipe_date_time' => $recipe_date_completed,
				'display_name'     => $user_name,
				'recipe_completed' => $recipe_status,
				'run_number'       => $run_number,
				'actions'          => join( ' | ', $actions ),
			);
		}

		return $data;
	}

	/**
	 * Format trigger log data
	 *
	 * @param array $triggers list of objects
	 *
	 * @return array
	 */
	private function format_trigger_data( $triggers ) {

		$data         = array();
		$recipes_data = Automator()->get_recipes_data( false );

		foreach ( $triggers as $trigger ) {

			$trigger_code = $this->item_code( $recipes_data, absint( $trigger->automator_trigger_id ) );

			$trigger_date_completed = $trigger->trigger_date;
			if ( ! empty( $trigger->trigger_run_time ) && '0000-00-00 00:00:00' !== (string) $trigger->trigger_run_time ) {
				$trigger_date_completed = $trigger->trigger_run_time;
			}

			$recipe_link = get_edit_post_link( absint( $trigger->automator_recipe_id ) );
			/* translators: 1: Post ID */
			$recipe_name = ! empty( $trigger->recipe_title ) ? $trigger->recipe_title : sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $trigger->automator_recipe_id );

			if ( ! empty( $recipe_link ) ) {
				$recipe_name = '<a href="' . $recipe_link . '" class="uap-log-table__recipe-name">' . $recipe_name . '</a>';
			}

			if ( 1 === absint( $trigger->recipe_completed ) ) {
				/* translators: Trigger status */
				$recipe_status = esc_attr_x( 'Completed', 'Trigger', 'uncanny-automator' );
			} elseif ( 2 === absint( $trigger->recipe_completed ) ) {
				/* translators: Trigger status */
				$recipe_status = esc_attr_x( 'Completed with errors', 'Trigger', 'uncanny-automator' );
			} elseif ( 9 === absint( $trigger->recipe_completed ) ) {
				/* translators: Trigger status */
				$recipe_status = esc_attr_x( 'Completed, do nothing', 'Trigger', 'uncanny-automator' );
			} else {
				/* translators: Trigger status */
				$recipe_status = esc_attr_x( 'In progress', 'Trigger', 'uncanny-automator' );
			}

			$recipe_date_completed = ( 1 === absint( $trigger->recipe_completed ) || 2 === absint( $trigger->recipe_completed ) || 9 === absint( $trigger->recipe_completed ) ) ? $trigger->recipe_date_time : '';
			if ( is_null( $trigger->user_id ) ) {
				/* translators: User type */
				$user_name = esc_attr_x( 'N/A', 'User', 'uncanny-automator' );
			} else {
				$user_link       = get_edit_user_link( absint( $trigger->user_id ) );
				$user_email_link = sprintf(
					'<a href="mailto:%1$s" title="%2$s">(%1$s)</a>',
					sanitize_email( $trigger->user_email ),
					esc_attr__( 'Send Email', 'uncanny-automator' )
				);
				$user_name       = '<a href="' . $user_link . '">' . $trigger->display_name . '</a> <br>' . $user_email_link;
			}

			/* translators: 1. Trigger ID */
			$trigger_name = sprintf( esc_attr__( 'Trigger deleted: %1$s', 'uncanny-automator' ), $trigger->automator_trigger_id );

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
						$trigger_name = '<div class="uap-logs-table__item-main-sentence">' . $trigger_title . '</div>';
					} else {
						$trigger_name = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $trigger_sentence ) . '</div>';
						$trigger_name .= '<div class="uap-logs-table__item-secondary-sentence">' . $trigger_title . '</div>';
					}
				}
			}

			$recipe_run_number   = absint( $trigger->recipe_run_number );
			$trigger_run_number  = ( 0 === absint( $trigger->trigger_run_number ) || empty( $trigger->trigger_run_number ) ) ? 1 : absint( $trigger->trigger_run_number );
			$trigger_total_times = ( 0 === absint( $trigger->trigger_total_times ) || empty( $trigger->trigger_total_times ) ) ? 1 : $trigger->trigger_total_times;
			$recipe_run_number   = 'anonymous' === (string) Automator()->utilities->get_recipe_type( absint( $trigger->automator_recipe_id ) ) ? 'N/A' : $recipe_run_number;

			$data[] = array(
				'trigger_id'         => $trigger->ID,
				'trigger_title'      => $trigger_name,
				'trigger_date'       => $trigger_date_completed,
				'recipe_title'       => $recipe_name,
				'recipe_completed'   => $recipe_status,
				'recipe_date_time'   => $recipe_date_completed,
				'recipe_run_number'  => $recipe_run_number,
				'trigger_run_number' => sprintf( esc_attr__( '%1$d of %2$d', 'uncanny-automator' ), $trigger_run_number, $trigger_total_times ),
				'display_name'       => $user_name,
			);
		}

		return $data;
	}

	/**
	 * Format single item
	 *
	 * @param array $recipes_data
	 * @param string $item_id
	 *
	 * @return array
	 */
	private function item_code( $recipes_data, $item_id ) {

		$item_code  = null;
		$item_codes = array();

		foreach ( $recipes_data as $recipe_data ) {

			foreach ( $recipe_data['triggers'] as $trigger ) {
				$item_codes[ $trigger['ID'] ] = $trigger['meta']['code'];
			}

			foreach ( $recipe_data['actions'] as $action ) {
				$item_codes[ $action['ID'] ] = $action['meta']['code'];
			}

			foreach ( $recipe_data['closures'] as $closure ) {
				$item_codes[ $closure['ID'] ] = $closure['meta']['code'];
			}
		}

		if ( isset( $item_codes[ $item_id ] ) ) {
			$item_code = $item_codes[ $item_id ];
		}

		return $item_code;
	}

	/**
	 * @param string $sentence
	 *
	 * @return string
	 */
	private function format_human_readable_sentence( $sentence = '' ) {
		// Check if it's not empty
		if ( ! empty( $sentence ) ) {
			// Wrap the sentence tokens with <span>s
			// This will convert convert
			// > input: "User views {{Homepage}}"
			// > output: "User views <span>Homepage</span>"
			//
			// Note: Consider that if a sentence token has an item token
			// like {{user_email}}, then the sentence would be
			// > input: "Send an email to {{{{user_email}}}}"
			// in that case, we want to keep the curly brackets from the item token,
			// and replace the curly brackets of the sentence token with the <span>
			// > output: "Send an email to <span>{{user_email}}</span>"
			$sentence = preg_replace(
				'({{(.*?)}}(?=\s|$))',
				'<span class="uap-logs-table-item-name__token">$1</span>',
				$sentence
			);
		}

		// Return the sentence
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

		$data         = array();
		$recipes_data = Automator()->get_recipes_data( false );

		foreach ( $actions as $action ) {
			$st = esc_attr__( 'Error', 'uncanny-automator' );
			if ( 1 === (int) $action->action_completed ) {
				/* translators: Action status */
				$st = esc_attr_x( 'Completed', 'Action', 'uncanny-automator' );
			} elseif ( 9 === (int) $action->action_completed ) {
				/* translators: Action status */
				$st = esc_attr_x( 'Completed, do nothing', 'Action', 'uncanny-automator' );
			}
			$action_code = $this->item_code( $recipes_data, absint( $action->automator_action_id ) );
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
						$action_name = '<div class="uap-logs-table__item-main-sentence">' . $action_title . '</div>';
					} else {
						$action_name = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $action_sentence ) . '</div>';
						$action_name .= '<div class="uap-logs-table__item-secondary-sentence">' . $action_title . '</div>';
					}
				}
			}

			$action_date_completed = $action->action_date;
			$action_status         = apply_filters( 'automator_action_log_status', $st, $action );
			$error_message         = apply_filters( 'automator_action_log_error', $action->error_message, $action );
			$recipe_link           = get_edit_post_link( absint( $action->automator_recipe_id ) );
			$recipe_name           = '<a href="' . $recipe_link . '" class="uap-log-table__recipe-name">' . $action->recipe_title . '</a>';

			if ( 1 === (int) $action->recipe_completed ) {
				/* translators: Action status */
				$recipe_status = esc_attr_x( 'Completed', 'Action', 'uncanny-automator' );
			} elseif ( 2 === (int) $action->recipe_completed ) {
				/* translators: Action status */
				$recipe_status = esc_attr_x( 'Completed with errors', 'Action', 'uncanny-automator' );
			} elseif ( 9 === (int) $action->recipe_completed ) {
				/* translators: Action status */
				$recipe_status = esc_attr_x( 'Completed, do nothing', 'Action', 'uncanny-automator' );
			} else {
				/* translators: Action status */
				$recipe_status = esc_attr_x( 'In progress', 'Action', 'uncanny-automator' );
			}

			$recipe_date_completed = ( 1 === absint( $action->recipe_completed ) || 2 === absint( $action->recipe_completed ) || 9 === absint( $action->recipe_completed ) ) ? $action->recipe_date_time : '';
			$recipe_run_number     = $action->recipe_run_number;
			$recipe_run_number     = 'anonymous' === (string) Automator()->utilities->get_recipe_type( absint( $action->automator_recipe_id ) ) ? 'N/A' : $recipe_run_number;
			if ( ! is_null( $action->user_id ) ) {
				$user_link       = get_edit_user_link( absint( $action->user_id ) );
				$user_email_link = sprintf(
					'<a href="mailto:%1$s" title="%2$s">(%1$s)</a>',
					sanitize_email( $action->user_email ),
					esc_attr__( 'Send Email', 'uncanny-automator' )
				);
				$user_name       = '<a href="' . $user_link . '">' . $action->display_name . '</a><br>' . $user_email_link;
			} else {
				/* translators: User type */
				$user_name = esc_attr_x( 'N/A', 'User', 'uncanny-automator' );
			}
			$data[] = array(
				'action_title'      => $action_name,
				'action_date'       => $action_date_completed,
				'action_completed'  => $action_status,
				'error_message'     => $error_message,
				'recipe_title'      => $recipe_name,
				'recipe_completed'  => $recipe_status,
				'recipe_date_time'  => $recipe_date_completed,
				'recipe_run_number' => $recipe_run_number,
				'display_name'      => $user_name,
			);
		}

		return $data;
	}

	/**
	 * Replaces {{automator_upgrade_link}} with actual upgrade link.
	 *
	 * @param string $message The message.
	 *
	 * @return string The message with upgrade link.
	 */
	public function format_all_upgrade_links( $message, $action ) {

		$link = 'https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=recipe_log&utm_content=upgrade_to_pro';

		$upgrade_link = sprintf( '<a target="_blank" href="%1$s" title="%2$s">%2$s</a>', $link, esc_html__( 'Please upgrade for unlimited credits', 'uncanny-automator' ) );

		return str_replace( '{{automator_upgrade_link}}', $upgrade_link, $message );

	}

	/**
	 * Override function for table navigation.
	 *
	 * @param string $which "top" or "bottom"
	 */
	protected function extra_tablenav( $which ) {

		if ( 'top' === $which ) {

			if ( class_exists( '\uncanny_automator_pro\Pro_Filters' ) ) {
				$filter_html = Pro_Filters::activities_filters_html( $this->tab );
			} else {
				$GLOBALS['ua_current_tab'] = $this->tab;

				// Start output
				ob_start();

				include Utilities::automator_get_view( 'filters.php' );

				// Get output
				$filter_html = ob_get_clean();
			}

			// There's HTML involved. Ignoring
			echo $filter_html; //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
}
