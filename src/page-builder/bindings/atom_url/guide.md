# atom_url — Atom Feed URL

Render the site Atom feed URL.

## Rendered HTML

This binding renders **no markup at all** — it writes a URL onto the host
element's attribute (`href` on `<a>`, `src` on `<img>`). Children of the
host element are your authored content and are untouched:

```html
<!-- You author: -->
<a data-ai-dynamic="atom_url" href="#" class="your-class">Your link text</a>

<!-- Renders as: -->
<a data-ai-dynamic="atom_url" href="https://example.com/resolved" class="your-class">Your link text</a>
```

Style the host element and its children — there are no binding-generated
elements or classes.

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `atom_url` | Marks this as a atom_url region |

## Bind Keys

**None.** Self-rendering binding.

## Example

```html
<span data-ai-dynamic="atom_url">Placeholder</span>
```

## Notes

- Uses get_bloginfo('atom_url'). Outputs a plain URL string suitable for href attributes.
