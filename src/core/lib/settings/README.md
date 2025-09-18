# App Integration Settings Framework

This directory contains the abstract classes and traits that form the foundation for Uncanny Automator's App Integration Settings framework. This framework provides a standardized way to build settings pages for integrations that connect to external services.

## Overview

The App Integration Settings framework consists of one main abstract class and several traits that provide specific functionality:

### Main Abstract Class
- **App_Integration_Settings** - Main settings class that handles setup, registration, and templating

### Core Traits (Included by Default)
- **Premium_Integration_Templating** - Main templating functionality and page rendering
- **Premium_Integration_Templating_Helpers** - UI components and templating utilities
- **Premium_Integration_Items** - Discovery of available actions and triggers
- **Premium_Integration_Alerts** - Alert notification system
- **Settings_Options** - Option registration and management
- **Premium_Integration_Rest_Processing** - REST API request handling

### Conditional Traits (Use as Needed)
- **OAuth_App_Integration** - OAuth-specific functionality
- **Premium_Integration_Webhook_Settings** - Webhook configuration patterns

## App_Integration_Settings

The main settings class provides app-specific functionality with standardized templating and uap_option management.

### Required Methods

```php
abstract protected function get_formatted_account_info();
```

This method must return an array with account information for the connected user display that utilizes the `output_connected_user_info` method:

### Dependency Access

The settings class receives and provides access to integration dependencies:

```php
// Direct property access
$this->api->some_api_method( $params );
$this->webhooks->register_webhook();
$this->helpers->get_credentials();

// Full dependency access
$this->dependencies->api->some_api_method( $params );
$this->dependencies->webhooks->register_webhook();
$this->dependencies->helpers->get_credentials();
```

**Constructor Parameters:**
```php
// First parameter: Dependencies object
// Second parameter: Settings configuration array
new Integration_Settings( $this->dependencies, $this->get_settings_config() );
```
```php
return array(
    'avatar_type'    => 'icon', // 'icon', 'image', or 'text'
    'avatar_value'   => $this->get_icon(),
    'main_info'      => 'user@example.com',
    'main_info_icon' => false,
    'additional'     => 'Additional user info',
);
```

**Note**: You should not need to override `output_bottom_left_connected_content()` - the framework automatically handles the connected user display using this account information.

### Configuration Pattern

```php
public function setup_settings_properties() {
    // Get settings from dependencies
    if ( ! empty( $this->settings ) ) {
        $this->set_settings( $this->settings );
    }
    
    // Allow extending class to set additional properties
    $this->set_properties();
}
```

### Connection Status Methods

The framework automatically handles different content based on connection status:

```php
// Called when integration is connected
public function set_connected_properties() {
    // Set properties for connected state
}

// Called when integration is disconnected  
public function set_disconnected_properties() {
    // Set properties for disconnected state
}
```

## Core Traits

### Premium_Integration_Templating

Provides the main templating functionality and page rendering logic. This trait handles the overall page structure and content rendering.

#### Key Features
- **Page Structure**: Standard panel structure with top, content, and bottom sections
- **Content Rendering**: Main content methods for connected/disconnected states
- **Hook System**: Extensive hook system for Pro extensions to add content
- **Automatic Button Handling**: Standard buttons (connect, disconnect, save settings)

#### Pro Extension Integration
The trait provides hooks that allow Pro extensions to add content to existing free integrations:
- `automator_settings_main_connected_content_{integration_id}`
- `automator_settings_main_disconnected_content_{integration_id}`
- `automator_settings_bottom_left_connected_content_{integration_id}`
- `automator_settings_bottom_right_connected_content_{integration_id}`

### Premium_Integration_Templating_Helpers

Provides helper methods for UI components and templating utilities that can be shared between free and Pro settings classes.

#### Key Features
- **UI Components**: Pre-built components for common UI patterns (buttons, inputs, switches)
- **Templating Utilities**: Helper methods for building CSS classes, generating links, etc.
- **Shared Functionality**: Methods that can be used by both free and Pro extensions

#### Usage
```php
// UI Components
$this->output_action_button( 'custom_action', 'Custom Action' );
$this->output_switch( array( 'id' => 'enable_feature', 'checked' => true ) );
$this->output_settings_table( $columns, $data );

// Templating Utilities
$class_string = $this->build_css_class_string( 'base-class', $additional_classes );
$link = $this->get_escaped_link( $url, $text );
```

### Premium_Integration_Items

Automatically discovers and displays available actions and triggers for the integration.

#### Usage
```php
// Automatically called by templating trait
public function output_available_items() {
    // Lists all available triggers and actions
    // Uses get_available_triggers() and get_available_actions()
}
```

### Premium_Integration_Alerts

Provides a transient-based alert system for displaying success/error messages on reload without polluting url parameters and methods for generating formatted messages for REST API responses.

#### Usage Patterns

**1. Page Reload Required (Transient-based alerts)**
Use when the page needs to reload to show the alert:

```php
// Register alerts for next page load
$this->register_success_alert( 'Connection successful!' );
$this->register_error_alert( 'Invalid API key' );
$this->register_warning_alert( 'Connection expiring soon' );

// Ensure page reloads to show the alert
$response['reload'] = true;

// Display alerts (automatically called by templating)
$this->display_alerts();
```

**2. No Page Reload Required (REST response alerts)**
Use when you want to show the alert immediately without page reload:

```php
// Get formatted alert for REST response
$alert = $this->get_success_alert( 'Connection successful!' );
$alert = $this->get_error_alert( 'Invalid API key' );
$alert = $this->get_warning_alert( 'Connection expiring soon' );

// Include in REST response
$response['alert'] = $alert;
```

#### Alert Methods

**Register Methods (for page reloads):**
```php
$this->register_success_alert( $message, $heading );
$this->register_error_alert( $message, $heading );
$this->register_warning_alert( $message, $heading );
$this->register_info_alert( $message, $heading );
```

**Get Methods (for REST responses):**
```php
$alert = $this->get_success_alert( $message, $heading );
$alert = $this->get_error_alert( $message, $heading );
$alert = $this->get_warning_alert( $message, $heading );
$alert = $this->get_info_alert( $message, $heading );
```

### Settings_Options

Provides standardized uap_option registration, validation, and storage without using WordPress Settings API.

#### Usage
```php
public function register_options() {
    $this->register_option( 'api_key', array(
        'type'     => 'text',
        'default'  => '',
        'sanitize_callback' => 'sanitize_text_field'
    ) );
    
    $this->register_option( 'enable_feature', array(
        'type'     => 'checkbox',
        'default'  => false,
        'sanitize_callback' => 'rest_sanitize_boolean'
    ) );
}

// Options are automatically validated and stored during authorization or save_settings
```

### Premium_Integration_Rest_Processing

Handles REST API requests with standardized action processing.

#### Standard Actions
- `authorize` - Handle authorization flow
- `disconnect` - Handle disconnection
- `save_settings` - Save additional settings
- `oauth_init` - Initialize OAuth flow

#### Custom Actions
```php
// Custom action handling
public function handle_custom_action( $data, $response ) {
    // Process custom action
    return $response;
}

// Called via REST with action: 'custom_action'
```

## Conditional Traits

### OAuth_App_Integration

Provides OAuth-specific functionality for integrations that use OAuth authentication.

#### Phase 1 Configuration (Current)

During the migration phase, different integrations may use different parameter names. The trait allows customization:

```php
class Integration_Settings extends App_Integration_Settings {
    use OAuth_App_Integration;
    
    public function set_properties() {
        // OAuth action name (varies by integration)
        $this->oauth_action = 'authorization_request';
        
        // Redirect parameter name (varies by integration)
        $this->redirect_param = 'redirect_url';
        
        // Error parameter name (varies by integration)
        $this->error_param = 'error';
    }
    
    // OAuth connect button is automatically output
    public function output_bottom_left_disconnected_content() {
        $this->output_oauth_connect_button();
    }
}
```

#### Custom OAuth Arguments

For integrations that need to add custom parameters to the OAuth request (like Discord server ID or Stripe account type):

```php
public function maybe_filter_oauth_args( $args, $data ) {
    // Add custom parameters to OAuth request
    $args['custom_param'] = $data['custom_value'] ?? '';
    
    return $args;
}
```

**Examples:**
- **Discord**: Adds server ID for server-specific OAuth
- **Stripe**: Adds account type for different Stripe account types
- **Other integrations**: Add any integration-specific OAuth parameters

#### Cleanup on Disconnect

Override `before_disconnect()` to register options for automatic cleanup:

```php
protected function before_disconnect( $response = array(), $data = array() ) {
    // Register options for automatic cleanup
    $this->register_option( 'custom_option_name' );
    
    return $response;
}
```

#### Phase 2 Migration (Future)

In Phase 2, these parameter names will be standardized across all integrations:
- OAuth action names will be normalized
- Redirect parameter names will be consistent
- Error parameter names will be standardized

The current customization methods will be deprecated in favor of standardized patterns.

#### OAuth Flow

The trait handles the complete OAuth flow:

1. **Initiation**: `handle_oauth_init()` builds the OAuth URL with proper parameters
2. **Callback Processing**: `process_oauth_authentication()` handles the OAuth callback
3. **Credential Storage**: Automatically stores validated credentials
4. **Account Verification**: Optional `authorize_account()` method for additional verification
5. **Success/Error Alerts**: Automatic alert registration for OAuth results

### Premium_Integration_Webhook_Settings

Provides webhook configuration patterns for integrations that support webhooks.

#### Usage
```php
class Integration_Settings extends App_Integration_Settings {
    use Premium_Integration_Webhook_Settings;
    
    public function output_main_connected_content() {
        // Output webhook settings
        $this->output_webhook_settings();
    }
    
    public function output_webhook_content() {
        // Custom webhook instructions
        $this->output_webhook_instructions( array(
            'sections' => array(
                array(
                    'type'    => 'text',
                    'content' => 'Configure webhooks in your account...'
                ),
                $this->get_webhook_regeneration_button()
            )
        ) );
    }
}
```

## Best Practices

### Use Templating Methods

Focus on the main content methods and let the framework handle standard buttons automatically:

```php
// Good: Override main content methods
public function output_main_connected_content() {
    $this->output_single_account_message();
    $this->output_panel_subtitle( 'Additional Information' );
    $this->output_subtle_panel_paragraph( 'Help text here' );
}

public function output_main_disconnected_content() {
    $this->output_disconnected_header();
    $this->output_setup_instructions( 'Get your API key...' );
}

// Avoid: Overriding bottom panel methods unless needed
public function output_bottom_left_connected_content() {
    // Only override for custom functionality
}
```

### Leverage Dependency Access

Use the injected dependencies for direct access to integration components:

```php
// Direct property access
public function output_main_connected_content() {
    // Make API calls directly
    $account = $this->api->get_account_info();
    
    // Access webhooks functionality
    $webhook_url = $this->webhooks->get_webhook_url();
    
    // Use helper methods
    $credentials = $this->helpers->get_credentials();
}
```

### Leverage Option Registration

Use the framework's option registration system for consistent handling and cleanup on disconnect:

```php
public function register_connected_options() {
    $this->register_option( 'webhook_url', array(
        'type' => 'text',
        'default' => ''
    ) );
}

public function register_disconnected_options() {
    $this->register_option( 'api_key', array(
        'type' => 'text',
        'default' => ''
    ) );
}
```

### Use Action Buttons

For custom functionality, use `output_action_button()` for consistent REST integration:

```php
$this->output_action_button( 'custom_action', 'Custom Action', array(
    'color' => 'primary',
    'icon'  => 'gear',
    'confirm' => array(
        'heading' => 'Confirm Action',
        'content' => 'Are you sure?',
        'button'  => 'Continue'
    )
) );
```

**Note**: Standard buttons (connect, disconnect, save settings) are automatically handled by the framework.

### Implement Custom Actions

Create handler methods for custom actions:

```php
public function handle_custom_action( $data, $response ) {
    // Process the action
    $result = $this->process_custom_action( $data );
    
    if ( $result ) {
        $response['success'] = true;
        $this->register_success_alert( 'Action completed successfully' );
    } else {
        $response['success'] = false;
        $this->register_error_alert( 'Action failed' );
    }
    
    return $response;
}
```

## New Lit Components

### uap-app-integration-settings-list

A flexible settings list component for displaying table/card data with interactive rows.

#### Key Features
- **Row-specific submissions**: Only submits data for specific rows
- **Queue processing**: Background processing for multiple rows
- **Loading states**: Individual row loading indicators
- **Dynamic cell rendering**: Flexible cell content through component loader

#### Usage
```php
$this->output_settings_table( 
    $columns, 
    $data, 
    'table', 
    true, 
    array( 'rateLimit' => 1000 )
);
```

For detailed documentation, see: `src/assets/src/features/app-integration-settings/components/list/README.md`

### uap-app-integration-settings-section

A component for showing/hiding content based on definable state changes.

#### Key Features
- **State-based visibility**: Show/hide content based on form state
- **Conditional rendering**: Dynamic content based on user interactions
- **Integration with switches**: Automatic state management with form controls

#### Usage
```php
$this->output_app_integration_section( array(
    'id'              => 'webhook-details',
    'section-type'    => 'webhook-details',
    'state'           => 'webhook-enabled',
    'show-when'       => '1',
    'content'         => $webhook_content
) );
```

For detailed documentation, see: `src/assets/src/features/app-integration-settings/components/section/README.md`

## Migration from Premium_Integration_Settings

When migrating from the old `Premium_Integration_Settings`:

1. **Extend App_Integration_Settings** instead of `Premium_Integration_Settings`
2. **Use templating methods** instead of overriding large output methods
3. **Register options** using the new option registration system
4. **Use action buttons** for all interactive elements
5. **Implement custom actions** with `handle_{action_name}` methods
6. **Leverage Lit components** for complex UI patterns
7. **Access dependencies directly** via `$this->api`, `$this->webhooks`, etc.

## Future Phases

The templating framework is designed to support future phases where settings pages will be generated from JSON configuration passed to Lit components. Using the templating methods now ensures compatibility with this future architecture.

## Examples

See the following integrations for complete examples:

- **Brevo**: `src/integrations/brevo/settings/` - API key-based integration
- **Discord**: `src/integrations/discord/settings/` - OAuth-based integration with webhooks
- **Stripe**: `src/integrations/stripe/settings/` - OAuth-based integration with webhooks

## Support

For questions about implementing the App Integration Settings framework, refer to:

1. Existing migrated integrations for patterns
2. The trait documentation in this directory
3. The Lit component documentation in the assets directory 