# lost_password_url — Lost Password URL

Render the WordPress lost password page URL.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="lost_password_url" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="lost_password_url" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `lost_password_url` | Marks this as a lost_password_url region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="lost_password_url">Placeholder</span>
```

## Notes

- Uses wp_lostpassword_url(). Place alongside login forms for password recovery.
