# wp_breadcrumbs — WordPress Breadcrumbs

Renders a breadcrumb navigation trail for the current page. This is a self-rendering binding — you do NOT use `data-ai-bind` keys. The renderer replaces the content of your element with a breadcrumb trail like: Home > Parent Page > Current Page.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<nav class="upb-breadcrumbs" aria-label="Breadcrumb"><a href="https://example.com/">Home</a> <span class="upb-breadcrumb-sep">&gt;</span> <span aria-current="page">Current Page Title</span></nav>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_breadcrumbs` | Marks this as a breadcrumbs region |
| `data-separator` | e.g. `/`, `>`, `&raquo;` | Character between breadcrumb items (optional, defaults to `/`) |

## Bind Keys

**None.** `wp_breadcrumbs` is self-rendering. Do not use `data-ai-bind` inside this region.

## Example

```html
<nav aria-label="breadcrumb" class="py-2 bg-light">
  <div class="container">
    <div data-ai-dynamic="wp_breadcrumbs" data-separator="/">
      <!-- Renderer replaces this with: Home / Parent / Current Page -->
    </div>
  </div>
</nav>
```

## Notes

- The breadcrumb trail is generated from the current page's position in the WordPress page hierarchy
- On the homepage, typically renders just "Home"
- Runtime currently builds breadcrumbs from the current page/post ancestor chain only.
- Each breadcrumb item except the last is a clickable link
