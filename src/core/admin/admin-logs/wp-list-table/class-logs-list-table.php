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
	 * @var array
	 */
	private $trigger_action_integrations = array();

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

			$query .= ' ORDER BY ' . $orderby . ' ' . $order;

		}

		/* -- Pagination parameters -- */
		$total_items = $wpdb->query( $query ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$perpage = 100;

		$paged = $this->get_pagenum();

		if ( empty( $paged ) || ! is_numeric( $paged ) || $paged <= 0 ) {
			$paged = 1;
		}

		$totalpages = ceil( $total_items / $perpage ); //Adjust the query to take pagination into account.

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
		$columns = $this->get_columns();

		$_wp_column_headers[ $screen->id ] = $columns;

		/* -- Fetch the items -- */
		if ( 'recipe-log' === $this->tab ) {
			$recipes = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->fetch_triggers_actions_integrations( $recipes );
			$this->items = $this->format_recipe_data( $recipes );
		} elseif ( 'recipe-activity' === $this->tab ) {
			$this->items = $this->format_recipe_activity_data( $wpdb->get_results( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} elseif ( 'trigger-log' === $this->tab ) {
			$triggers = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->fetch_triggers_actions_integrations( $triggers );
			$this->items = $this->format_trigger_data( $triggers );
		} elseif ( 'action-log' === $this->tab ) {
			$actions = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$this->fetch_triggers_actions_integrations( $actions );
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
			$search_key = esc_sql( sanitize_text_field( automator_filter_input( 'search_key' ) ) );
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

			$recipe_link = '#';
			$recipe_id   = 0;

			if ( isset( $recipe->automator_recipe_id ) ) {
				$recipe_link = $this->get_edit_link( absint( $recipe->automator_recipe_id ) );
				$recipe_id   = $recipe->automator_recipe_id;
			}

			// Recipe name
			/* translators: Recipe ID */
			$recipe_name = ! empty( $recipe->recipe_title ) ? $recipe->recipe_title : sprintf( esc_attr__( 'ID: %1$s (no title)', 'uncanny-automator' ), $recipe_id );

			if ( '#' !== $recipe_link ) {
				//$recipe_name = '<a href="' . esc_url( $recipe_link ) . '" class="uap-log-table__recipe-name">' . esc_html( $recipe_name ) . '</a>';
				$recipe_name = $this->build_recipe_title_with_icons( $recipe_id, $recipe_link, esc_html( $recipe_name ), 'uap-log-table__recipe-name', $recipe );
			}

			// User
			$user_name = $this->get_user_html( $recipe );

			// Recipe status
			$recipe_status_class = sprintf( 'uap-logs-recipe-status--%s', $this->get_status_class_name( $recipe->recipe_completed ) );

			$recipe_status = '
				<div class="uap-logs-recipe-status ' . sanitize_html_class( $recipe_status_class ) . '">' .
							 esc_html( $this->get_status_name( (int) $recipe->recipe_completed ) ) . '
				</div>';

			// Recipe complation date
			$recipe_date_completed = in_array(
				absint( $recipe->recipe_completed ),
				array(
					Automator_Status::COMPLETED,
					Automator_Status::COMPLETED_WITH_ERRORS,
					Automator_Status::DID_NOTHING,
					Automator_Status::COMPLETED_WITH_NOTICE,
				)
			) ?
				$recipe->recipe_date_time :
				'';

			$recipe_datetime_completed = $this->get_datetime_formatted( $recipe_date_completed );

			if ( false !== $recipe_datetime_completed ) {
				$recipe_date_completed = '
					<div class="uap-logs-complation-date uap-logs-recipe-complation-date">'
										 . esc_html( $recipe_datetime_completed['date'] ) . '
						<span class="uap-logs-complation-date__time">@ '
										 . esc_html( $recipe_datetime_completed['time'] ) . '
						</span>
					</div>';
			}

			$current_type = Automator()->utilities->get_recipe_type( $recipe_id );

			$run_number = '&mdash;';

			// Only show run numbers for non errors non in progress, or status with zero.
			if ( ! in_array(
				absint( $recipe->recipe_completed ),
				array(
					0,
					Automator_Status::COMPLETED_WITH_ERRORS,
					Automator_Status::IN_PROGRESS,
				),
				true
			) ) {

				// Run #
				$run_number = 'anonymous' === $current_type ? '&mdash;' : $recipe->run_number;
				// Run # when it is a anonymous recipe
				if ( 'anonymous' === $current_type ) {
					$run_number = '
						<div class="uap-logs-run-number">
							<div class="uap-logs-run-number--user">
								<uo-icon id="user-slash-solid"></uo-icon>
							</div>
						</div>';
				}

				// Run # when it is a logged-in recipe
				if ( is_numeric( $run_number ) ) {
					$run_number = '
						<div class="uap-logs-run-number">
							<div class="uap-logs-run-number--user">' .
								  esc_html( $run_number ) . '
							</div>
						</div>';
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
				'uo-recipe',
				'uncanny-automator-admin-logs',
				$recipe_id,
				$run_number_log,
				absint( $recipe_log_id )
			);

			$actions = array(
				'view' => '<uap-log-dialog-button log-id="' . $recipe_log_id . '" recipe-id="' . $recipe_id . '" run-number="' . $run_number_log . '"></uap-log-dialog-button>',
			);

			// Delete button
			if ( true === apply_filters( 'automator_allow_users_to_delete_in_progress_recipe_runs', true, $recipe_id ) ) {

				$delete_btn = '<uo-button
					class="uap-logs-action-button uap-logs-action-button--delete"
					size="small"
					color="transparent"
					href="%1$s"
					uap-tooltip="' . esc_attr__( 'Delete row', 'uncanny-automator' ) . '"
					needs-confirmation
					confirmation-heading="%2$s"
					confirmation-content="%3$s"
					confirmation-button-label="%4$s">
						<uo-icon id="trash"></uo-icon>
					</uo-button>';

				$actions['delete'] = sprintf(
					$delete_btn,
					esc_url( $delete_url ),
					esc_attr__( 'This action is irreversible', 'uncanny-automator' ),
					esc_attr__( 'Are you sure you want to delete this run?', 'uncanny-automator' ),
					esc_attr__( 'Confirm', 'uncanny-automator' )
				);

			}

			// $actions = join( ' ', $actions );

			// Layout
			$actions = '<div class="uap-logs-action-buttons">' . $actions['delete'] . $actions['view'] . '</div>';

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
	 * Format trigger log data
	 *
	 * @param array $triggers list of objects
	 *
	 * @return array
	 */
	private function format_trigger_data( $triggers ) {

		$data = array();

		$recipes_data = Automator()->get_recipes_data( false );

		foreach ( $triggers as $trigger ) {

			$trigger_code = $this->item_code( $recipes_data, absint( $trigger->automator_trigger_id ) );

			$trigger_date_completed = $trigger->trigger_date;

			// Show trigger run time instead of the completeion date.
			$trigger_datetime_completed = $this->get_datetime_formatted( $trigger->trigger_run_time );

			if ( false !== $trigger_datetime_completed ) {
				$trigger_date_completed = '
					<div class="uap-logs-complation-date uap-logs-trigger-complation-date">' .
										  esc_html( $trigger_datetime_completed['date'] ) . '
						<span class="uap-logs-complation-date__time">@ ' .
										  esc_html( $trigger_datetime_completed['time'] ) .
										  '</span>
					</div>';
			}

			$current_type = Automator()->utilities->get_recipe_type( $trigger->automator_recipe_id );

			$run_number_log = 'anonymous' === $current_type ? 0 === absint( $trigger->recipe_run_number ) ? 1 : $trigger->recipe_run_number : $trigger->recipe_run_number;

			$actions = '<uap-log-dialog-button log-id="' . esc_attr( $trigger->recipe_log_id ) . '" recipe-id="' . esc_attr( $trigger->automator_recipe_id ) . '" run-number="' . esc_attr( $run_number_log ) . '"></uap-log-dialog-button>';

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
						$trigger_name = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $trigger_sentence ) . '</div>';
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
								<uo-icon id="user-slash-solid"></uo-icon>
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

		$data         = array();
		$recipes_data = Automator()->get_recipes_data( false );

		foreach ( $actions as $action ) {

			$current_type = Automator()->utilities->get_recipe_type( $action->automator_recipe_id );

			$run_number_log = 'anonymous' === $current_type ? 0 === absint( $action->recipe_run_number ) ? 1 : $action->recipe_run_number : $action->recipe_run_number;

			$action_column = $actions = '<uap-log-dialog-button log-id="' . esc_attr( $action->recipe_log_id ) . '" recipe-id="' . esc_attr( $action->automator_recipe_id ) . '" run-number="' . esc_attr( $run_number_log ) . '"></uap-log-dialog-button>';

			// Action status
			$action_status_html = '
				<div class="uap-logs-action-status uap-logs-action-status--' . sanitize_html_class( $this->get_status_class_name( $action->action_completed ) ) . '">' .
								  esc_html( $this->get_status_name( $action->action_completed ) ) . '
				</div>';

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
						$action_name = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $action_title ) . '</div>';
					} else {
						$action_name = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $action_sentence ) . '</div>';
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

			$action_date_completed = $action->action_date;

			$action_datetime_completed = $this->get_datetime_formatted( $action->action_date );

			if ( false !== $action_datetime_completed ) {
				$action_date_completed = '
					<div class="uap-logs-complation-date uap-logs-trigger-complation-date">' .
										 esc_html( $action_datetime_completed['date'] ) . '
						<span class="uap-logs-complation-date__time">@ ' .
										 esc_html( $action_datetime_completed['time'] ) . '
						</span>
					</div>';
			}

			$action_status = apply_filters( 'automator_action_log_status', $action_status_html, $action );
			$error_message = apply_filters( 'automator_action_log_error', $action->error_message, $action );

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
								<uo-icon id="user-slash-solid"></uo-icon>
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
	 * Replaces {{automator_upgrade_link}} with actual upgrade link.
	 *
	 * @param string $message The message.
	 *
	 * @return string The message with upgrade link.
	 */
	public static function format_all_upgrade_links( $message, $action ) {

		$link = 'https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=recipe_log&utm_content=upgrade_to_pro';

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
				echo Pro_Filters::activities_filters_html( $this->tab ); //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
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
			'date' => $datetime->format( 'Y-m-d' ),
			'time' => $datetime->format( 'H:i:s' ),
		);

	}

	/**
	 * Constructs the user HTML of any log
	 *
	 * @param mixed $object The object.
	 *
	 * @return string The HTML of the user log.
	 */
	private function get_user_html( $object ) {

		// Safely convert all user id to integer.
		$user_id = absint( $object->user_id );

		// Handle 'everyone' recipe log.
		if ( empty( $user_id ) ) {
			return '&mdash;';
		}

		$deleted = empty( $object->display_name ) && empty( $object->user_email ) && 0 !== absint( $user_id );

		// Handle logs where user is deleted.
		if ( $deleted ) {
			return '
            <div class="uap-logs-user">
			    <div class="uap-logs-user__avatar-container">
				    <img class="uap-logs-user__avatar" src="' . esc_url( Utilities::automator_get_asset( 'backend/dist/img/gravatar-user-deleted.svg' ) ) . '">
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
			    <a href="' . esc_url( $user_link ) . '" class="uap-logs-user__display-name">' . esc_html( $object->display_name ) . '</a>
		    <div class="uap-logs-user__email">' . esc_html( $object->user_email ) . '</div>
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
		$recipe_name = sprintf(
			'<div class="uap-log-table-recipe-container">
						<a href="%s" class="%s">%s</a>
						<div class="uap-log-table__recipe-integrations">
							<div class="uap-log-table-recipe-integration-triggers">
								%s
							</div>
							<div class="uap-log-table-recipe-integration-actions">
								%s
							</div>
						</div>
					</div>',
			esc_url( $recipe_link ),
			$css_class,
			esc_html( $recipe_name ),
			$this->trigger_icons( $recipe_id ),
			$this->action_icons( $recipe_id )
		);

		return $recipe_name;
	}

	/**
	 * @param $recipes
	 *
	 * @return void
	 */
	private function fetch_triggers_actions_integrations( $recipes ) {
		if ( empty( $recipes ) ) {
			return;
		}
		$recipe_ids = array_column( $recipes, 'automator_recipe_id' );
		global $wpdb;
		//phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$sql     = $wpdb->prepare(
			"SELECT p.post_parent, p.post_type, pm.meta_value AS integration
FROM $wpdb->postmeta pm
JOIN $wpdb->posts p
ON p.ID = pm.post_id
WHERE pm.meta_key = %s
AND p.post_parent IN (" . join( ',', $recipe_ids ) . ')', //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			'integration'
		);
		$results = $wpdb->get_results( $sql ); //phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( empty( $results ) ) {
			return;
		}
		$triggers = array();
		$actions  = array();
		foreach ( $results as $r ) {
			if ( 'uo-action' === $r->post_type ) {
				$actions[ $r->post_parent ][] = $r->integration;
			}
			if ( 'uo-trigger' === $r->post_type ) {
				$triggers[ $r->post_parent ][] = $r->integration;
			}
		}
		$this->trigger_action_integrations = array(
			'triggers' => $triggers,
			'actions'  => $actions,
		);
	}

	/**
	 * @param $recipe_id
	 *
	 * @return string
	 */
	private function trigger_icons( $recipe_id ) {
		$triggers = isset( $this->trigger_action_integrations['triggers'][ $recipe_id ] ) ? $this->trigger_action_integrations['triggers'][ $recipe_id ] : array();
		if ( empty( $triggers ) ) {
			return '';
		}
		$t_icons = array();
		foreach ( $triggers as $integration ) {
			$t_icons[] = '<uo-icon integration="' . $integration . '" hide-missing show-tooltip></uo-icon>';
		}

		return join( PHP_EOL, $t_icons );
	}

	/**
	 * @param $recipe_id
	 *
	 * @return string
	 */
	private function action_icons( $recipe_id ) {
		$actions = isset( $this->trigger_action_integrations['actions'][ $recipe_id ] ) ? $this->trigger_action_integrations['actions'][ $recipe_id ] : array();
		if ( empty( $actions ) ) {
			return '';
		}
		$a_icons = array();
		foreach ( $actions as $integration ) {
			$a_icons[] = '<uo-icon integration="' . $integration . '" hide-missing show-tooltip></uo-icon>';
		}

		return join( PHP_EOL, $a_icons );
	}
}
