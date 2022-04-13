<?php if ( ! defined( 'ABSPATH' ) ) {
	return;} ?>

<form method="POST" action="options.php" warn-unsaved>
	
	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">
		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">
				<uo-icon id="twilio"></uo-icon> <?php esc_html_e( 'Twilio', 'uncanny-automator' ); ?>
			</div>

			<div class="uap-settings-panel-content">
				
					<?php 
                        
                        if ( automator_filter_has_var( 'connect' ) ) {

                            $connect = automator_filter_input( 'connect' );

                            $alert_heading = __( "There was an error connecting your Twilio account. Please try again or contact support.", 'uncanny-automator' );
                            $alert_type = 'error';
                            $alert_content = __( "Error: ", 'uncanny-automator' ) . $connect;

                            if ( 1 == $connect ) { 
                                $alert_heading = __( 'You have successfully connected your Twilio account', 'uncanny-automator' );
                                $alert_type = 'success';
                                $alert_content = '';
                            }

                            ?>

                            <uo-alert
                                type="<?php echo esc_attr( $alert_type ); ?>"
                                heading="<?php echo esc_attr( $alert_heading ); ?>"
                                class="uap-spacing-bottom uap-spacing-top"
                            ><?php echo esc_attr( $alert_content ); ?></uo-alert>

                            <?php

                        }

                    ?>

                <?php if ( ! $this->is_connected ) { ?>
                    <div class="uap-settings-panel-content-subtitle">
						<?php esc_html_e( 'Connect Uncanny Automator to Twilio', 'uncanny-automator' ); ?>
					</div>

					<div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
						<?php esc_html_e( 'Integrate your WordPress site directly with Twilio. Send SMS messages to users when they make a purchase, fill out a form, complete a course, or complete any combination of supported triggers.', 'uncanny-automator' ); ?>
					</div>

					<p>
						<strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
					</p>

					<ul>
						<li>
							<uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Send an SMS message to a number', 'uncanny-automator' ); ?>
						</li>
					</ul>

					<div class="uap-settings-panel-content-separator"></div>

					<uo-alert
                        heading="<?php esc_attr_e( 'Setup instructions', 'uncanny-automator' ); ?>"
                    >
                        <?php

                            echo sprintf(
                            	/* translators: 1. Link to Automator knowledge base  */
                                esc_html__( "Connecting to Twilio requires getting 2 values from inside your account. It's really easy, we promise! Visit our %1\$s for simple instructions.", 'uncanny-automator-pro' ),
                                '<a href="' . esc_url( automator_utm_parameters( 'https://automatorplugin.com/knowledge-base/twilio/', 'settings', 'twilio-kb_article' ) ) . '" target="_blank">' . esc_html__( 'Knowledge Base article', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>'
                            );

                        ?>
                    </uo-alert>

                <?php } ?>

                <uo-text-field
                    id="uap_automator_twilio_api_account_sid"
                    value="<?php echo esc_attr( $account_sid ); ?>"
                    required

                    label="<?php esc_attr_e( 'Account SID', 'uncanny-automator' ); ?>"

                    class="uap-spacing-top"

                    <?php echo $this->is_connected ? 'hidden disabled' : ''; ?>
                ></uo-text-field>

                <uo-text-field
                    id="uap_automator_twilio_api_auth_token"
                    value="<?php echo esc_attr( $auth_token ); ?>"
                    required

                    label="<?php esc_attr_e( 'Auth token', 'uncanny-automator' ); ?>"

                    class="uap-spacing-top"

                    <?php echo $this->is_connected ? 'hidden disabled' : ''; ?>
                ></uo-text-field>

                <?php 

                $phone_number_helper = sprintf(
                	/* translators: 1. URL */
            		__( 'See your list of active phone numbers on the %1$s page.', 'uncanny-automator' ),
            		'<a href="https://www.twilio.com/console/phone-numbers/incoming" target="_blank">' . __( 'Active numbers', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>'
            	);

                ?>
                    
                <uo-text-field
                    id="uap_automator_twilio_api_phone_number"
                    value="<?php echo esc_attr( $phone_number ); ?>"
                    placeholder="+15017122661"
                    required

                    label="<?php esc_attr_e( 'Active number', 'uncanny-automator' ); ?>"
                    helper="<?php echo esc_attr( $phone_number_helper ); ?>"

                    class="uap-spacing-top"
                ></uo-text-field>

			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<div class="uap-settings-panel-bottom-left">

					<?php

                    // Check what button we have to add
                    if ( $this->is_connected ) {

                        // Check if we have the username and the ID
                        if ( ! empty( $this->user['friendly_name'] ) ) {
                            ?>

                            <div class="uap-settings-panel-user">

                                <div class="uap-settings-panel-user__avatar">
                                    <?php echo esc_html( strtoupper( $this->user['friendly_name'][0] ) ); ?>
                                </div>

                                <div class="uap-settings-panel-user-info">
                                    <div class="uap-settings-panel-user-info__main">
                                        <?php echo esc_html( $this->user['friendly_name'] ); ?>
                                        <uo-icon id="twilio"></uo-icon>
                                    </div>

                                    <div class="uap-settings-panel-user-info__additional">
                                    	<?php

                                    	printf(
                                    		/* translators: 1. Phone number */
                                    		esc_html__( 'Active number: %1$s', 'uncanny-automator' ),
                                    		esc_html( $phone_number )
                                    	);

                                    	?>
                                    </div>
                                </div>
                            </div>

                            <?php

                        }
                    } else {

                    	?>

                    	<uo-button
							type="submit"
						>
							<?php esc_html_e( 'Connect Twilio account', 'uncanny-automator' ); ?>
						</uo-button>

                    	<?php

                    }

					?>

				</div>

				<div class="uap-settings-panel-bottom-right">

                <?php if ( $this->is_connected ) { ?>

					<uo-button
						href="<?php echo esc_url( $disconnect_uri ); ?>"
						color="danger"
					>
						<uo-icon id="sign-out"></uo-icon>

						<?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
					</uo-button>

					<uo-button
						type="submit"
					>
						<?php esc_html_e( 'Save settings', 'uncanny-automator' ); ?>
					</uo-button>

                <?php } ?>
					
				</div>

				

		</div>

	</div>
	<input type="hidden" name="uap_automator_twilio_api_settings_timestamp" value="<?php esc_attr_e( time() ); ?>" >
</form>
