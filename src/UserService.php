<?php /** @noinspection PhpUnused */

namespace Bayfront\BonesService\User;

use Bayfront\ArrayHelpers\Arr;
use Bayfront\Bones\Abstracts\Service;
use Bayfront\Bones\Application\Services\Events\EventService;
use Bayfront\Bones\Application\Services\Filters\FilterService;
use Bayfront\Bones\Application\Utilities\App;
use Bayfront\Bones\Exceptions\ServiceException;
use Bayfront\BonesService\User\Events\UserServiceEvents;
use Bayfront\BonesService\User\Exceptions\UserServiceException;
use Bayfront\BonesService\User\Filters\UserServiceFilters;
use Bayfront\Cookies\Cookie;
use Bayfront\Encryptor\DecryptException;
use Bayfront\Encryptor\EncryptException;
use Bayfront\Encryptor\Encryptor;
use Bayfront\Encryptor\InvalidCipherException;
use Bayfront\SessionManager\Session;

class UserService extends Service
{

    public EventService $events;
    public FilterService $filters;
    public Session $session;
    public array $config;

    /**
     * @param EventService $events
     * @param FilterService $filters
     * @param Session $session
     * @param array $config
     * @throws UserServiceException
     */
    public function __construct(EventService $events, FilterService $filters, Session $session, array $config)
    {

        $this->events = $events;
        $this->filters = $filters;
        $this->session = $session;
        $this->config = $config;
        parent::__construct($events);

        // Enqueue events

        try {
            $events->addSubscriptions(new UserServiceEvents($this));
        } catch (ServiceException $e) {
            throw new UserServiceException('Unable to start UserService: ' . $e->getMessage(), $e->getCode(), $e->getPrevious());
        }

        // Enqueue filters

        try {
            $this->filters->addSubscriptions(new UserServiceFilters($this));
        } catch (ServiceException $e) {
            throw new UserServiceException('Unable to start UserService: ' . $e->getMessage(), $e->getCode(), $e->getPrevious());
        }

    }

    /**
     * Get user configuration value in dot notation.
     *
     * @param string $key (Key to return in dot notation)
     * @param mixed $default (Default value to return if not existing)
     * @return mixed
     */
    public function getConfig(string $key = '', mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * Login user.
     *
     * - Set user data to session
     * - Create refresh cookie if refresh token exists
     * - Do user.login event
     *
     * @param string $user_id
     * @param string $access_token
     * @param int $expiration (Expiration timestamp. 0 to not expire)
     * @param string $refresh_token
     * @param bool $remember (If true, refresh cookie will be saved)
     * @return void
     * @throws UserServiceException
     */
    public function login(string $user_id, string $access_token = '', int $expiration = 0, string $refresh_token = '', bool $remember = false): void
    {

        $this->session->set('user', [
            'id' => $user_id,
            'access_token' => [
                'value' => $access_token,
                'expiration' => $expiration
            ],
            'refresh_token' => $refresh_token
        ]);

        if ($refresh_token !== '' && $remember === true) {

            if ($this->getConfig('refresh_cookie.encrypt', true) === true) {

                try {
                    $encryptor = new Encryptor(App::getEnv('APP_KEY', ''));
                    $refresh_token = $encryptor->encryptString($refresh_token);
                } catch (InvalidCipherException|EncryptException $e) {
                    throw new UserServiceException('Unable to encrypt refresh token: ' . $e->getMessage());
                }

            }

            Cookie::set($this->getConfig('refresh_cookie.name', 'user_refresh'), $refresh_token, $this->getConfig('refresh_cookie.duration', 10080), $this->getConfig('refresh_cookie.path', '/'), $this->getConfig('refresh_cookie.domain', ''));

        }

        $this->events->doEvent('user.login', $user_id, $expiration);

    }

    /**
     * Logout user.
     *
     * - Destroy user session
     * - Remove refresh cookie, if existing
     * - Do user.logout event, if required
     *
     * @param bool $do_event (Do user.logout event?)
     * @return void
     */
    public function logout(bool $do_event = true): void
    {
        $id = $this->getId();
        $this->session->startNew();
        Cookie::forget($this->getConfig('refresh_cookie.name', 'user_refresh'), $this->getConfig('refresh_cookie.path', '/'));

        if ($do_event === true) {
            $this->events->doEvent('user.logout', $id);
        }

    }

    /**
     * Is user session valid?
     *
     * Checks for user ID and expiration.
     *
     * @return bool
     */
    private function sessionIsValid(): bool
    {

        $expiration = $this->getAccessTokenExpiration();

        if (($expiration > 0 && $expiration <= time())
            || $this->getId() === null) {
            return false;
        }

        return true;

    }

    /**
     * Is user logged in?
     *
     * Checks for user ID and expiration.
     *
     * If not, will trigger the user.refresh event once and reattempt.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {

        if ($this->sessionIsValid()) {
            return true;
        }

        // Refresh once if needed

        $this->refresh();

        return $this->sessionIsValid();

    }

    /**
     * Trigger the user.refresh event if a refresh cookie is found
     * in the current session or in a refresh cookie.
     *
     * @return void
     */
    public function refresh(): void
    {

        // Get refresh token from session, if existing

        $refresh_token = $this->session->get('user.refresh_token', '');

        if ($refresh_token !== '') {
            $this->events->doEvent('user.refresh', $refresh_token, false);
            return;
        }

        // Get refresh token from cookie, if existing

        $refresh_token = Cookie::get($this->getConfig('refresh_cookie.name', 'user_refresh'));

        if (!is_string($refresh_token)) {
            return;
        }

        // Decrypt refresh token, if needed

        if ($this->getConfig('refresh_cookie.encrypt', true) === true) {

            try {
                $encryptor = new Encryptor(App::getEnv('APP_KEY', ''));
                $refresh_token = $encryptor->decryptString($refresh_token);
            } catch (InvalidCipherException|DecryptException) {

                /*
                 * May wish to throw UserServiceException, as is done in the login method.
                 * This fails silently so as not to throw an exception when checking if logged in.
                 */

                return;

            }

        }

        $this->events->doEvent('user.refresh', $refresh_token, true);

    }

    /**
     * Get user ID, if defined.
     *
     * @return string|null
     */
    public function getId(): ?string
    {
        return $this->session->get('user.id');
    }

    /**
     * Get access token value.
     *
     * @return string
     */
    public function getAccessTokenValue(): string
    {
        return $this->session->get('user.access_token.value', '');
    }

    /**
     * Get access token expiration.
     *
     * @return int
     */
    public function getAccessTokenExpiration(): int
    {
        return $this->session->get('user.access_token.expiration', 0);
    }

    /**
     * Set user permissions.
     *
     * @param array $permissions
     * @return void
     */
    public function setPermissions(array $permissions): void
    {
        if ($this->isLoggedIn()) {
            $this->session->set('user.permissions', $permissions);
        }
    }

    /**
     * Get user permissions.
     *
     * @return array
     */
    public function getPermissions(): array
    {
        return $this->session->get('user.permissions', []);
    }

    /**
     * Does user have permission?
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getPermissions());
    }

    /**
     * Does user have any permissions?
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAnyPermissions(array $permissions): bool
    {
        return Arr::hasAnyValues($this->getPermissions(), $permissions);
    }

    /**
     * Does user have all permissions?
     *
     * @param array $permissions
     * @return bool
     */
    public function hasAllPermissions(array $permissions): bool
    {
        return Arr::hasAllValues($this->getPermissions(), $permissions);
    }

    /**
     * Set a value for user session key in dot notation if logged in.
     *
     * @param string $key (Key to set in dot notation)
     * @param mixed $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        if ($this->isLoggedIn()) {
            $this->session->set('user.set.' . $key, $value);
        }
    }

    /**
     * Does user session key exist?
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->session->has('user.set.' . $key);
    }

    /**
     * Get value of user session key in dot notation, or default value if not existing.
     *
     * @param string $key (Key to return in dot notation)
     * @param mixed $default (Default value to return if not existing)
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session->get('user.set.' . $key, $default);
    }

    /**
     * Forget user session key in dot notation.
     *
     * @param string $key (Key to forget in dot notation)
     * @return void
     */
    public function forget(string $key): void
    {
        $this->session->forget('user.set.' . $key);
    }

}