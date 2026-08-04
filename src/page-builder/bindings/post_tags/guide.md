# post_tags — Post Tags

Comma-separated tag list for the current post.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:post_tags -->): -->
<span data-ai-dynamic="post_tags" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="post_tags" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `post_tags` | Marks this as a post_tags region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="post_tags">tag1, tag2</span>
```

## Notes

- Returns all tags as a comma-separated string using get_the_tag_list(). Returns empty string if no tags are assigned.
