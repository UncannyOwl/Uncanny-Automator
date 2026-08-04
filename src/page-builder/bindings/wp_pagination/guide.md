# wp_pagination — Archive Pagination

Renders pagination links for archive pages (blog index, category archives, search results, etc.).

## Rendered HTML

This binding renders archive pagination markup from WordPress `paginate_links()` and wraps
it in a navigation element. When no pagination links are available, it renders
empty output.

```html
<nav class="upb-pagination" aria-label="Page navigation">
  ... output from paginate_links() ...
</nav>
```

(Output captured from renderer source, not execution.)

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_pagination` | Marks this element for pagination output |

## Example

```html
<nav data-ai-dynamic="wp_pagination"></nav>
```

## Usage Notes

- Only works on archive/index views where WordPress has multiple pages of results.
- On single posts or when there is only one page, the element renders empty.
- Outputs numbered page links with previous/next navigation.
- Pair with a `wp_query` binding that has `data-paginate="true"` for paginated post grids.
- Style the pagination links with CSS as needed.
