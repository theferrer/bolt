<?php

namespace Bolt\Provider;

use Bolt\Collection\Bag;
use Bolt\Storage\Database\Prefill;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class PrefillServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['prefill.api_url'] = 'http://loripsum.net/api/';

        $app['prefill'] = function ($app) {
            return new Prefill\ApiClient($app['guzzle.client']);
        };

        $app['prefill.builder'] = function ($app) {
            return new Prefill\Builder(
                $app['storage'],
                $app['prefill.generator_factory'],
                5,
                Bag::from($app['config']->get('contenttypes'))
            );
        };

        $app['prefill.default_field_values'] = function () {
            return new Bag([
                'blocks' => [
                    'title' => 'About Us', 'Address', 'Search Teaser', '404 Not Found',
                ],
            ]);
        };

        $app['prefill.generator_factory'] = $app->protect(
            function ($contentTypeName) use ($app) {
                return new Prefill\RecordContentGenerator(
                    $contentTypeName,
                    $app['prefill'],
                    $app['storage']->getRepository($contentTypeName),
                    $app['filesystem']->getFilesystem('files'),
                    $app['config']->get('taxonomy'),
                    $app['prefill.default_field_values']
                );
            }
        );
    }
}
