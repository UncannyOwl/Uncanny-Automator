# current_user_avatar — Current User Avatar

Render the logged-in user's avatar URL.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<img data-ai-dynamic="current_user_avatar" src="#" class="your-class" alt="Profile image">

<!-- Renders as: -->
<img data-ai-dynamic="current_user_avatar" src="https://example.com/resolved" class="your-class" alt="Profile image">
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `current_user_avatar` | Marks this as a current_user_avatar region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<img data-ai-dynamic="current_user_avatar" src="" alt="Current user avatar" />
```

## Notes

- Uses the current user's avatar URL at `96px`.
- On `<img>` hosts, Page Builder writes the final `src` attribute automatically.
- If the visitor is not logged in, the binding renders empty.
