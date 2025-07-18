# [User service](README.md) > UserService class

The `UserService` class contains the following:

- [EventService](https://github.com/bayfrontmedia/bones/blob/master/docs/services/events.md) as `$this->events`
- [FilterService](https://github.com/bayfrontmedia/bones/blob/master/docs/services/filters.md) as `$this->filters`
- [Session](https://github.com/bayfrontmedia/session-manager) as `$this->session`

The [events](events.md) and [filters](filters.md) are added in its constructor.

Methods include:

- [getConfig](#getconfig)
- [login](#login)
- [logout](#logout)
- [isLoggedIn](#isloggedin)
- [refresh](#refresh)
- [getId](#getid)
- [getAccessTokenValue](#getaccesstokenvalue)
- [getAccessTokenExpiration](#getaccesstokenexpiration)
- [setPermissions](#setpermissions)
- [getPermissions](#getpermissions)
- [hasPermission](#haspermission)
- [hasAnyPermissions](#hasanypermissions)
- [hasAllPermissions](#hasallpermissions)
- [set](#set)
- [has](#has)
- [get](#get)
- [forget](#forget)

## getConfig

**Description**

Get user configuration value in dot notation.

**Parameters**

- `$key = ''` (string): Key to return in dot notation
- `$default = null` (mixed): Default value to return if not existing

**Returns**

- (mixed)

## login

**Description**

Login user.

- Set user data to session
- Create refresh cookie if refresh token exists
- Do `user.login` event

**Parameters**

- `$user_id` (string)
- `$access_token = ''` (string)
- `$expiration = 0` (int)
- `$refresh_token = ''` (string)

**Returns**

- (void)

**Throws**

- `\Bayfront\BonesService\User\Exceptions\UserServiceException`

## logout

**Description**

Logout user.

- Destroy user session
- Remove refresh cookie, if existing
- Do `user.logout` event, if required

**Parameters**

- `$do_event = true` (bool)

**Returns**

- (void)

## isLoggedIn

**Description**

Is user logged in?

Checks for user ID and expiration.

If not, will trigger the `user.refresh` event once and reattempt.

**Parameters**

- (none)

**Returns**

- (bool)

## refresh

**Description**

Trigger the `user.refresh` event if a refresh cookie is found.

**Parameters**

- (none)

**Returns**

- (void)

## getId

**Description**

Get user ID, if defined.

**Parameters**

- (none)

**Returns**

- (string|null)

## getAccessTokenValue

**Description**

Get access token value.

**Parameters**

- (none)

**Returns**

- (string)

## getAccessTokenExpiration

**Description**

Get access token expiration.

**Parameters**

- (none)

**Returns**

- (int)

## setPermissions

**Description**

Set user permissions.

**Parameters**

- `$permissions` (array)

**Returns**

- (void)

## getPermissions

**Description**

Get user permissions

**Parameters**

- (none)

**Returns**

- (array)

## hasPermission

**Description**

Does user have permission?

**Parameters**

- `$permission` (string)

**Returns**

- (bool)

## hasAnyPermissions

**Description**

Does user have any permissions?

**Parameters**

- `$permissions` (array)

**Returns**

- (bool)
- 
## hasAllPermissions

**Description**

Does user have all permissions?

**Parameters**

- `$permissions` (array)

**Returns**

- (bool)

## set

**Description**

Set a value for user session key in dot notation if logged in.

**Parameters**

- `$key` (string): Key to set in dot notation
- `$value` (mixed)

**Returns**

- (void)

## has

**Description**

Does user session key exist?

**Parameters**

- `$key` (string)

**Returns**

- (bool)

## get

**Description**

Get value of user session key in dot notation, or default value if not existing.

**Parameters**

- `$key` (string): Key to return in dot notation
- `$default = null` (mixed): Default value to return if not existing

**Returns**

- (mixed)

## forget

**Description**

Forget user session key in dot notation.

**Parameters**

- `$key` (string): Key to forget in dot notation

**Returns**

- (void)