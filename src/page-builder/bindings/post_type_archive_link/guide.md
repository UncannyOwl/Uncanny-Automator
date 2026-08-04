# post_type_archive_link — Post Type Archive Link

Render the archive URL for a specific post type.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="post_type_archive_link" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="post_type_archive_link" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_type_archive_link` | Marks this as a post_type_archive_link region |
| `data-post-type` | `post` | The post type slug (optional, defaults to "post") |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<a data-ai-dynamic="post_type_archive_link" data-post-type="post" href="">Post archive</a>
```

## Notes

- Uses `get_post_type_archive_link()`.
- On `<a>` hosts, Page Builder writes the final `href` attribute automatically.
- Use a text host like `<span>` only when you want the visible archive URL text.
