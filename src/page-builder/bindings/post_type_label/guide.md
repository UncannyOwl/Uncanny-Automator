# post_type_label — Post Type Label

Render the human-readable label of the current post type.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:post_type_label -->): -->
<span data-ai-dynamic="post_type_label" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="post_type_label" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_type_label` | Marks this as a post_type_label region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="post_type_label">Placeholder</span>
```

## Notes

- Uses get_post_type_object()->labels->singular_name. Returns "Post", "Page", or custom post type label.
