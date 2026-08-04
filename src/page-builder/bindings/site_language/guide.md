# site_language — Site Language

Render the site language code (e.g. en-US).

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:site_language -->): -->
<span data-ai-dynamic="site_language" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="site_language" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `site_language` | Marks this as a site_language region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="site_language">Placeholder</span>
```

## Notes

- Uses get_bloginfo('language'). Useful for lang attributes and locale-aware rendering.
