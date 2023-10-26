<?php

declare(strict_types=1);

namespace Verstka\Builder;

use Verstka\Verstka;

interface VerstkaBuilderInterface
{
    const API_HOST = 'https://verstka.org';

    /**
     * @return Verstka
     */
    public function build(): Verstka;
}