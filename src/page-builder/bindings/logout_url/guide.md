# logout_url — Logout URL

Render the WordPress logout URL with redirect to home.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="logout_url" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="logout_url" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `logout_url` | Marks this as a logout_url region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<a data-ai-dynamic="logout_url" href="/wp-login.php?action=logout">Logout</a>
```

## Notes

- Uses wp_logout_url(). Include in user account menus for authenticated visitors.
