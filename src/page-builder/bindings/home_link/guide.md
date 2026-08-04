# home_link — Home Link

Render a linked site name pointing to the home page.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<a href="https://example.com/">Site Name</a>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `home_link` | Marks this as a home_link region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="home_link">Placeholder</span>
```

## Notes

- Renders an anchor tag wrapping the site name linked to home_url(). Ideal for logo-text or breadcrumb home links.
