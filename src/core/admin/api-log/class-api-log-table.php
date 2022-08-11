<?php

namespace Uncanny_Automator;

use Uncanny_Automator_Pro\Pro_Filters;
use WP_List_Table;

if ( ! class_exists( 'WP_List_Table' ) ) {
	include_once ABSPATH . '/wp-admin/includes/class-wp-list-table.php';
}

/**
 * Class Api_Log_Table
 *
 * @package Uncanny_Automator
 */
class Api_Log_Table extends WP_List_Table {

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

		add_filter( 'automator_action_log_error', array( Logs_List_Table::class, 'format_all_upgrade_links' ), 10, 2 );

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

		if ( class_exists( '\Uncanny_Automator_Pro\Pro_Filters' ) && method_exists( '\Uncanny_Automator_Pro\Pro_Filters', 'get_api_query' ) ) {
			$query = Pro_Filters::get_api_query();
		} else {
			$query = $this->get_api_query();
		}

		// /* -- Ordering parameters -- */
		$orderby = ! empty( automator_filter_input( 'orderby' ) ) ? $wpdb->_real_escape( automator_filter_input( 'orderby' ) ) : 'date';
		$order   = ! empty( automator_filter_input( 'order' ) ) ? $wpdb->_real_escape( automator_filter_input( 'order' ) ) : 'DESC';

		$query .= " ORDER BY $orderby $order";

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

		$this->items = $this->format_api_data( $wpdb->get_results( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
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
	 * Prepare query for api interactions
	 *
	 * @return string query
	 */
	private function get_api_query() {
		global $wpdb;
		$view_exists       = Automator_DB::is_view_exists( 'api' );
		$search_conditions = ' 1=1 ';

		if ( automator_filter_has_var( 'search_key' ) && '' !== automator_filter_input( 'search_key' ) ) {
			$search_key = sanitize_text_field( automator_filter_input( 'search_key' ) );
			if ( $view_exists ) {
				$search_conditions .= " AND ( (recipe_title LIKE '%$search_key%') OR (title LIKE '%$search_key%') OR (display_name LIKE '%$search_key%' ) OR (user_email LIKE '%$search_key%' ) OR (error_message LIKE '%$search_key%' ) ) ";
			} else {
				$search_conditions .= " AND ( (p.post_title LIKE '%$search_key%') OR (pa.post_title LIKE '%$search_key%') OR (u.display_name LIKE '%$search_key%' ) OR (u.user_email LIKE '%$search_key%' ) OR (al.error_message LIKE '%$search_key%' ) ) ";
			}
		}

		if ( automator_filter_has_var( 'recipe_id' ) && '' !== automator_filter_input( 'recipe_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND automator_recipe_id = '" . absint( automator_filter_input( 'recipe_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND al.automator_recipe_id = '" . absint( automator_filter_input( 'recipe_id' ) ) . "' ";
			}
		}

		if ( automator_filter_has_var( 'recipe_log_id' ) && '' !== automator_filter_input( 'recipe_log_id' ) ) {
			if ( $view_exists ) {
				$search_conditions .= " AND recipe_log_id = '" . absint( automator_filter_input( 'recipe_log_id' ) ) . "' ";
			} else {
				$search_conditions .= " AND al.automator_recipe_log_id = '" . absint( automator_filter_input( 'recipe_log_id' ) ) . "' ";
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
			return "SELECT * FROM {$wpdb->prefix}uap_api_logs_view WHERE ($search_conditions)";
		} else {
			$sql = Automator_DB::api_log_view_query( false );

			return "$sql WHERE ($search_conditions) GROUP BY al.ID";
		}
	}

	/**
	 * Format api log data
	 *
	 * @param array $requests list of objects
	 *
	 * @return array
	 */
	private function format_api_data( $requests ) {

		$data         = array();
		$recipes_data = Automator()->get_recipes_data( false );

		foreach ( $requests as $request ) {

			$completed = Automator_Status::name( $request->completed );

			$code = $this->item_code( $recipes_data, absint( $request->item_id ) );
			/* translators: 1. Action ID */
			$name = sprintf( esc_attr__( 'Action deleted: %1$s', 'uncanny-automator' ), $request->item_id );

			if ( $code ) {
				// get the action title
				$title = $request->title;
				// get the action completed sentence
				$sentence = $request->sentence;

				if ( empty( $title ) && ! defined( 'AUTOMATOR_PRO_FILE' ) ) {
					/* translators: 1. Trademarked term */
					$name = sprintf( esc_attr__( '(Reactivate %1$s to view)', 'uncanny-automator' ), 'Uncanny Automator Pro' );
				} else {
					if ( empty( $sentence ) ) {
						$name = '<div class="uap-logs-table__item-main-sentence">' . $title . '</div>';
					} else {
						$name = '<div class="uap-logs-table__item-main-sentence">' . $this->format_human_readable_sentence( $sentence ) . '</div>';
					}
				}
			}

			$type          = $request->type;
			$date          = $request->date;
			$completed     = apply_filters( 'automator_api_log_status', $completed, $request );
			$error_message = apply_filters( 'automator_api_log_error', $request->error_message, $request );
			$recipe_link   = get_edit_post_link( absint( $request->automator_recipe_id ) );
			$recipe_name   = '<a href="' . $recipe_link . '" class="uap-log-table__recipe-name">' . $request->recipe_title . '</a>';

			$recipe_status   = Automator_Status::name( $request->completed );
			$recipe_finished = Automator_Status::finished( $request->completed );

			$recipe_date_completed = $recipe_finished ? $request->recipe_date_time : '';
			$recipe_run_number     = $request->recipe_run_number;
			$recipe_run_number     = 'anonymous' === (string) Automator()->utilities->get_recipe_type( absint( $request->automator_recipe_id ) ) ? 'N/A' : $recipe_run_number;

			if ( ! is_null( $request->user_id ) ) {
				$user_link       = get_edit_user_link( absint( $request->user_id ) );
				$user_email_link = sprintf(
					'<a href="mailto:%1$s" title="%2$s">(%1$s)</a>',
					sanitize_email( $request->user_email ),
					esc_attr__( 'Send Email', 'uncanny-automator' )
				);
				$user_name       = '<a href="' . $user_link . '">' . $request->display_name . '</a><br>' . $user_email_link;
			} else {
				/* translators: User type */
				$user_name = esc_attr_x( 'N/A', 'User', 'uncanny-automator' );
			}

			$status = $request->status;

			$buttons = array();

			if ( ! empty( $request->params ) && 'action' === $type ) {
				$buttons['resend'] = Api_Log::resend_button_html( $request->item_log_id );
			}

			$price   = 0;
			$balance = '';

			$balance = $request->balance;
			$price   = $request->price;

			$time_spent = $request->time_spent;

			$data[] = array(
				'type'              => 'action' === $request->type ? __( 'Outgoing', 'uncanny-automator' ) : __( 'Incoming', 'uncanny-automator' ),
				'title'             => $recipe_name . ' > ' . $name,
				'date'              => $date,
				'recipe_title'      => $recipe_name,
				'recipe_completed'  => $recipe_status,
				'recipe_date_time'  => $recipe_date_completed,
				'recipe_run_number' => $recipe_run_number,
				'display_name'      => $user_name,
				'request'           => var_export( $request, true ), //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
				'time_spent'        => $time_spent,
				'status'            => $status,
				'completed'         => $completed,
				'error_message'     => apply_filters( 'automator_action_log_error', $error_message, $request ),
				'balance'           => $balance,
				'price'             => empty( $price ) ? 0 : $price,
				'actions'           => join( ' ', $buttons ),
				'endpoint'          => $request->endpoint,
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
