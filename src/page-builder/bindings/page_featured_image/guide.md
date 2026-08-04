# page_featured_image — Page Featured Image

Render the featured image of the current page/post.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<img data-ai-dynamic="page_featured_image" src="#" class="your-class" alt="Featured image">

<!-- Renders as: -->
<img data-ai-dynamic="page_featured_image" src="https://example.com/resolved" class="your-class" alt="Featured image">
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `page_featured_image` | Marks this as a page_featured_image region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<img data-ai-dynamic="page_featured_image" src="" alt="Placeholder" />
```

## Notes

- Uses `get_the_post_thumbnail_url(..., 'large')`.
- Returns the `large` image URL.
