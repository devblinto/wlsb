<?php

declare(strict_types=1);

namespace Wlsb\Access\Delivery\Access;

use Wlsb\Access\Application\Access\AccessPolicy;
use WP_Error;

/**
 * Closes the REST read path for content protected by a required capability, so a
 * page hidden on the front end can't be fetched over /wp-json instead.
 */
final class RestGuard
{
    public function __construct(private readonly AccessPolicy $policy) {}

    public function register(): void
    {
        add_filter('rest_pre_dispatch', [$this, 'preDispatch'], 10, 3);
    }

    /**
     * @param mixed $result
     * @param mixed $server
     * @param mixed $request
     * @return mixed
     */
    public function preDispatch($result, $server, $request)
    {
        if ($result !== null || ! is_object($request) || ! method_exists($request, 'get_route')) {
            return $result;
        }

        if (preg_match('#^/wp/v2/(?:posts|pages)/(\d+)#', (string) $request->get_route(), $matches) !== 1) {
            return $result;
        }

        $requiredCap = (string) get_post_meta((int) $matches[1], FrontendGuard::CAP_META, true);

        if ($requiredCap !== '' && ! $this->policy->authorize($requiredCap)) {
            return new WP_Error(
                'wlsb_forbidden',
                __('You do not have permission to view this content.', 'wlsb-access'),
                ['status' => 403],
            );
        }

        return $result;
    }
}
