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
     * - Set user session
     * - Create refresh cookie, if required
     * - Do user.login event, if required
     *
     * @param string $user_id
     * @param string $access_token
     * @param int $expiration
     * @param string $refresh_token
     * @param bool $remember
     * @param bool $do_event (Do user.login event?)
     * @return void
     * @throws UserServiceException
     */
    public function login(string $user_id, string $access_token, int $expiration = 0, string $refresh_token = '', bool $remember = false, bool $do_event = true): void
    {

        $this->session->set('user', [
            'id' => $user_id,
            'access_token' => [
                'value' => $access_token,
                'expiration' => $expiration
            ]
        ]);

        if ($refresh_token !== '') {

            if ($this->getConfig('refresh_cookie.encrypt', true) === true) {

                try {
                    $encryptor = new Encryptor(App::getEnv('APP_KEY', ''));
                    $refresh_token = $encryptor->encryptString($refresh_token);
                } catch (InvalidCipherException|EncryptException $e) {
                    throw new UserServiceException('Unable to encrypt refresh token: ' . $e->getMessage());
                }

            }

            $this->session->set('user.refresh_token', $refresh_token);

            if ($remember === true) {
                Cookie::set($this->getConfig('refresh_cookie.name', 'user_refresh'), $refresh_token, $this->getConfig('refresh_cookie.duration', 10080), $this->getConfig('refresh_cookie.path', '/'), $this->getConfig('refresh_cookie.domain', ''));
            }

        }

        if ($do_event === true) {
            $this->events->doEvent('user.login', $user_id);
        }

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
     * Is user logged in?
     *
     * If not, will trigger the user.refresh event once and reattempt.
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {

        $expiration = $this->session->get('user.access_token.expiration');

        /*
         * Refresh if session does not exist or is nearing expiration
         */

        if ($expiration === null
            || ((int)$expiration > 0 && (int)$expiration <= time() - (int)$this->getConfig('refresh_remaining', 120))) {
            $this->refresh();
        }

        $expiration = $this->session->get('user.access_token.expiration');

        // Refresh if expired
        if ($expiration === null || ((int)$expiration > 0 && (int)$expiration <= time())) {
            return false;
        }

        // Check session

        return $this->session->has('user');

    }

    /**
     * Trigger the user.refresh event if a refresh cookie is found.
     *
     * @return void
     */
    public function refresh(): void
    {

        // Get refresh token, if existing

        $remember = true;
        $refresh_token = Cookie::get($this->getConfig('refresh_cookie.name', 'user_refresh'));

        if ($refresh_token === null) {
            $remember = false;
            $refresh_token = $this->session->get('user.refresh_token');
        }

        if (!is_string($refresh_token)) {
            return;
        }

        // Decrypt refresh token, if needed

        if ($remember === true && $this->getConfig('refresh_cookie.encrypt', true) === true) {

            try {
                $encryptor = new Encryptor(App::getEnv('APP_KEY', ''));
                $refresh_token = $encryptor->decryptString($refresh_token);
            } catch (InvalidCipherException|DecryptException) {

                /*
                 * May wish to throw UserServiceException, as is done in the login method
                 */

                return;

            }

        }

        $this->events->doEvent('user.refresh', $refresh_token, $remember);

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