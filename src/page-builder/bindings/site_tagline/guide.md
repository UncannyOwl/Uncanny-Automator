# site_tagline — Site Tagline

Render the WordPress site tagline/description.

## Rendered HTML

This binding renders **plain text only** — no elements are added. The text
replaces the region element's children, so CSS targets the element you
authored:

```html
<!-- You author (or the mask form <!-- upb:bindings:dynamic_data:site_tagline -->): -->
<span data-ai-dynamic="site_tagline" class="your-class"></span>

<!-- Renders as: -->
<span data-ai-dynamic="site_tagline" class="your-class">The resolved text value</span>
```

Style it by styling your own element (`.your-class`). There are no
binding-generated classes to target.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `site_tagline` | Marks this as a site_tagline region |

## Bind Keys

**None.** This is a self-rendering binding. The content is replaced automatically.

## Example

```html
<span data-ai-dynamic="site_tagline">Placeholder</span>
```

## Notes

- Uses get_bloginfo('description'). Commonly placed below the site title or in meta descriptions.
