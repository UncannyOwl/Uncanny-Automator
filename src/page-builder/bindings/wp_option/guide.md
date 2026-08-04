# wp_option — WordPress Option

Renders a value from a small allowlisted subset of `wp_options`.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored.

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:wp_option -->): -->
<span data-ai-dynamic="wp_option" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="wp_option" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Required | Description |
|-----------|----------|-------------|
| `data-ai-dynamic` | yes | Set to `wp_option` |
| `data-key` | yes | The option name (e.g. `blogdescription`, `date_format`) |

## Example

```html
<span data-ai-dynamic="wp_option" data-key="blogdescription"></span>
<span data-ai-dynamic="wp_option" data-key="date_format"></span>
```

## Usage Notes

- Returns scalar option values only. Serialized arrays are not rendered.
- If the option is not allowlisted or does not exist, the element renders empty.
- The runtime uses a hard allowlist, not a heuristic blocklist.
- Default public keys are: `blogname`, `blogdescription`, `start_of_week`, `timezone_string`, `date_format`, `time_format`, `posts_per_page`, and `show_on_front`.
- Developers can extend the allowlist with the `uncanny_page_builder_public_option_keys` filter, but that is a site-owned security decision.
- Export safety is `public_request_safe` because only allowlisted public scalar options may render.
