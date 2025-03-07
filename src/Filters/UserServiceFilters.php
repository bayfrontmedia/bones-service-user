<?php

namespace Bayfront\BonesService\User\Filters;

use Bayfront\Bones\Abstracts\FilterSubscriber;
use Bayfront\Bones\Application\Services\Filters\FilterSubscription;
use Bayfront\Bones\Interfaces\FilterSubscriberInterface;
use Bayfront\BonesService\User\UserService;

class UserServiceFilters extends FilterSubscriber implements FilterSubscriberInterface
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
            new FilterSubscription('webapp.response.body', [$this, 'addTemplateTags'], 10)
        ];
    }

    /**
     * Add support for template tags:
     * - @candoany
     * - @candoall
     * - @can
     *
     * @param string $body
     * @return string
     */
    public function addTemplateTags(string $body): string
    {

        // @candoany

        preg_match_all("/@candoany:(.*?)@endcandoany/s", $body, $tags);

        if (isset($tags[0]) && is_array($tags[0])) { // If a tag was found

            foreach ($tags[0] as $tag) { // $tag = Entire block

                $use = explode(':', $tag, 2);

                if (isset($use[1])) { // If valid @can syntax

                    $can = explode(PHP_EOL, $use[1], 2);

                    if (isset($can[1])) {

                        if ($this->userService->hasAnyPermissions(explode('|', $can[0]))) {
                            $body = str_replace($tag, str_replace('@endcandoany', '', $can[1]), $body);
                        } else {
                            $body = str_replace($tag, '', $body);
                        }

                    }

                }

            }

        }

        // @candoall

        preg_match_all("/@candoall:(.*?)@endcandoall/s", $body, $tags);

        if (isset($tags[0]) && is_array($tags[0])) { // If a tag was found

            foreach ($tags[0] as $tag) { // $tag = Entire block

                $use = explode(':', $tag, 2);

                if (isset($use[1])) { // If valid @can syntax

                    $can = explode(PHP_EOL, $use[1], 2);

                    if (isset($can[1])) {

                        if ($this->userService->hasAllPermissions(explode('|', $can[0]))) {
                            $body = str_replace($tag, str_replace('@endcandoall', '', $can[1]), $body);
                        } else {
                            $body = str_replace($tag, '', $body);
                        }

                    }

                }

            }

        }

        // @can

        preg_match_all("/@can:(.*?)@endcan/s", $body, $tags);

        if (isset($tags[0]) && is_array($tags[0])) { // If a tag was found

            foreach ($tags[0] as $tag) { // $tag = Entire block

                $use = explode(':', $tag, 2);

                if (isset($use[1])) { // If valid @can syntax

                    $can = explode(PHP_EOL, $use[1], 2);

                    if (isset($can[1])) {

                        if ($this->userService->hasPermission($can[0])) {
                            $body = str_replace($tag, str_replace('@endcan', '', $can[1]), $body);
                        } else {
                            $body = str_replace($tag, '', $body);
                        }

                    }

                }

            }

        }

        return $body;

    }

}