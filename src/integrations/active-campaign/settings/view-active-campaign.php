<?php if ( ! defined( 'ABSPATH' ) ) {
	return;} ?>

<form method="POST" action="options.php" warn-unsaved>

	<?php settings_fields( $this->get_settings_id() ); ?>

	<div class="uap-settings-panel">

		<div class="uap-settings-panel-top">

			<div class="uap-settings-panel-title">

				<uo-icon id="active-campaign"></uo-icon> <?php esc_html_e( 'ActiveCampaign', 'uncanny-automator' ); ?>

			</div>

			<div class="uap-settings-panel-content">

                     <?php 
                        
                        if ( automator_filter_has_var( 'connect' ) ) {

                            $connect = automator_filter_input( 'connect' );

                            $alert_heading = __( "There was an error connecting your ActiveCampaign account. Please try again or contact support.", 'uncanny-automator' );
                            $alert_type = 'error';
                            $alert_content = __( "Error: ", 'uncanny-automator' ) . $connect;

                            if ( 1 == $connect ) { 
                                $alert_heading = __( 'You have successfully connected your ActiveCampaign account', 'uncanny-automator' );
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
                        <?php esc_html_e( 'Connect Uncanny Automator to ActiveCampaign', 'uncanny-automator' ); ?>
                    </div>

                    <div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                        <?php esc_html_e( "Connect Uncanny Automator to ActiveCampaign to better segment and engage with your customers.  Add and update contacts and add/remove tags based on a user's activity on your WordPress site, or automatically perform actions on your users when tags are added or removed in ActiveCampaign.", 'uncanny-automator' ); ?>
                    </div>

                    <p>
                        <strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
                    </p>

                    <ul>
                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'A tag is added to a contact', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Trigger:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'A tag is removed from a contact', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a contact to a list', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a contact to Active Campaign', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a tag to a contact', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a tag to the user', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add the user to a list', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add the user to Active Campaign', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove a contact from a list', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove a tag from a contact', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove a tag from the user', 'uncanny-automator' ); ?>
                        </li>

                        <li>
                            <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove the user from a list', 'uncanny-automator' ); ?>
                        </li>
                    </ul>

                    <div class="uap-settings-panel-content-separator"></div>

                    <uo-alert heading="<?php esc_attr_e( 'Setup instructions', 'uncanny-automator' ); ?>">

                        <?php esc_html_e( 'To obtain your ActiveCampaign API URL and Key, follow these steps in your ActiveCampaign account:', 'uncanny-automator' ); ?>

                        <ol class="uap-spacing-top uap-spacing-top--small uap-spacing-bottom uap-spacing-bottom--none">
                            <li><?php esc_html_e( 'Click the "Settings" option located in the left side navigation menu.', 'uncanny-automator' ); ?></li>
                            <li><?php esc_html_e( 'The Account Settings menu will appear. Click the "Developer" option.', 'uncanny-automator' ); ?></li>
                            <li><?php esc_html_e( 'The Developer Settings page will load and will display your ActiveCampaign API URL and Key.', 'uncanny-automator' ); ?></li>
                        </ol>
                    
                    </uo-alert>

                <?php } ?>

                <uo-text-field
                    id="uap_active_campaign_api_url"
                    value="<?php echo esc_attr( $this->account_url ); ?>"

                    label="<?php esc_attr_e( 'Account URL', 'uncanny-automator' ); ?>"
                    required

                    <?php echo $this->is_connected ? 'hidden disabled' : ''; ?>

                    class="uap-spacing-top"
                ></uo-text-field>

                <uo-text-field
                    id="uap_active_campaign_api_key"
                    value="<?php echo esc_attr( $this->api_key ); ?>"

                    label="<?php esc_attr_e( 'API key', 'uncanny-automator' ); ?>"
                    required

                    <?php echo $this->is_connected ? 'hidden disabled' : ''; ?>

                    class="uap-spacing-top"
                ></uo-text-field>

                <?php if ( $this->is_connected ) { ?>

                    <uo-alert
                        heading="<?php echo esc_attr( $this->button_labels['default'] ); ?>"
                        type="info"
                    >
                        <p class="uap-spacing-bottom uap-spacing-bottom--none">
                            <uo-button id="active-campaign-local-syn-btn" color="secondary">
                                <?php esc_html_e( 'Refresh', 'uncanny-automator' ); ?>
                            </uo-button>
                        </p>
                    </uo-alert>

                    <div class="uap-settings-panel-content-separator"></div>

                    <uo-switch id="uap_active_campaign_enable_webhook"  <?php echo esc_attr( $this->enable_triggers ); ?> label="<?php esc_attr_e( 'Enable triggers', 'uncanny-automator' ); ?>"></uo-switch>

                    <div id="uap-activecampaign-webhook" style="display:none;">

                        <uo-alert
                            heading="<?php esc_attr_e( 'Setup instructions', 'uncanny-automator' ); ?>"
                            class="uap-spacing-top"
                        >

                            <p>
                                <?php

                                    echo sprintf(
                                        esc_html__( "Enabling ActiveCampaign triggers requires setting up a webhook in your ActiveCampaign account using the URL below. A few steps and you'll be up and running in no time. Visit our %1\$s for simple instructions.", 'uncanny-automator-pro' ),
                                        '<a href="' . esc_url( $this->kb_link ) . '" target="_blank">' . esc_html__( 'Knowledge Base article', 'uncanny-automator' ) . ' <uo-icon id="external-link"></uo-icon></a>'
                                    );

                                ?>
                            </p>

                            <uo-text-field
                                value="<?php echo esc_url( $this->webhook_url ); ?>"
                                label="<?php esc_attr_e( 'Webhook URL', 'uncanny-automator' ); ?>"
                                helper="<?php esc_attr_e( "You'll be asked to enter a webhook URL.", 'uncanny-automator' ); ?>"
                                disabled
                            ></uo-text-field>

                            <uo-button
                                onclick="return confirm('<?php echo esc_html( $this->regenerate_alert ); ?>');"
                                href="<?php esc_attr_e( $this->regenerate_key_url ); ?>"
                                size="small"
                                color="secondary"
                                class="uap-spacing-top"
                            >
                                <uo-icon id="sync"></uo-icon> 
                                <?php esc_attr_e( 'Regenerate webhook URL', 'uncanny-automator' ); ?>
                            </uo-button>
                        
                        </uo-alert>

                    </div>

                <?php } ?> 
                    
			</div>

		</div>

		<div class="uap-settings-panel-bottom">

				<div class="uap-settings-panel-bottom-left">

					<?php

                    if ( $this->is_connected ) {
                        $user = array_shift( $this->users );
                        ?>

                        <div class="uap-settings-panel-user">

                            <div class="uap-settings-panel-user__avatar">
                                <?php echo esc_html( strtoupper( $user['firstName'][0] ) ); ?>
                            </div>

                            <div class="uap-settings-panel-user-info">
                                <div class="uap-settings-panel-user-info__main">
                                    <?php echo esc_html( $user['firstName'] . ' ' . $user['lastName'] ); ?>
                                    <uo-icon id="active-campaign"></uo-icon>
                                </div>
                                <div class="uap-settings-panel-user-info__additional">
                                    <?php echo esc_html( $user['email'] ); ?>
                                </div>
                            </div>
                        </div>

                        <?php

                    } else {

                        ?>

                        <uo-button
                            type="submit"
                        >
                            <?php esc_html_e( 'Connect ActiveCampaign account', 'uncanny-automator' ); ?>
                        </uo-button>

                        <?php

                    }

					?>

				</div>

				<div class="uap-settings-panel-bottom-right">

                <?php if ( $this->is_connected ) { ?>

					<uo-button
						href="<?php echo esc_url( $this->disconnect_url ); ?>"
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
    <input type="hidden" name="uap_active_campaign_settings_timestamp" value="<?php esc_attr_e( time() ); ?>" >
</form>
