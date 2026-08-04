# post_author_url — Post Author URL

Author archive page URL for the current post.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="post_author_url" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="post_author_url" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_author_url` | Marks this as a post_author_url region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<a data-ai-dynamic="post_author_url" href="#">View Author Posts</a>
```

## Notes

- Sets the `href` attribute on `<a>` tags automatically. Uses get_author_posts_url(). Must be used within post context.
