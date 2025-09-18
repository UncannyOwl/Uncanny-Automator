# App Integration Framework

This directory contains the abstract classes that form the foundation for Uncanny Automator's App Integration framework. This framework provides a standardized way to build integrations that connect to external services via our API proxy server.

## Overview

The App Integration framework consists of four main abstract classes:

1. **App_Integration** - Main integration class that handles setup and registration
2. **App_Helpers** - Common helper functionality for credentials, settings, and connection status
3. **Api_Caller** - API request management for communicating with the API proxy server
4. **App_Webhooks** - Webhook handling for integrations that support incoming webhooks

## Abstract Classes

### App_Integration

The main integration class that extends the base `Integration` class and provides app-specific functionality - mainly defining properties to be shared with the settings and helpers classes.

#### Required Methods

```php
abstract protected function setup();
abstract protected function is_app_connected();
```

#### Configuration Pattern

```php
public static function get_config() {
    return array(
        'integration'  => 'INTEGRATION_CODE',    // Integration code
        'name'         => 'Integration Name',    // Integration name
        'api_endpoint' => 'v2/endpoint',         // API proxy endpoint
        'settings_id'  => 'settings-id',         // Settings URL/tab ID
    );
}
```

#### Setup Pattern

```php
protected function setup() {
    // Create helpers instance
    $this->helpers = new Integration_Helpers( self::get_config() );
    
    // Set icon URL
    $this->set_icon_url( plugin_dir_url( __FILE__ ) . 'img/icon.svg' );
    
    // Finalize setup
    $this->setup_app_integration( self::get_config() );
}
```

#### Load Pattern

```php
public function load() {
    // Load actions and triggers with dependencies
    new ACTION_CLASS( $this->dependencies );
    new TRIGGER_CLASS( $this->dependencies );
    
    // Load settings with dependencies
    new Integration_Settings( 
        $this->dependencies,
        $this->get_settings_config()
    );
}
```

**Note**: Actions and triggers now extend `App_Action` and `App_Trigger` respectively, which automatically handle dependency injection without manual property setting.

### App_Helpers

Provides common functionality for managing credentials, account information, and integration state.

#### Standardized Methods

The App_Helpers class provides standardized methods to prevent code duplication across integrations:

##### Settings Page URL
```php
// Get settings page URL with optional parameters
$url = $this->get_settings_page_url();
$url = $this->get_settings_page_url(array('param' => 'value'));
```

##### Credentials Management
```php
// Get credentials (throws Exception if not found)
$credentials = $this->get_credentials();

// Store credentials
$this->store_credentials($credentials);

// Delete credentials
$this->delete_credentials();

// Validate credentials (override for custom validation)
$validated = $this->validate_credentials($credentials);
```

##### Account Information

Standardized account handling replaces various naming patterns (Client, Account, User, etc.) with consistent "account" methods:

```php
$account = $this->get_account_info();
$this->store_account_info($account);
$this->delete_account_info();
$validated = $this->validate_account_info($account);
```

**Common Patterns:**
- **API Key**: Account stored in separate option (`automator_{settings_id}_account`)
- **OAuth**: Account extracted from credentials (override `get_account_info()`)
- **Hybrid**: Complex logic in `validate_account_info()` method

#### Key Features

- **Credentials Management**: Automatic option name generation and storage
- **Account Information**: Standardized account info handling with flexible storage patterns
- **Dependency Access**: Direct access to Helpers,API and webhooks instances via properties
- **Connection Status**: Manages integration connection state
- **Settings Integration**: Provides settings page URL generation

#### Credential & Account Management

The framework automatically generates option names and provides standardized methods for credential and account management:

```php
// Auto-generated option names
'automator_{settings_id}_credentials'  // e.g., 'automator_brevo_credentials'
'automator_{settings_id}_account'      // e.g., 'automator_brevo_account'

// Standard methods
$credentials = $this->get_credentials();           // Throws Exception if not found
$this->store_credentials($credentials);
$this->delete_credentials();
$validated = $this->validate_credentials($credentials);

$account = $this->get_account_info();
$this->store_account_info($account);
$this->delete_account_info();
$validated = $this->validate_account_info($account);
```

##### Custom Overrides

Override these methods for custom behavior:

```php
// Custom credential validation/storage
public function validate_credentials($credentials, $args = array()) {
    // Add validation logic
    return $credentials;
}

public function prepare_credentials_for_storage($credentials) {
    // Modify before storage
    return $credentials;
}

// Custom account info (e.g., extract from credentials)
public function get_account_info() {
    try {
        $credentials = $this->get_credentials();
        return array(
            'id'   => $credentials['user_id'],
            'name' => $credentials['user_name'],
        );
    } catch (Exception $e) {
        return array();
    }
}

// Override option names if needed
public function set_properties() {
    $this->set_credentials_option_name('custom_credentials_option');
    $this->set_account_option_name('custom_account_option');
}
```

##### Phase 2: Vault Migration

**Future**: Credentials will migrate to our encrypted vault database with `vault_id` and `vault_signature`. Current patterns are maintained during Phase 1 for backward compatibility.

#### Dependency Management

The framework automatically manages dependencies and provides access to API, webhooks, and other integration components.

**Dependency Setup:**
```php
// Dependencies are set during integration initialization
$this->set_dependency( 'helpers', $this->helpers );
$this->set_dependency( 'api', $this->get_api_instance() );
$this->set_dependency( 'webhooks', $this->get_webhooks_instance() );

// Pass dependencies to helpers for property access
$this->helpers->set_dependencies( $this->dependencies );
```

**Access Patterns:**
```php
// In Helper classes:
$this->api->some_api_method( $params );
$this->webhooks->register_webhook();

// In Settings classes:
$this->helpers->get_credentials();
$this->api->some_api_method( $params );
$this->webhooks->register_webhook();

// In Actions/Triggers:
// When extending App_Action or App_Trigger, dependencies are automatically available:
$this->helpers->get_credentials();  // Direct access to helpers
$this->api->some_api_method();      // Direct access to API
$this->webhooks->some_webhook_method(); // Direct access to webhooks (if available)

// No manual property setting needed - just use @property annotations for IDE support
```

### Api_Caller

Handles all API requests to the Automator API proxy server with built-in error handling and credential management.

#### Key Features

- **Automatic Credential Injection**: Credentials are automatically included in requests
- **Error Handling**: Standardized error handling with custom error messages
- **Request Flexibility**: Support for different request types and parameters
- **Timeout Management**: Configurable request timeouts

#### Usage Pattern

```php
class Integration_Api_Caller extends Api_Caller {
    
    public function set_properties() {
        // Override credential request key if needed
        $this->set_credential_request_key( 'api-key' );
        
        // Register custom error messages
        $this->register_error_messages( array(
            'invalid_credentials' => array(
                'message'   => 'Your connection has expired. [reconnect your account](%s)',
                'help_link' => $this->helpers->get_settings_page_url(),
            ),
        ) );
    }
    
    public function some_api_method( $params ) {
        $body = array(
            'action' => 'some_action',
            'data'   => $params,
        );
        
        return $this->api_request( $body );
    }
}
```

#### API Request Options

```php
$response = $this->api_request( $body, $action_data, array(
    'exclude_credentials' => false,  // Do not include credentials
    'exclude_error_check' => false,  // Skip error checking
    'include_timeout'     => 30,     // Set timeout in seconds
) );
```

### App_Webhooks

Provides webhook functionality for integrations that support incoming webhooks with automatic registration, authorization, and asynchronous processing.

#### Key Features

- **Conditional Registration**: Webhooks are only registered when the integration is connected and webhooks are enabled
- **Automatic Authorization**: Built-in webhook key validation with customizable parameter names
- **Asynchronous Processing**: Fast response times with background processing via shutdown hooks
- **Flexible Endpoints**: Support for custom webhook endpoints and authorization methods
- **Request Validation**: Standardized validation with extensible custom logic

#### Webhook Registration

The framework automatically determines when webhooks should be registered based on two conditions:

```php
public function should_register_webhooks() {
    // Check if the integration is connected
    if ( ! $this->is_connected ) {
        return false;
    }
    
    // Check if webhooks are enabled via stored option
    return $this->get_webhooks_enabled_status();
}
```

**Registration Flow:**
1. **Connection Check**: Integration must be connected (credentials valid)
2. **User Preference**: Webhooks must be enabled by the user via settings
3. **Automatic Setup**: If both conditions are met, webhooks are registered with the Action Manager

**Webhook Status Management:**
```php
// Check if webhooks are enabled
$enabled = $this->get_webhooks_enabled_status();

// Store webhook enabled status
$this->store_webhooks_enabled_status( true );

// Delete webhook status
$this->delete_webhooks_enabled_status();
```

#### Request Handling Flow

The webhook processing follows a specific flow to ensure fast responses and reliable processing:

**1. Request Validation**
```php
public function handle_webhook_request( $request ) {
    // Validate request object
    // Set current request
    // Validate webhook authorization
    // Process webhook callback
}
```

**2. Authorization Validation**
```php
protected function validate_webhook_authorization( $request ) {
    $auth_value = $request->get_param( $this->auth_param );
    return $this->is_valid_webhook_key( $auth_value );
}
```

**3. Asynchronous Processing**
```php
protected function process_webhook_callback( $request ) {
    // Store data for shutdown processing
    $this->shutdown_data = $this->set_shutdown_data( $request );
    
    // Add shutdown hook for processing
    add_action( 'shutdown', array( $this, 'process_shutdown_webhook' ) );
    
    // Return immediate response
    return $this->generate_webhook_response();
}
```

**4. Background Processing**
```php
public function process_shutdown_webhook() {
    // Process webhook data
    // Fire automator actions
    // Clear shutdown data
}
```

#### Usage Pattern

```php
class Integration_Webhooks extends App_Webhooks {
    
    public function set_properties() {
        // Set webhook endpoint (optional - defaults to settings_id)
        $this->set_webhook_endpoint( 'custom-endpoint' );
        
        // Set authorization parameter name
        $this->set_auth_param( 'key' );
    }
    
    protected function process_webhook_callback( $request ) {
        // Process the webhook data
        $data = $this->get_decoded_request_body();
        
        // Trigger automator actions
        do_action( 'automator_webhook_integration_event', $data );
    }
    
    // Optional: Custom validation
    protected function validate_webhook_request( $request ) {
        // Add custom validation logic
        $data = $this->get_decoded_request_body();
        
        if ( empty( $data['event_type'] ) ) {
            return false;
        }
        
        return true;
    }
}
```

#### Webhook Management

**Key Generation:**
```php
// Generate new webhook key
$key = $this->regenerate_webhook_key();

// Get current webhook key (regenerates if empty)
$key = $this->get_webhook_key();

// Get authorized webhook URL
$url = $this->get_authorized_url();
```

**Request Data Access:**
```php
// Get raw request body
$body = $this->get_raw_request_body();

// Get decoded JSON body
$data = $this->get_decoded_request_body();

// Get request parameters
$params = $this->get_request_params();

// Get specific header
$header = $this->get_request_header( 'X-Custom-Header' );
```

**Timestamp Validation:**
```php
// Check if webhook timestamp is within acceptable range
$is_recent = $this->is_timestamp_acceptable( $timestamp, 10 );
```

## Recipe Parts

The framework provides specialized abstract classes for actions and triggers that eliminate the need for manual dependency setup:

### App_Action

Extends the base `Action` class to provide clean access to app integration dependencies without manual property setting.

**Key Benefits:**
- **No manual property declarations** - Properties are automatically available
- **Full IDE support** - Use `@property` annotations for type-specific autocomplete
- **Clean inheritance** - Minimal boilerplate in extending classes

**Usage Pattern:**
```php
use Uncanny_Automator\Recipe\App_Action;

/**
 * @property IntegrationName_App_Helpers $helpers
 * @property IntegrationName_Api_Caller $api
 * @property IntegrationName_Webhooks $webhooks
 */
class My_Action extends App_Action {
    // Dependencies are automatically available:
    // $this->helpers, $this->api, $this->webhooks
    
    protected function setup_action() {
        // Use dependencies directly
        $this->set_integration( 'INTEGRATION_NAME' );
        $this->set_action_code( 'ACTION_CODE' );
    }
    
    protected function process_action( $user_id, $action_data, $recipe_id, $args, $parsed ) {
        // Full IDE support for API methods
        $result = $this->api->some_api_method( $params );
        
        // Full IDE support for helper methods
        $credentials = $this->helpers->get_credentials();
        
        return true;
    }
}
```

### App_Trigger

Extends the base `Trigger` class with the same dependency injection benefits as `App_Action`.

**Usage Pattern:**
```php
use Uncanny_Automator\Recipe\App_Trigger;

/**
 * @property IntegrationName_App_Helpers $helpers
 * @property IntegrationName_Api_Caller $api
 * @property IntegrationName_Webhooks $webhooks
 */
class My_Trigger extends App_Trigger {
    // Same clean dependency access as App_Action
}
```

**IDE Support:**
- **Type-specific autocomplete** via `@property` annotations
- **No property redeclaration** needed in child classes
- **Full method signature validation**
- **Go to definition** support for integration-specific methods

## Integration Structure

A complete integration using the new framework should have the following structure:

```
src/integrations/integration-name/
├── integration-name-integration.php      // Main integration class
├── helpers/
│   ├── integration-name-helpers.php      // App_Helpers implementation
│   └── integration-name-api-caller.php   // Api_Caller implementation
├── settings/
│   └── integration-name-settings.php     // App_Integration_Settings implementation
├── actions/
│   └── action-classes.php
├── triggers/
│   └── trigger-classes.php
└── webhooks/
    └── integration-name-app-webhooks.php     // App_Webhooks implementation (optional)
```

## Migration Checklist

When migrating an integration to the new framework:

### Phase 1: Framework Migration

- [ ] Create new integration class extending `App_Integration`
- [ ] Create helpers class extending `App_Helpers`
- [ ] Create API caller class extending `Api_Caller`
- [ ] Create settings class extending `App_Integration_Settings`
- [ ] **Update actions to extend `App_Action`** with `@property` annotations for IDE support
- [ ] **Update triggers to extend `App_Trigger`** with `@property` annotations for IDE support
- [ ] Update integration to use new framework
- [ ] Test all functionality
- [ ] Maintain existing option names and endpoints

### Phase 2: Standardization (Future)

- [ ] Migrate credentials to vault system
- [ ] Update option names to follow new conventions
- [ ] Standardize webhook endpoints
- [ ] Update any remaining legacy patterns
- [ ] Remove migration-specific code

## Best Practices

### Configuration

- Use the `get_config()` static method for all integration configuration
- Keep configuration centralized and consistent
- Use descriptive settings IDs that match the integration name

### Error Handling

- Register custom error messages in the API caller
- Provide helpful error messages with action links
- Use consistent error handling patterns

### Credential & Account Management

- Use the framework's automatic credential and account management methods
- Override behavior only when necessary for backward compatibility
- Follow standard patterns for API key, OAuth, and hybrid integrations
- Prepare for Phase 2 vault migration by using consistent patterns

### Webhook Implementation

- Only implement webhooks if the integration actually supports them
- Use the standard webhook patterns when possible
- Provide clear documentation for webhook setup

## Examples

See the following integrations for complete examples:

- **Brevo**: `src/integrations/brevo/` - API key-based integration
- **Discord**: `src/integrations/discord/` - OAuth-based integration with webhooks
- **Stripe**: `src/integrations/stripe/` - OAuth-based integration with webhooks

## Support

For questions about implementing the App Integration framework, refer to:

1. Existing migrated integrations for patterns
2. The abstract class documentation
3. The settings framework documentation in `src/core/lib/settings/`

## Notes

- This framework is designed to work with the Automator API proxy server
- All external API calls should go through the proxy server, not directly to external services
- The framework maintains backward compatibility during migration
- Phase 2 will standardize all patterns and remove migration-specific code 