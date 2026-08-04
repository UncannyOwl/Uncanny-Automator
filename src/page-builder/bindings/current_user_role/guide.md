# current_user_role — Current User Role

Render the logged-in user's primary role.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:current_user_role -->): -->
<span data-ai-dynamic="current_user_role" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="current_user_role" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `current_user_role` | Marks this as a current_user_role region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="current_user_role">Placeholder</span>
```

## Notes

- Uses the first role from wp_get_current_user()->roles. Returns empty if not logged in.
