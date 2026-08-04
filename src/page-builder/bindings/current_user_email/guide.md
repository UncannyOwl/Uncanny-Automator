# current_user_email — Current User Email

Render the logged-in user's email address.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:current_user_email -->): -->
<span data-ai-dynamic="current_user_email" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="current_user_email" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `current_user_email` | Marks this as a current_user_email region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="current_user_email">Placeholder</span>
```

## Notes

- Uses wp_get_current_user()->user_email. Returns empty if not logged in.
