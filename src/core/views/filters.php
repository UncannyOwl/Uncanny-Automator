<?php

$post_type    = sanitize_text_field( automator_filter_input( 'post_type' ) );
$form_action  = admin_url( 'edit.php' ) . '?post_type=uo-recipe';
$search_query = automator_filter_has_var( 'search_key' ) ? sanitize_text_field( automator_filter_input( 'search_key' ) ) : '';
$current_tab  = $GLOBALS['ua_current_tab'];

?>

<div class="uap">
	<div class="uap-report">
		<form class="uap-report-filters" method="GET" action="<?php echo esc_url_raw( $form_action ); ?>">

			<input type="hidden" name="post_type" value="<?php echo esc_attr( $post_type ); ?>"/>
			<input type="hidden" name="page" value="uncanny-automator-<?php echo esc_attr( $current_tab ); ?>"/>
			<input type="hidden" name="tab" value="<?php echo esc_attr( $current_tab ); ?>"/>

			<div class="uap-report-filters-content">
				<div class="uap-report-filters-left">
					<?php

					switch ( $current_tab ) {
						case 'recipe-log':
							?>

							<div class="uap-report-filters-filter">
								<select disabled>
									<option><?php esc_attr_e( 'All recipes', 'uncanny-automator' ); ?></option>
								</select>
							</div>

							<div class="uap-report-filters-filter">
								<select disabled>
									<option><?php esc_attr_e( 'All users', 'uncanny-automator' ); ?></option>
								</select>
							</div>

							<div class="uap-report-filters-filter">
								<input type="text"
									   placeholder="<?php esc_attr_e( 'Recipe completion date', 'uncanny-automator' ); ?>"
									   disabled>
							</div>

							<div class="uap-report-filters-filter uap-report-filters-filter--submit">
								<div class="button button--disabled">
									<?php

									/* translators: Non-personal infinitive verb */
									esc_attr_e( 'Filter', 'uncanny-automator' );

									?>
								</div>
							</div>

							<?php
							break;

						case 'trigger-log':
							?>

							<div class="uap-report-filters-filter">
								<select disabled>
									<option><?php esc_attr_e( 'All recipes', 'uncanny-automator' ); ?></option>
								</select>
							</div>

							<div class="uap-report-filters-filter">
								<select disabled>
									<option><?php esc_attr_e( 'All triggers', 'uncanny-automator' ); ?></option>
								</select>
							</div>

							<div class="uap-report-filters-filter">
								<select disabled>
									<option><?php esc_attr_e( 'All users', 'uncanny-automator' ); ?></option>
								</select>
							</div>

							<div class="uap-report-filters-filter">
								<input type="text"
									   placeholder="<?php esc_attr_e( 'Recipe completion date', 'uncanny-automator' ); ?>"
									   disabled>
							</div>

							<div class="uap-report-filters-filter">
								<input type="text"
									   placeholder="<?php esc_attr_e( 'Trigger completion date', 'uncanny-automator' ); ?>"
									   disabled>
							</div>

							<div class="uap-report-filters-filter uap-report-filters-filter--submit">
								<div class="button button--disabled">
									<?php

									/* translators: Non-personal infinitive verb */
									esc_attr_e( 'Filter', 'uncanny-automator' );

									?>
								</div>
							</div>

							<?php
							break;

						case 'action-log':
							?>

							<div class="uap-report-filters-filter">
								<select disabled>
									<option><?php esc_attr_e( 'All recipes', 'uncanny-automator' ); ?></option>
								</select>
							</div>

							<div class="uap-report-filters-filter">
								<select disabled>
									<option><?php esc_attr_e( 'All actions', 'uncanny-automator' ); ?></option>
								</select>
							</div>

							<div class="uap-report-filters-filter">
								<select disabled>
									<option><?php esc_attr_e( 'All users', 'uncanny-automator' ); ?></option>
								</select>
							</div>

							<div class="uap-report-filters-filter">
								<input type="text"
									   placeholder="<?php esc_attr_e( 'Recipe completion date', 'uncanny-automator' ); ?>"
									   disabled>
							</div>

							<div class="uap-report-filters-filter">
								<input type="text"
									   placeholder="<?php esc_attr_e( 'Action completion date', 'uncanny-automator' ); ?>"
									   disabled>
							</div>

							<div class="uap-report-filters-filter uap-report-filters-filter--submit">
								<div class="button button--disabled">
									<?php

									/* translators: Non-personal infinitive verb */
									esc_attr_e( 'Filter', 'uncanny-automator' );

									?>
								</div>
							</div>

							<?php
							break;
					}

					?>

					<div class="uap-report-filters__pro-notice">
						<div class="uap-report-filters__pro-notice-text">
							<?php

							/* translators: 1. Trademarked term */
							printf( esc_attr__( 'Upgrade to %1$s for advanced log filters!', 'uncanny-automator' ), '<a href="https://automatorplugin.com/pricing/?utm_source=uncanny_automator&utm_medium=logs&utm_campaign=update_to_pro&utm_content=advanced-log-filters" target="_blank">Uncanny Automator Pro</a>' );

							?>
						</div>
					</div>
				</div>
				<div class="uap-report-filters-right">
					<div class="uap-report-filters-search">
						<input type="text" name="search_key" value="<?php echo esc_attr( $search_query ); ?>"
							   class="uap-report-filters-search__field"/>
						<input type="submit" name="filter_action" value="
						<?php
						/* translators: Non-personal infinitive verb */
						esc_attr_e( 'Search', 'uncanny-automator' );
						?>
						" class="button uap-report-filters-search__submit">
					</div>
				</div>
			</div>

		</form>
	</div>
</div>
