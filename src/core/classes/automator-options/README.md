### Automator Options: Caching and Facade for `uap_options`

This module provides a high-performance options layer that:

- Reads from a custom `uap_options` table with precedence over `wp_options`.
- Adds multi-level caching to reduce database load.
- Preserves legacy value encoding (e.g., `__true__`, `__false__`, `__null__`).
- Exposes simple facade functions for WordPress developers.

Files of interest:

- `class-automator-options.php` — public API and flow control.
- `class-automator-options-cache.php` — in-process and object cache manager.
- `class-automator-options-query.php` — raw database access (always returns raw/serialized values).
- `class-automator-option-formatter.php` — legacy value encoding/decoding.
- Facade helpers in `src/global-functions.php`.

### Quick start (facade helpers)

Use the global helpers. They are stable and WordPress-friendly.

```php
// Read an option with a default (uses cache).
$value = automator_get_option( 'my_option_key', 'default-value' );

// Force a fresh DB read for the latest value (bypasses shared cache for this call only).
$fresh_value = automator_get_option( 'my_option_key', 'default-value', true );

// Add a new option (returns false if it already exists).
automator_add_option( 'my_option_key', array( 'a' => 1 ), true ); // autoload = true

// Upsert/update an option (strict comparison; identical values return true without writing).
automator_update_option( 'my_option_key', array( 'a' => 2 ), true );

// Delete an option.
automator_delete_option( 'my_option_key' );
```

If you need the full object, call `automator_options()` to get the `Automator_Options` instance.

### Public API (object)

- `get_option( string $key, $default = null, bool $skip_cache = false )`
  - Returns the decoded option value, or `$default` when the option does not exist.
  - Set `$skip_cache = true` to bypass caches and read straight from the database. This does not update the shared object cache.

- `add_option( string $key, $value, bool $autoload = false ): bool`
  - Inserts a new option into `uap_options`. Returns false if it already exists.

- `update_option( string $key, $value, bool $autoload = false ): bool`
  - Upserts the option. Uses strict comparison (`===`) to detect no-op updates.

- `delete_option( string $key ): bool`
  - Deletes the option from `uap_options` and clears caches.

### How caching works

Caching is multi-layered for speed and to prevent duplicate DB hits:

- In-process memory cache: fastest cache for the current request.
- WordPress object cache: shared cache (`wp_cache_*`) across requests.
- Miss cache: negative caching to avoid repeated DB queries for missing keys.

Cache keys and groups:

- Group: `uap_options`.
- Option values: prefix `uap_opts_option_{$key}` stored in object cache (serialized DB value).
- Misses: prefix `uap_opts_miss_{$key}` stored in object cache (short TTL).

Hookable TTLs:

- `uap_options_cache_ttl` — default 3600 seconds.
- `uap_options_cache_miss_ttl` — default 300 seconds.

Autoload warmup:

- On construction, the system warms the in-process cache with:
  - All `uap_options` where `autoload = 'yes'`.
  - Selected `wp_options` that are autoloaded (see `array-option-keys.php`).
  - Values from `uap_options` take precedence over `wp_options`.

### Why `MISS` and `ABSENT`?

The cache uses two sentinels to express different states:

- `ABSENT`: “This cache layer has no information.”
  - Used internally to keep checking other layers or the database.

- `MISS`: “This key is known to be missing.”
  - Set when a DB lookup confirms the option does not exist.
  - Stored both in memory and in object cache with a short TTL to avoid repeated DB hits.

Null vs default:

- A stored `null` is different from “missing”. If an option is stored as `null`, reads return `null` (not the default). Only missing options return the provided `$default`.

### Value encoding and types

- Booleans are stored using legacy strings: `__true__` and `__false__`.
- `null` is stored as `__null__`.
- Other values are stored as-is and serialized when needed.
- On read, values are decoded and unserialized by `Automator_Option_Formatter::format_value()`.
- Strict equality is used on update: e.g., `"1"` and `1` are different.

### Hooks

- Filters
  - `uap_options_cache_ttl` (int seconds)
  - `uap_options_cache_miss_ttl` (int seconds)

- Actions
  - `uap_options_db_error( string $error, string $key )` — fires when a DB error occurs during reads.

### Practical examples

Store and read booleans and nulls:

```php
automator_update_option( 'feature_enabled', true );
automator_update_option( 'optional_value', null );

$enabled = automator_get_option( 'feature_enabled', false ); // true
$maybe   = automator_get_option( 'optional_value', 'fallback' ); // null (stored null is respected)
$other   = automator_get_option( 'unknown_key', 'fallback' ); // 'fallback' (missing => default)
```

Force a fresh read (skip caches) when another process may have just updated the DB:

```php
$latest = automator_get_option( 'external_mutated_key', '', true );
```

Autoload a small frequently-used option:

```php
// Mark as autoloaded; future requests may have it pre-warmed in memory.
automator_update_option( 'ui_prefs', array( 'compact_mode' => true ), true );
```

Strict no-op update behavior:

```php
// If the value is identical (===), this returns true without touching the DB.
automator_update_option( 'limit', 10 );
automator_update_option( 'limit', 10 ); // no-op, returns true
```

### Best practices

- Prefer the facade helpers (`automator_get_option`, `automator_update_option`, etc.).
- Use `$skip_cache = true` only when you truly need a fresh DB read.
- Keep autoloaded options small. Large payloads increase memory usage.
- Be explicit with defaults to avoid type ambiguity. If you expect a boolean, pass a boolean default.
- Do not rely on implicit casting. If you need a specific type, cast after reading.

### Advanced

- To integrate with your own object cache policies, you can adjust TTLs:

```php
add_filter( 'uap_options_cache_ttl', function( $ttl ) { return 900; } );
add_filter( 'uap_options_cache_miss_ttl', function( $ttl ) { return 120; } );
```

- Cache key schema (for debugging):
  - Group `uap_options`.
  - Value keys `uap_opts_option_{$option_name}`.
  - Miss keys `uap_opts_miss_{$option_name}`.

### Notes

- `uap_options` takes precedence over `wp_options` when both contain the same key.
- `add_option()` has an expected race condition window (existence check → insert). The UNIQUE constraint on `option_name` prevents duplicates, and a false return is acceptable in concurrent scenarios.


