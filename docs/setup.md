# [User service](README.md) > Initial setup

- [Configuration](#configuration)
- [Add to container](#add-to-container)

## Configuration

This service requires a configuration array. Typically, this would be placed at `config/user.php`.

**Example:**

```php
return [
    'refresh_cookie' => [
        'name' => 'user_refresh',
        'duration' => 10080, // In minutes (10080 = 7 days)
        'path' => '/',
        'domain' => '',
        'encrypt' => true
    ],
    'refresh_remaining' => 120 // In seconds
];
```

### Configuration summary

- `refresh_cookie.name`: The name of the cookie which will be used to save the refresh token
- `refresh_cookie.duration`: Validity duration of the refresh cookie, in minutes
- `refresh_cookie.path`: Path of the refresh cookie
- `refresh_cookie.domain`: Domain of the refresh cookie
- `refresh_cookie.encrypt`: Whether to encrypt the refresh token before saving it to the cookie (recommended)
- `refresh_remaining`: Maximum time remaining (in seconds) before refreshing the access token

## Add to container

With the configuration completed, the [UserService](userservice-class.md) class needs to be added to the Bones [service container](https://github.com/bayfrontmedia/bones/blob/master/docs/usage/container.md).
This is typically done in the `resources/bootstrap.php` file. You may also wish to create an alias.

For more information, see [Bones bootstrap documentation](https://github.com/bayfrontmedia/bones/blob/master/docs/usage/bootstrap.md).

The `UserService` requires the following in its constructor:

- [EventService](https://github.com/bayfrontmedia/bones/blob/master/docs/services/events.md)
- [FilterService](https://github.com/bayfrontmedia/bones/blob/master/docs/services/filters.md)
- [Session](https://github.com/bayfrontmedia/session-manager)
- Configuration array (see above)

```php
// Example Session configuration
$handler = new LocalHandler(App::storagePath('/app/sessions'));
$session = new Session($handler, [
    'cookie_name' => 'user_session'
]);

// Add User service to container
$userService = $container->make('Bayfront\BonesService\User\UserService', [
    'session' => $session,
    'config' => (array)App::getConfig('user', [])
]);

$container->set('Bayfront\BonesService\User\UserService', $userService);
$container->setAlias('userService', 'Bayfront\BonesService\User\UserService');
```