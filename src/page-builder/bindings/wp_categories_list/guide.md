# wp_categories_list — WordPress Categories List

Renders a list of categories, optionally with post counts and hierarchical nesting. This is a self-rendering binding — you do NOT use `data-ai-bind` keys.

## Rendered HTML

The region's children are replaced with the markup below (captured from a real render). Classes shown here are the stable CSS hooks:

```html
<ul class="upb-categories-list">
	<li class="cat-item cat-item-151"><a href="https://example.com/category/artificial-intelligence/">Artificial Intelligence</a>
<ul class="children">
	<li class="cat-item cat-item-152"><a href="https://example.com/category/artificial-intelligence/ai-agents/">AI Agents</a>
</li>
</ul>
</li>
	<li class="cat-item cat-item-212"><a href="https://example.com/category/business/">Business</a>
</li>
	<!-- …repeats… -->
</ul>
```

## Required Attributes

| Attribute | Value | Description |
|-----------|-------|-------------|
| `data-ai-dynamic` | `wp_categories_list` | Marks this as a categories list region |
| `data-show-count` | `true`, `false` | Show post count next to each category (optional, defaults to `false`) |
| `data-hierarchical` | `true`, `false` | Show categories in a nested hierarchy (optional, defaults to `true`) |

## Bind Keys

**None.** `wp_categories_list` is self-rendering. Do not use `data-ai-bind` inside this region.

## Example

```html
<aside class="categories-widget py-4">
  <h4>Categories</h4>
  <div data-ai-dynamic="wp_categories_list"
       data-show-count="true"
       data-hierarchical="true">
    <!-- Renderer replaces this with a nested <ul> of category links -->
  </div>
</aside>
```

## Notes

- Renders as a `<ul>` list with `<li>` items, each containing an `<a>` link to the category archive
- When `data-hierarchical="true"`, child categories are nested inside parent `<li>` elements
- When `data-show-count="true"`, each item shows the number of posts in parentheses
