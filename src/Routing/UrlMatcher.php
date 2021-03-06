<?php

namespace Bolt\Routing;

use Silex\Provider\Routing\RedirectableUrlMatcher;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

/**
 * If Silex\RedirectableUrlMatcher does not match a route,
 * it checks for a route with a trailing slash and redirects to it.
 *
 * This additionally checks for a route without a trailing slash and redirects
 * to it.
 */
class UrlMatcher extends RedirectableUrlMatcher
{
    public function match($pathinfo)
    {
        try {
            return parent::match($pathinfo);
        } catch (ResourceNotFoundException $e) {
            if ('/' !== substr($pathinfo, -1)) {
                throw $e;
            }

            // Try matching the route without trailing slash
            $withoutTrailingSlash = substr($pathinfo, 0, -1);
            try {
                parent::match($withoutTrailingSlash);

                return $this->redirect($withoutTrailingSlash, null);
            } catch (ResourceNotFoundException $e2) {
                // We don't care about the new exception as we are just trying
                // to match different versions of the route, if it fails then we
                // throw the original one
                throw $e;
            }
        }
    }
}
