# current_user_bio — Current User Bio

Render the logged-in user's biographical description.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:current_user_bio -->): -->
<span data-ai-dynamic="current_user_bio" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="current_user_bio" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `current_user_bio` | Marks this as a current_user_bio region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="current_user_bio">Placeholder</span>
```

## Notes

- Uses get_the_author_meta('description'). Returns the bio from the user profile.
