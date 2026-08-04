# share_email — Share via Email

mailto: share link for the current page.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="share_email" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="share_email" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `share_email` | Marks this as a share_email region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<a data-ai-dynamic="share_email" href="#">Share via Email</a>
```

## Notes

- Sets the `href` attribute on `<a>` tags automatically.
- Uses the current page or post title for `subject` and the current permalink for `body`.
- If no current post/page context exists, it falls back to the site name and home URL.
