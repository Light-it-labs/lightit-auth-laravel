<?php

namespace Lightitlabs\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Lightit
 */
class Lightit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Lightitlabs\Lightit::class;
    }
}
