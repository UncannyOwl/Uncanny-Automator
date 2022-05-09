<?php
$available_tabs = apply_filters(
	'automator_tools_header_tabs',
	array(
		'uncanny-automator-tools'          => esc_html__( 'Status', 'uncanny-automator' ),
		'uncanny-automator-debug-log'      => esc_html__( 'Logs', 'uncanny-automator' ),
		'uncanny-automator-database-tools' => esc_html__( 'Database tools', 'uncanny-automator' ),
	)
);
if ( filter_has_var( INPUT_GET, 'page' ) ) {
	$current_tab = sanitize_text_field( filter_input( INPUT_GET, 'page' ) );
}
?>

	<div class="wrap uap uap-tools">
		<h2 class="tools-header">
			<?php esc_html_e( 'Automator Tools', 'uncanny-automator' ); ?>
		</h2>

		<?php do_action( 'automator_tools_header_after' ); ?>

		<nav class="nav-tab-wrapper uap-nav-tab-wrapper">
			<?php
			foreach ( $available_tabs as $tab => $tab_name ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				?>
				<a href="<?php echo esc_url_raw( admin_url( 'edit.php' ) ); ?>?post_type=uo-recipe&page=<?php echo esc_attr( $tab ); ?>"
				   class="nav-tab <?php echo ( (string) $tab === (string) $current_tab ) ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_attr( $tab_name ); ?>
				</a>
				<?php
			}
			?>
		</nav>
	</div>
<?php
