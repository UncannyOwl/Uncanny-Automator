<?php

namespace Uncanny_Automator;

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

/**
 * Class Logs_List_Table
 * @package Uncanny_Automator
 */
class Logs_List_Table extends \WP_List_Table {

	/**
	 * $column_list     ARRAY| Setting table heading/columns
	 */
	private $column_list;

	/**
	 * $sortable_columns     ARRAY| Setting sortable table heading/columns
	 */
	private $sortable_columns;

	/**
	 * Set current tab.
	 *
	 * @var $tab     string| Setting tab
	 */
	public $tab;

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
	 * Override function for table navigation.
	 *
	 * @param string $which "top" or "bottom"
	 */
	protected function extra_tablenav( $which ) {

		if ( 'top' === $which ) {

			if ( class_exists( '\uncanny_automator_pro\Pro_Filters' ) ) {
				$filter_html = \uncanny_automator_pro\Pro_Filters::activities_filters_html( $this->tab );
			} else {
				$GLOBALS[ 'ua_current_tab' ] = $this->tab;

				// Start output
				ob_start();

				include Utilities::get_view( 'filters.php' );

				// Get output
				$filter_html = ob_get_clean();
			}

			echo $filter_html;
		}
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
	 * Getting columns
	 *
	 * @return array
	 */
	public function get_columns() {
		return $this->column_list;
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
	 * Override method
	 *
	 * @return array
	 */
	public function get_sortable_columns() {
		return $this->sortable_columns;
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
	 * Prepare items/data
	 *
	 */
	public function prepare_items() {
		global $wpdb, $_wp_column_headers;
		$screen = get_current_screen();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		if ( class_exists( '\uncanny_automator_pro\Pro_Filters' ) ) {
			if ( 'recipe-log' === $this->tab ) {
				$query = \uncanny_automator_pro\Pro_Filters::get_recipe_query();
			} elseif ( 'trigger-log' === $this->tab ) {
				$query = \uncanny_automator_pro\Pro_Filters::get_trigger_query();
			} elseif ( 'action-log' === $this->tab ) {
				$query = \uncanny_automator_pro\Pro_Filters::get_action_query();
			}
		} else {
			if ( 'recipe-log' === $this->tab ) {
				$query = $this->get_recipe_query();
			} elseif ( 'trigger-log' === $this->tab ) {
				$query = $this->get_trigger_query();
			} elseif ( 'action-log' === $this->tab ) {
				$query = $this->get_action_query();
			}
		}

		/* -- Ordering parameters -- */
		$order = ! empty( $_GET["order"] ) ? $wpdb->_real_escape( $_GET["order"] ) : 'DESC';
		if ( 'recipe-log' === $this->tab ) {
			$orderby = ! empty( $_GET["orderby"] ) ? $wpdb->_real_escape( $_GET["orderby"] ) : 'r.ID';
		} elseif ( 'trigger-log' === $this->tab ) {
			$orderby = ! empty( $_GET["orderby"] ) ? $wpdb->_real_escape( $_GET["orderby"] ) : 't.ID';
		} elseif ( 'action-log' === $this->tab ) {
			$orderby = ! empty( $_GET["orderby"] ) ? $wpdb->_real_escape( $_GET["orderby"] ) : 'a.ID';
		}

		if ( ! empty( $query ) && ! empty( $orderby ) && ! empty( $order ) ) {
			$query .= ' ORDER BY ' . $orderby . ' ' . $order;
		}

		/* -- Pagination parameters -- */
		$total_items = $wpdb->query( $query );

		$perpage = 100;

		$paged = $this->get_pagenum();

		if ( empty( $paged ) || ! is_numeric( $paged ) || $paged <= 0 ) {
			$paged = 1;
		}

		$totalpages = ceil( $total_items / $perpage ); //adjust the query to take pagination into account
		if ( ! empty( $paged ) && ! empty( $perpage ) ) {
			$offset = ( $paged - 1 ) * $perpage;
			$query  .= ' LIMIT ' . (int) $offset . ',' . (int) $perpage;
		}

		/* -- Register the pagination -- */
		$this->set_pagination_args( [
			'total_items' => $total_items,
			'total_pages' => $totalpages,
			'per_page'    => $perpage,
		] );

		/* -- Register the Columns -- */
		$columns                           = $this->get_columns();
		$_wp_column_headers[ $screen->id ] = $columns;

		/* -- Fetch the items -- */
		if ( 'recipe-log' === $this->tab ) {
			$this->items = $this->format_recipe_data( $wpdb->get_results( $query ) );
		} elseif ( 'trigger-log' === $this->tab ) {
			$this->items = $this->format_trigger_data( $wpdb->get_results( $query ) );
		} elseif ( 'action-log' === $this->tab ) {
			$this->items = $this->format_action_data( $wpdb->get_results( $query ) );
		}
	}

	/**
	 * Prepare query for recipe
	 *
	 * @return string query
	 */
	private function get_recipe_query() {
		global $wpdb;
		$search_conditions = ' 1=1 AND r.completed != -1 ';
		if ( isset( $_GET['search_key'] ) && '' !== $_GET['search_key'] ) {
			$search_key        = sanitize_text_field( $_GET['search_key'] );
			$search_conditions .= " AND ( (p.post_title LIKE '%{$search_key}%') OR (u.display_name  LIKE '%{$search_key}%' ) OR (u.user_email  LIKE '%{$search_key}%' ) ) ";
		}

		$query = "SELECT r.user_id, 
							r.date_time AS recipe_date_time, 
							r.completed AS recipe_completed, 
							r.run_number, 
							r.automator_recipe_id, 
							u.user_email, 
							u.display_name, 
							p.post_title AS recipe_title 
					FROM {$wpdb->prefix}uap_recipe_log r
					LEFT JOIN {$wpdb->users} u
					ON u.ID = r.user_id  
					JOIN {$wpdb->posts} p
					ON p.ID = r.automator_recipe_id
					WHERE {$search_conditions}";


		return $query;
	}

	/**
	 * Prepare query for triggers
	 *
	 * @return string query
	 */
	private function get_trigger_query() {
		global $wpdb;
		$search_conditions = ' 1=1 ';
		if ( isset( $_GET['search_key'] ) && '' !== $_GET['search_key'] ) {
			$search_key        = sanitize_text_field( $_GET['search_key'] );
			$search_conditions .= " AND ( (pt.post_title LIKE '%{$search_key}%') OR (p.post_title LIKE '%{$search_key}%') OR (u.display_name  LIKE '%{$search_key}%' ) OR (u.user_email  LIKE '%{$search_key}%' ) ) ";
		}

		$query = "SELECT u.ID AS user_id, u.user_email, 
                            u.display_name, 
                            t.automator_trigger_id, 
                            t.date_time AS trigger_date, 
                            t.completed AS trigger_completed, 
                            t.automator_recipe_id, 
                            t.ID, 
                            pt.post_title AS trigger_title, 
                            tm.meta_value AS trigger_sentence, 
                            p.post_title AS recipe_title, 
                            r.date_time AS recipe_date_time, 
                            r.completed AS recipe_completed, 
                            r.run_number AS recipe_run_number
                        FROM {$wpdb->prefix}uap_trigger_log t
                        LEFT JOIN {$wpdb->users} u
                        ON u.ID = t.user_id
                        LEFT JOIN {$wpdb->posts} p
                        ON p.ID = t.automator_recipe_id
                        LEFT JOIN {$wpdb->posts} pt
                        ON pt.ID = t.automator_trigger_id
                        LEFT JOIN {$wpdb->prefix}uap_trigger_log_meta tm
						ON tm.automator_trigger_log_id = t.ID AND tm.meta_key = 'sentence_human_readable'
                        LEFT JOIN {$wpdb->prefix}uap_recipe_log r
                        ON t.automator_recipe_log_id = r.ID
                        WHERE ({$search_conditions}) ";

		return $query;
	}

	/**
	 * Prepare query for actions
	 *
	 * @return string query
	 */
	private function get_action_query() {
		global $wpdb;
		$search_conditions = ' 1=1 ';
		if ( isset( $_GET['search_key'] ) && '' !== $_GET['search_key'] ) {
			$search_key        = sanitize_text_field( $_GET['search_key'] );
			$search_conditions .= " AND ( (pa.post_title LIKE '%{$search_key}%') OR (p.post_title LIKE '%{$search_key}%') OR (u.display_name LIKE '%{$search_key}%' ) OR (u.user_email LIKE '%{$search_key}%' ) OR (a.error_message LIKE '%{$search_key}%' ) ) ";
		}

		$query = "SELECT a.automator_action_id,
					a.date_time AS action_date, 
					a.completed AS action_completed, 
					a.error_message, 
					a.automator_recipe_id,
					r.date_time AS recipe_date_time, 
					r.completed AS recipe_completed, 
					r.run_number AS recipe_run_number,
					pa.post_title AS action_title,
					am.meta_value AS action_sentence, 
					p.post_title AS recipe_title, 
					u.ID AS user_id, 
					u.user_email, 
					u.display_name            
			FROM {$wpdb->prefix}uap_action_log a
			LEFT JOIN {$wpdb->prefix}uap_recipe_log r
			ON a.automator_recipe_log_id = r.ID
			LEFT JOIN {$wpdb->posts} p
			ON p.ID = a.automator_recipe_id
			JOIN {$wpdb->posts} pa
			ON pa.ID = a.automator_action_id
			LEFT JOIN {$wpdb->prefix}uap_action_log_meta am
			ON am.automator_action_id = a.automator_action_id  AND am.meta_key = 'sentence_human_readable'
			LEFT JOIN {$wpdb->users} u
			ON a.user_id = u.ID
			WHERE ({$search_conditions})
			GROUP BY a.ID";

		return $query;
	}

	/**
	 * Format recipes log data
	 *
	 * @param array $recipes list of objects
	 *
	 * @return array
	 */
	private function format_recipe_data( $recipes ) {
		global $uncanny_automator;
		$data = array();
		foreach ( $recipes as $recipe ) {

			if ( isset( $recipe->automator_recipe_id ) ) {
				$recipe_link = get_edit_post_link( absint( $recipe->automator_recipe_id ) );
				$recipe_id   = $recipe->automator_recipe_id;
			} else {
				$recipe_link = '#';
				$recipe_id   = 0;
			}

			$recipe_name = ! empty( $recipe->recipe_title ) ? $recipe->recipe_title : sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $recipe_id );

			if ( '#' !== $recipe_link ) {
				$recipe_name = '<a href="' . $recipe_link . '" class="uap-log-table__recipe-name">' . $recipe_name . '</a>';
			}
			if ( empty( $recipe->display_name ) ) {
				/* translators: User type */
				$user_name = _x( 'Anonymous', 'User', 'uncanny-automator' );
			} else {
				$user_link = get_edit_user_link( absint( $recipe->user_id ) );
				$user_name = '<a href="' . $user_link . '">' . $recipe->display_name . '</a> <br>' . $recipe->user_email;
			}

			if ( 1 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = _x( 'Completed', 'Recipe', 'uncanny-automator' );
			} elseif ( 2 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = _x( 'Completed with errors', 'Recipe', 'uncanny-automator' );
			} elseif ( 9 === (int) $recipe->recipe_completed ) {
				/* translators: Recipe status */
				$recipe_status = _x( 'Completed - do nothing', 'Recipe', 'uncanny-automator' );
			} else {
				/* translators: Recipe status */
				$recipe_status = _x( 'In progress', 'Recipe', 'uncanny-automator' );
			}

			$run_number            = $recipe->run_number;
			$recipe_date_completed = ( 1 === absint( $recipe->recipe_completed ) || 2 === absint( $recipe->recipe_completed ) || 9 === absint( $recipe->recipe_completed ) ) ? $recipe->recipe_date_time : '';
			$current_type          = $uncanny_automator->utilities->get_recipe_type( $recipe_id );

			/* translators: Recipe type. Logged-in recipes are triggered only by logged-in users */
			$recipe_type_name      = _x( 'Logged-in', 'Recipe', 'uncanny-automator' );
			if ( ! empty( $current_type ) ){
				if ( $current_type == 'user' ){
					/* translators: Recipe type. Logged-in recipes are triggered only by logged-in users */
					$recipe_type_name = _x( 'Logged-in', 'Recipe', 'uncanny-automator' );
				}
				elseif ( $current_type == 'anonymous' ){
					/* translators: Recipe type. Anonymous recipes can be triggered by logged-in or anonymous users. Anonymous recipes can create new users or modify existing users. */
					$recipe_type_name = _x( 'Anonymous', 'Recipe', 'uncanny-automator' );
				}
			}

			$data[]                = array(
				'recipe_type'      => $recipe_type_name,
				'recipe_title'     => $recipe_name,
				'recipe_date_time' => $recipe_date_completed,
				'display_name'     => $user_name,
				'recipe_completed' => $recipe_status,
				'run_number'       => $run_number,
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

		global $uncanny_automator;

		$data         = array();
		$recipes_data = $uncanny_automator->get_recipes_data( false );

		foreach ( $triggers as $trigger ) {

			// Log only completed Triggers
			if ( 1 !== absint( $trigger->trigger_completed ) ) {
				continue;
			}

			$trigger_code = $this->item_code( $recipes_data, absint( $trigger->automator_trigger_id ) );

			$trigger_date_completed = $trigger->trigger_date;
			$recipe_link            = get_edit_post_link( absint( $trigger->automator_recipe_id ) );
			/* translators: 1: Post ID */
			$recipe_name = ! empty( $trigger->recipe_title ) ? $trigger->recipe_title : sprintf( __( 'ID: %1$s (no title)', 'uncanny-automator' ), $trigger->automator_recipe_id );

			if ( ! empty( $recipe_link ) ) {
				$recipe_name = '<a href="' . $recipe_link . '" class="uap-log-table__recipe-name">' . $recipe_name . '</a>';
			}

			if ( 1 === absint( $trigger->recipe_completed ) ) {
				/* translators: Trigger status */
				$recipe_status = _x( 'Completed', 'Trigger', 'uncanny-automator' );
			} elseif ( 2 === absint( $trigger->recipe_completed ) ) {
				/* translators: Trigger status */
				$recipe_status = _x( 'Completed with errors', 'Trigger', 'uncanny-automator' );
			} elseif ( 9 === absint( $trigger->recipe_completed ) ) {
				/* translators: Trigger status */
				$recipe_status = _x( 'Completed, do nothing', 'Trigger', 'uncanny-automator' );
			} else {
				/* translators: Trigger status */
				$recipe_status = _x( 'In progress', 'Trigger', 'uncanny-automator' );
			}

			$recipe_date_completed = ( 1 === absint( $trigger->recipe_completed ) || 2 === absint( $trigger->recipe_completed ) || 9 === absint( $trigger->recipe_completed ) ) ? $trigger->recipe_date_time : '';
			if ( is_null( $trigger->user_id ) ) {
				/* translators: User type */
				$user_name = _x( 'Anonymous', 'User', 'uncanny-automator' );
			} else {
				$user_link = get_edit_user_link( absint( $trigger->user_id ) );
				$user_name = '<a href="' . $user_link . '">' . $trigger->display_name . '</a> <br>' . $trigger->user_email;
			}

			/* translators: 1. Trigger ID */
			$trigger_name = sprintf( __( 'Trigger deleted: %1$s', 'uncanny-automator' ), $trigger->automator_trigger_id );

			if ( $trigger_code ) {
				// get the trigger title
				$trigger_title = $trigger->trigger_title;
				// get the triggers completed sentence
				$trigger_sentence = $trigger->trigger_sentence;
				if ( empty( $trigger_title ) && ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
					/* translators: 1. Trademarked term */
					$trigger_name = sprintf( __( '(Reactivate %1$s to view)', 'uncanny-automator' ), 'Uncanny Automator Pro' );
				} else {
					if( empty( $trigger_sentence )){
						$trigger_name = $trigger_title;
					}else{

						$trigger_name = '<div class="triggername">' . $trigger_title . '</div><div class="triggerdetail">' . $this->format_human_readable_senctence( $trigger_sentence ) . '</div>';
					}
				}
			}

			$recipe_run_number = $trigger->recipe_run_number;


			$data[] = array(
				'trigger_id'        => $trigger->ID,
				'trigger_title'     => $trigger_name,
				'trigger_date'      => $trigger_date_completed,
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
	 * Format action log data
	 *
	 * @param array $actions list of objects
	 *
	 * @return array
	 */
	private function format_action_data( $actions ) {
		global $uncanny_automator;

		$data         = array();
		$recipes_data = $uncanny_automator->get_recipes_data( false );

		foreach ( $actions as $action ) {
			$st = __( 'Error', 'uncanny-automator' );
			if ( 1 === (int) $action->action_completed ) {
				/* translators: Action status */
				$st = _x( 'Completed', 'Action', 'uncanny-automator' );
			} elseif ( 9 === (int) $action->action_completed ) {
				/* translators: Action status */
				$st = _x( 'Completed, do nothing', 'Action', 'uncanny-automator' );
			}
			$action_code           = $this->item_code( $recipes_data, absint( $action->automator_action_id ) );
			/* translators: 1. Action ID */
			$action_name = sprintf( __( 'Action deleted: %1$s', 'uncanny-automator' ), $action->automator_action_id );

			if ( $action_code ) {
				// get the action title
				$action_title = $action->action_title;
				// get the action completed sentence
				$action_sentence = $action->action_sentence;
				if ( empty( $action_title ) && ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
					/* translators: 1. Trademarked term */
					$action_name = sprintf( __( '(Reactivate %1$s to view)', 'uncanny-automator' ), 'Uncanny Automator Pro' );
				} else {
					if( empty( $action_sentence )){
						$action_name = $action_title;
					}else{
						$action_name = '<div class="triggername">' . $action_title . '</div><div class="triggerdetail">' .  $this->format_human_readable_senctence($action_sentence ) . '</div>';
					}
				}
			}

			$action_date_completed = $action->action_date;
			$action_status         = $st;
			$error_message         = $action->error_message;
			$recipe_link           = get_edit_post_link( absint( $action->automator_recipe_id ) );
			$recipe_name           = '<a href="' . $recipe_link . '" class="uap-log-table__recipe-name">' . $action->recipe_title . '</a>';

			if ( 1 === (int) $action->recipe_completed ) {
				/* translators: Action status */
				$recipe_status = _x( 'Completed', 'Action', 'uncanny-automator' );
			} elseif ( 2 === (int) $action->recipe_completed ) {
				/* translators: Action status */
				$recipe_status = _x( 'Completed with errors', 'Action', 'uncanny-automator' );
			} elseif ( 9 === (int) $action->recipe_completed ) {
				/* translators: Action status */
				$recipe_status = _x( 'Completed, do nothing', 'Action', 'uncanny-automator' );
			} else {
				/* translators: Action status */
				$recipe_status = _x( 'In progress', 'Action', 'uncanny-automator' );
			}

			$recipe_date_completed = ( 1 === absint( $action->recipe_completed ) || 2 === absint( $action->recipe_completed ) || 9 === absint( $action->recipe_completed ) ) ? $action->recipe_date_time : '';
			$recipe_run_number     = $action->recipe_run_number;
			if ( ! is_null( $action->user_id ) ) {
				$user_link = get_edit_user_link( absint( $action->user_id ) );
				$user_name = '<a href="' . $user_link . '">' . $action->display_name . '</a><br>' . $action->user_email;
			} else {
				/* translators: User type */
				$user_name = _x( 'Anonymous', 'User', 'uncanny-automator' );
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

	private function format_human_readable_senctence($sentence = ''){
		if(empty($sentence)){
			return '';
		}

		$opening = '<span class="uap-logs-table__item-name">';
		$closing = '</span>';

		$opening_replace = '<span class="uap-logs-table-item-name__token">';
		$closing_replace = '</span>';


		$sentence = str_replace(array('{{{{{{{{{{', '{{{{{{{{', '{{{{{{', '{{{{', '{{'), $opening_replace, $sentence);
		$sentence = str_replace(array('}}}}}}}}}}', '}}}}}}}}', '}}}}}}', '}}}}', '}}'), $closing_replace, $sentence);
		return $opening . $sentence . $closing;

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
}
