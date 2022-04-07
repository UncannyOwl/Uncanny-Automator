<?php if ( ! defined( 'ABSPATH' ) ) {
	return;} ?>

<div class="uap-settings-panel">
    <div class="uap-settings-panel-top">

        <div class="uap-settings-panel-title">
            <uo-icon id="hubspot"></uo-icon> <?php esc_html_e( 'HubSpot', 'uncanny-automator' ); ?>
        </div>

        <div class="uap-settings-panel-content">

            <?php

            if ( ! $this->is_connected ) { ?>

                <div class="uap-settings-panel-content-subtitle">
                    <?php esc_html_e( 'Connect Uncanny Automator to HubSpot', 'uncanny-automator' ); ?>
                </div>

                <div class="uap-settings-panel-content-paragraph uap-settings-panel-content-paragraph--subtle">
                    <?php esc_html_e( 'Connect Uncanny Automator to Hubspot to better segment and engage with your customers.  Automatically add users to lists and update your HubSpot contacts based on user activity on your WordPress site.', 'uncanny-automator' ); ?>
                </div>

                <p>
                    <strong><?php esc_html_e( 'Activating this integration will enable the following for use in your recipes:', 'uncanny-automator' ); ?></strong>
                </p>

                <ul>
                    <li>
                        <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add a HubSpot contact to a static list', 'uncanny-automator' ); ?>
                    </li>
                    <li>
                        <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( "Add the user's HubSpot contact to a static list", 'uncanny-automator' ); ?>
                    </li>
                    <li>
                        <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Add/Update the user in HubSpot', 'uncanny-automator' ); ?>
                    </li>
                    <li>
                        <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Create/Update a contact in HubSpot', 'uncanny-automator' ); ?>
                    </li>
                    <li>
                        <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( 'Remove a HubSpot contact from a static list', 'uncanny-automator' ); ?>
                    </li>
                    <li>
                        <uo-icon id="bolt"></uo-icon> <strong><?php esc_html_e( 'Action:', 'uncanny-automator' ); ?></strong> <?php esc_html_e( "Remove the user's HubSpot contact from a static list", 'uncanny-automator' ); ?>
                    </li>
                </ul>

                <?php

            } else {

                if ( $just_connected && ! empty( $token_info['user'] ) ) {

					// Alert title
					$alert_title = sprintf(
						/* translators: 1. The Slack workspace name */
						_x( 'Your account "%1$s" has been connected successfully!', 'HubSpot', 'uncanny-automator' ),
						$token_info['user']
					);

					?>

					<uo-alert
						type="success"
						heading="<?php echo esc_attr( $alert_title ); ?>"
						class="uap-spacing-bottom"
					></uo-alert>

				<?php }
                ?>

                <uo-alert
                    heading="<?php esc_html_e( 'Uncanny Automator only supports connecting to one HubSpot account.', 'uncanny-automator' ); ?>"
                ></uo-alert>

                <?php

            }

            ?>

        </div>

    </div>

    <div class="uap-settings-panel-bottom">

            <div class="uap-settings-panel-bottom-left">

                <?php

                // Check what button we have to add
                if ( $this->is_connected ) {

                    // Check if we have the username and the ID
                    if ( ! empty( $token_info['user'] ) ) { ?>

                        <div class="uap-settings-panel-user">

                            <div class="uap-settings-panel-user__avatar">
                                <?php echo esc_html( strtoupper( $token_info['user'][0] ) ); ?>
                            </div>

                            <div class="uap-settings-panel-user-info">
                                <div class="uap-settings-panel-user-info__main">
                                    <?php echo esc_html( $token_info['user'] ); ?>
                                    <uo-icon id="hubspot"></uo-icon>
                                </div>
                                <div class="uap-settings-panel-user-info__additional">
                                    <?php
                                    if ( ! empty( $token_info['hub_domain'] ) ) {
                                        echo esc_html(
                                            sprintf(
                                                /* translators: 1. ID */
                                                __( 'Hub Domain: %1$s', 'uncanny-automator' ),
                                                $token_info['hub_domain']
                                            )
                                        );
                                    }

                                    ?>
                                </div>
                            </div>
                        </div>

                        <?php

                    }
                } else {

                    ?>

                    <uo-button
                        href="<?php echo esc_url( $connect_url ); ?>"
                    >
                        <?php esc_html_e( 'Connect HubSpot account', 'uncanny-automator' ); ?>
                    </uo-button>

                    <?php

                }

                ?>

            </div>

            <div class="uap-settings-panel-bottom-right">

                <?php if ( $this->is_connected ) { ?>

                    <uo-button
                        href="<?php echo esc_url( $disconnect_url ); ?>"
                        color="danger"
                    >
                        <uo-icon id="sign-out"></uo-icon>

                        <?php esc_html_e( 'Disconnect', 'uncanny-automator' ); ?>
                    </uo-button>
                <?php } ?>
            </div>

    </div>

</div>

