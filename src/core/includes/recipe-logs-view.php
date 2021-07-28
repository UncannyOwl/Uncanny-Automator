<?php
namespace Uncanny_Automator;

use Uncanny_Automator\Logs_List_Table;

$current_tab = 'recipe-log';
$current     = $current_tab;

$available_tabs = apply_filters(
	'automator_logs_header_tabs',
	array(
		//'uncanny-automator-recipe-activity' => esc_attr__( 'Recipe activity', 'uncanny-automator' ),
		'uncanny-automator-recipe-log'  => esc_attr__( 'Recipe log', 'uncanny-automator' ),
		'uncanny-automator-trigger-log' => esc_attr__( 'Trigger log', 'uncanny-automator' ),
		'uncanny-automator-action-log'  => esc_attr__( 'Action log', 'uncanny-automator' ),
	)
);
if ( automator_filter_has_var( 'page' ) ) {
	$current     = automator_filter_input( 'page' );
	$current_tab = str_replace( 'uncanny-automator-', '', $current );
}
// Set default query for list table.
if ( ! automator_filter_has_var( 'order' ) && ! automator_filter_has_var( 'orderby' ) ) {
	$_GET['order'] = 'desc';
	if ( 'recipe-log' === $current_tab ) {
		$_GET['orderby'] = 'recipe_date_time';
	} elseif ( 'trigger-log' === $current_tab ) {
		$_GET['orderby'] = 'trigger_date';
	} elseif ( 'action-log' === $current_tab ) {
		$_GET['orderby'] = 'action_date';
	}
}
/**
 * @param $current_tab
 */
function automator_setup_recipe_logs( $current_tab ) {
	$headings = array(
		/* translators: Log column. */
		//'recipe_type'      => esc_attr__( 'Recipe type', 'uncanny-automator' ),
		/* translators: Log column. */
		'recipe_title'     => esc_attr__( 'Recipe', 'uncanny-automator' ),
		/* translators: Log column. The recipe status */
		'recipe_completed' => esc_attr__( 'Status', 'uncanny-automator' ),
		/* translators: Log column. The recipe completion date */
		'recipe_date_time' => esc_attr__( 'Completion date', 'uncanny-automator' ),
		/* translators: Log column. Noun. The recipe iteration */
		'run_number'       => esc_attr__( 'Run #', 'uncanny-automator' ),
		/* translators: Log column. */
		'display_name'     => esc_attr__( 'User', 'uncanny-automator' ),
		// Added: actions
		'actions'          => esc_attr__( 'Actions', 'uncanny-automator' ),
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
}

/**
 * @param $current_tab
 * @param array $args
 */
function automator_setup_trigger_logs( $current_tab, $args = array() ) {

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

	$headings = wp_parse_args( $args, $headings );

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

	$sortables = apply_filters( 'automator_setup_trigger_logs_sortables', $sortables );

	//Prepare Table of elements
	$wp_list_table = new Logs_List_Table();
	$wp_list_table->set_columns( $headings );
	$wp_list_table->set_sortable_columns( $sortables );
	$wp_list_table->set_tab( $current_tab );
	$wp_list_table->prepare_items();
	$wp_list_table->display();
}

/**
 * @param $current_tab
 * @param array $args
 */
function automator_setup_action_logs( $current_tab, $args = array() ) {

	$headings = array(
		/* translators: Log column. */
		'action_title'      => esc_attr__( 'Action', 'uncanny-automator' ),
		/* translators: Log column. The action completion date */
		'action_date'       => esc_attr__( 'Date', 'uncanny-automator' ),
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

	$headings = wp_parse_args( $args, $headings );

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

	$sortables = apply_filters( 'automator_setup_action_logs_sortables', $sortables );

	//Prepare Table of elements
	$wp_list_table = new Logs_List_Table();
	$wp_list_table->set_columns( $headings );
	$wp_list_table->set_sortable_columns( $sortables );
	$wp_list_table->set_tab( $current_tab );
	$wp_list_table->prepare_items();
	$wp_list_table->display();
}

/**
 * @param $current_tab
 */
function automator_setup_activity_logs( $current_tab ) {
	$headings = array(
		/* translators: Log column. */
		//'recipe_type'      => esc_attr__( 'Recipe type', 'uncanny-automator' ),
		/* translators: Log column. */
		'recipe_title'     => esc_attr__( 'Recipe', 'uncanny-automator' ),
		/* translators: Log column. The recipe status */
		'recipe_completed' => esc_attr__( 'Status', 'uncanny-automator' ),
		/* translators: Log column. The recipe completion date */
		'recipe_date_time' => esc_attr__( 'Completion date', 'uncanny-automator' ),
		/* translators: Log column. Noun. The recipe iteration */
		'run_number'       => esc_attr__( 'Run #', 'uncanny-automator' ),
		/* translators: Log column. */
		'display_name'     => esc_attr__( 'User', 'uncanny-automator' ),
		/* translators: Log column. */
		'actions'          => esc_attr__( 'Actions', 'uncanny-automator' ),
	);

	$sortables = array(
		'recipe_title'     => array( 'recipe_title', true ),
		'recipe_date_time' => array( 'recipe_date_time', true ),
		'display_name'     => array( 'display_name', true ),
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
}

?>
<div class="wrap uap">
	<nav class="nav-tab-wrapper uap-nav-tab-wrapper">
		<?php

		foreach ( $available_tabs as $tab => $tab_name ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			?>
			<a href="<?php echo esc_attr( admin_url( 'edit.php' ) ); ?>?post_type=uo-recipe&page=<?php echo esc_attr( $tab ); ?>"
			   class="nav-tab <?php echo (string) $tab === (string) $current ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_attr( $tab_name ); ?>
			</a>
			<?php
		}
		?>
	</nav>
	<section class="uap-logs">
		<div class="uap-log-table-container">

			<?php
			if ( 'uncanny-automator-recipe-activity-details' === automator_filter_input( 'page' ) ) {

				include Utilities::automator_get_view( 'recipe-logs-details.php' );

			} else {
				switch ( $current_tab ) {

					case 'recipe-activity':
						automator_setup_activity_logs( $current_tab );

						break;
					case 'recipe-log':
						automator_setup_recipe_logs( $current_tab );

						break;
					case 'trigger-log':
						automator_setup_trigger_logs( $current_tab );

						break;
					case 'action-log':
						automator_setup_action_logs( $current_tab );

						break;
					default:
						do_action( 'automator_log_body', $current_tab );
						break;
				}
			}
			?>
		</div>
	</section>
</div>
