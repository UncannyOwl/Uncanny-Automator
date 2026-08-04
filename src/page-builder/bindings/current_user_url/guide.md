# current_user_url — Current User Posts URL

Render the URL to the logged-in user's author archive page.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="current_user_url" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="current_user_url" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `current_user_url` | Marks this as a current_user_url region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<a data-ai-dynamic="current_user_url" href="">Current user posts</a>
```

## Notes

- Uses `get_author_posts_url()`.
- On `<a>` hosts, Page Builder writes the final `href` attribute automatically.
- Use a text host like `<span>` only when you want the visible author archive URL text.
- If the visitor is logged out, the binding renders empty.
