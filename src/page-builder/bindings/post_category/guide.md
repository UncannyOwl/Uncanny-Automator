# post_category — Post Category

Primary category name for the current post.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored.

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:post_category -->): -->
<span data-ai-dynamic="post_category" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="post_category" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_category` | Marks this as a post_category region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="post_category">Category</span>
```

## Notes

- Returns the first assigned category using `get_the_category()`.
- Standard posts with no assigned category fall back to `Uncategorized`.
- Pages and post types without category support render empty.
