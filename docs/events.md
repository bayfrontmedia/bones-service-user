# [User service](README.md) > Events

The following [events](https://github.com/bayfrontmedia/bones/blob/master/docs/services/events.md) are added by this service:

- `user.login`: Executes when a user is logged in using the [login](userservice-class.md#login) method.
The user ID is passed as a parameter.
- `user.logout`: Executes when a user is logged out using the [logout](userservice-class.md#logout) method.
The user ID is passed as a parameter.
- `user.refresh`: Executes when a user session is successfully refreshed using the [refresh](userservice-class.md#refresh) method.
The refresh token, and whether to "remember" the session (boolean) are passed as parameters.

## Event subscriptions

This service subscribes to the following events:

- `app.http`: The user session is started.