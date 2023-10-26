<?php

declare(strict_types=1);

namespace Verstka\Builder;

use Verstka\Verstka;

class VerstkaEnvBuilder implements VerstkaBuilderInterface
{

    /**
     * @return Verstka
     */
    public function build(): Verstka
    {
        assert(
            !empty(getenv('verstka_apikey'))
            && !empty(getenv('verstka_secret'))
            && !empty(getenv('verstka_host'))
            ,
            'Invalid Verstka configuration from ENV!'
        );

        $verstkaHost = getenv('verstka_host');
        if (!is_string($verstkaHost) || empty($verstkaHost)) {
            $verstkaHost = VerstkaBuilderInterface::API_HOST;
        }
        return new Verstka(
            getenv('verstka_apikey'),
            getenv('verstka_secret'),
            getenv('images_host') !== false ? getenv('images_host') : $_SERVER['HTTP_HOST'],
            $verstkaHost,
            getenv('verstka_debug') ?? false
        );
    }
}