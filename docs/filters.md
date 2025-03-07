# [User service](README.md) > Filters

This service subscribes to the following [filters](https://github.com/bayfrontmedia/bones/blob/master/docs/services/filters.md):

- `webapp.response.body`: Adds support of `@candoany`, `@candoall` and `@can` template tags.

The above-mentioned template tags allow content to be included in template files based on user permissions.
The `@candoany` and `@candoall` tags allow for multiple permissions to be listed, separated by a pipe (`|`).

For example:

```html
<p>Content always visible</p>

@candoany:users:read|users:update
<p>Content only visible if user has users:read or users:update permission.</p>
@endcandoany

@candoall:users:read|users:update
<p>Content only visible if user has users:read and users:update permissions.</p>
@endcandoall

@can:users:read
<p>Content only available if user has users:read permissions.</p>
@endcan
```