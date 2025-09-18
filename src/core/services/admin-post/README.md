# Admin Post Routing System

A clean, dependency-injection-based routing system for handling WordPress `admin_post` actions in Uncanny Automator.

## Overview

This system provides a structured way to handle form submissions and admin actions through WordPress's `admin_post` hooks, with proper dependency injection and separation of concerns.

## Key Benefits

- **Dependency Injection**: Clean, testable architecture with injectable dependencies
- **Centralized Registration**: All routes managed in one place
- **Type Safety**: Clear method signatures and dependencies
- **Reusable Pattern**: Consistent approach for all admin-post handlers
- **Easy Testing**: Can inject mock dependencies for unit tests

## Architecture

```
src/core/services/admin-post/
├── routes.php              # Core routing class
├── routes-registry.php     # Route registration
├── routes/                 # Individual route handlers
│   └── pro-auto-install.php
└── README.md              # This file
```

## Quick Start

### 1. Create a Route Handler

```php
<?php

namespace Uncanny_Automator\Services\Admin_Post\Routes;

use Exception;

class Example_Handler {

    private $some_service;

    public function __construct( $some_service ) {
        $this->some_service = $some_service;
    }

    public function process_form() {
        // Validate security
        check_admin_referer( 'example_nonce', 'example_nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            throw new Exception( 'Insufficient permissions.' );
        }

        // Process form data
        $user_input = sanitize_text_field( $_POST['user_input'] ?? '' );
        
        try {
            $result = $this->some_service->process( $user_input );
            
            wp_safe_redirect( admin_url( 'admin.php?page=success' ) );
            exit;
            
        } catch ( Exception $e ) {
            wp_safe_redirect( add_query_arg( 
                'error', 
                urlencode( $e->getMessage() ), 
                admin_url( 'admin.php?page=error' ) 
            ) );
            exit;
        }
    }
}
```

### 2. Register Your Route

In `routes-registry.php`:

```php
<?php

final class Routes_Registry {

    public static function register() {
        
        // Register your route with dependencies
        Admin_Post_Routes::add(
            'example_action',
            new Example_Handler( self::create_example_service() ),
            'process_form'
        );

        // Register all routes
        Admin_Post_Routes::register_routes();
    }

    private static function create_example_service() {
        return new Some_Service_Class();
    }
}
```

### 3. Create Your Form

```php
<?php
$form_action = Admin_Post_Routes::get_url( 'example_action' );
?>

<form method="post" action="<?php echo esc_url( $form_action ); ?>">
    <?php wp_nonce_field( 'example_nonce', 'example_nonce' ); ?>
    
    <input type="text" name="user_input" required />
    <input type="submit" value="Submit" class="button button-primary" />
</form>
```

## Real-World Example: Pro Auto Install

The Pro Auto Install feature demonstrates the system in action:

### Handler Class
```php
class Pro_Auto_Install {
    
    private $upgrader;

    public function __construct( Plugin_Upgrader $upgrader ) {
        $this->upgrader = $upgrader;
    }

    public function process_installation() {
        // Validates license, downloads, installs, and activates Pro plugin
    }
}
```

### Registration
```php
Admin_Post_Routes::add(
    'uncanny_automator_pro_auto_install',
    new Pro_Auto_Install( self::create_upgrader() ),
    'process_installation'
);

private static function create_upgrader() {
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    
    return new Plugin_Upgrader( new Silent_Upgrader_Skin() );
}
```

## API Reference

### Admin_Post_Routes

#### `add( $action, $instance, $method )`
Register a new admin-post route.

- `$action` (string) - The action name (will be prefixed with `admin_post_`)
- `$instance` (object) - Instance of your handler class with dependencies injected
- `$method` (string) - Method name to call on the instance

#### `get_url( $action )`
Get the admin-post URL for an action.

- `$action` (string) - The action name
- Returns: Full URL to the admin-post endpoint

#### `register_routes()`
Register all added routes with WordPress. Call this after adding all routes.

#### `get_routes()`
Get all registered routes (useful for debugging).

## Best Practices

### Security
Always validate nonces and user capabilities:

```php
public function handle_request() {
    // Verify nonce
    check_admin_referer( 'your_nonce_action', 'your_nonce_field' );
    
    // Check capabilities
    if ( ! current_user_can( 'manage_options' ) ) {
        throw new Exception( 'Insufficient permissions.' );
    }
    
    // Process request...
}
```

### Error Handling
Use consistent error handling with redirects:

```php
try {
    // Process logic
    wp_safe_redirect( $success_url );
    exit;
} catch ( Exception $e ) {
    wp_safe_redirect( add_query_arg( 
        'error', 
        urlencode( $e->getMessage() ), 
        $error_url 
    ) );
    exit;
}
```

### Dependency Injection
Create dependencies in the registry, not in the handler:

```php
// ✅ Good - dependencies created in registry
Admin_Post_Routes::add(
    'action',
    new Handler( $dependency1, $dependency2 ),
    'method'
);

// ❌ Bad - dependencies created in handler
class Handler {
    public function __construct() {
        $this->dep = new Dependency(); // Tightly coupled
    }
}
```

### Testing
With proper dependency injection, you can easily test handlers:

```php
public function test_handler() {
    $mock_service = $this->createMock( Service::class );
    $mock_service->expects( $this->once() )
               ->method( 'process' )
               ->willReturn( 'success' );
    
    $handler = new Handler( $mock_service );
    // Test the handler...
}
```

## Adding New Routes

1. Create your handler class in `routes/`
2. Add route registration in `Routes_Registry::register()`
3. Create any necessary dependency factories
4. Create your form with proper nonce and action URL
5. Test the complete flow

## Notes

- All routes require users to be logged in (WordPress `admin_post` behavior)
- Routes are automatically prefixed with `admin_post_` by WordPress
- Dependencies should be created in `Routes_Registry`, not in handlers
- Always use proper nonce verification and capability checks
- Use `wp_safe_redirect()` and `exit` for redirects

## Troubleshooting

### Route Not Working
- Check that `Routes_Registry::register()` is being called
- Verify the action name matches between registration and form
- Ensure nonce field names match between form and handler

### Permission Errors
- Verify `current_user_can()` check matches user's actual capabilities
- Check that user is logged in before accessing admin-post

### Dependencies Not Working
- Ensure dependencies are created in registry, not handler constructor
- Verify all required classes are properly imported
- Check that dependency factories return correct instances