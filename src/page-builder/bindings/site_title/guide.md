# site_title — Site Title

Render the WordPress site name.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:site_title -->): -->
<span data-ai-dynamic="site_title" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="site_title" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `site_title` | Marks this as a site_title region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="site_title">Placeholder</span>
```

## Notes

- Uses get_bloginfo('name'). Safe for use in headers, footers, and meta tags.
