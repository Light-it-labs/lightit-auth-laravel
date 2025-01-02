<?php

namespace Lightit\Lightit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Lightit\Lightit\Lightit
 */
class Lightit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Lightit\Lightit\Lightit::class;
    }
}
