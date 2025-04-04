<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$this->display_alerts();

settings_fields( $this->get_settings_id() );

$hide_fields = $this->is_connected ? true : '';

$api_key             = automator_get_option( 'automator_twitter_api_key', '' );
$api_secret          = automator_get_option( 'automator_twitter_api_secret', '' );
$access_token        = automator_get_option( 'automator_twitter_access_token', '' );
$access_token_secret = automator_get_option( 'automator_twitter_access_token_secret', '' );

?>

<uo-alert heading="<?php esc_attr_e( 'Setup instructions', 'uncanny-automator' ); ?>" class="uap-spacing-top">
	<?php

	printf(
		// translators: 1: Knowledge base article link
		esc_html__( 'Connect your own X/Twitter developer app by adding the app details in the fields below. Visit our %1$s for full instructions.', 'uncanny-automator' ),
		'<a href="https://automatorplugin.com/knowledge-base/twitter/#use-your-own-twitter-app" target="_blank">' . esc_html__( 'Knowledge Base article', 'uncanny-automator' ) . '</a>'
	);

	?>
</uo-alert>

<?php

$this->text_input_html(
	array(
		'id'       => 'automator_twitter_api_key',
		'value'    => $api_key,
		'label'    => esc_html__( 'API key', 'uncanny-automator' ),
		'required' => true,
		'class'    => 'uap-spacing-top',
		'hidden'   => $hide_fields,
		'disabled' => $hide_fields,
	)
);

$this->text_input_html(
	array(
		'id'       => 'automator_twitter_api_secret',
		'value'    => $api_secret,
		'label'    => esc_html__( 'API key secret', 'uncanny-automator' ),
		'required' => true,
		'class'    => 'uap-spacing-top',
		'hidden'   => $hide_fields,
		'disabled' => $hide_fields,
	)
);

$this->text_input_html(
	array(
		'id'       => 'automator_twitter_access_token',
		'value'    => $access_token,
		'label'    => esc_html__( 'Access token', 'uncanny-automator' ),
		'required' => true,
		'class'    => 'uap-spacing-top',
		'hidden'   => $hide_fields,
		'disabled' => $hide_fields,
	)
);

$this->text_input_html(
	array(
		'id'       => 'automator_twitter_access_token_secret',
		'value'    => $access_token_secret,
		'label'    => esc_html__( 'Access token secret', 'uncanny-automator' ),
		'required' => true,
		'class'    => 'uap-spacing-top',
		'hidden'   => $hide_fields,
		'disabled' => $hide_fields,
	)
);

