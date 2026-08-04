# total_users_count — Total Users Count

Total number of registered users on the site.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:total_users_count -->): -->
<span data-ai-dynamic="total_users_count" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="total_users_count" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `total_users_count` | Marks this as a total_users_count region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="total_users_count">0</span>
```

## Notes

- Uses count_users() to return total registered users. Useful for membership sites, community stats, or social proof sections.
