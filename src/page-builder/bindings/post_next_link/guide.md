# post_next_link — Next Post Link

URL to the next post in chronological order.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="post_next_link" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="post_next_link" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_next_link` | Marks this as a post_next_link region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<a data-ai-dynamic="post_next_link" href="#">Next Post</a>
```

## Notes

- Sets the `href` attribute on `<a>` tags automatically.
- Uses the next adjacent post permalink.
- If no next post exists, the URL value is empty. On `<a>` hosts, Page Builder clears `href` and preserves the visible link label.
