<?php

namespace Bayfront\BonesService\User\Events;

use Bayfront\Bones\Abstracts\EventSubscriber;
use Bayfront\Bones\Application\Services\Events\EventSubscription;
use Bayfront\Bones\Interfaces\EventSubscriberInterface;
use Bayfront\BonesService\User\UserService;

class UserServiceEvents extends EventSubscriber implements EventSubscriberInterface
{

    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @inheritDoc
     */
    public function getSubscriptions(): array
    {
        return [
            new EventSubscription('app.http', [$this, 'startSession'], 1),
        ];
    }

    /**
     * Start session.
     *
     * @return void
     */
    public function startSession(): void
    {
        $this->userService->session->start();
    }

}