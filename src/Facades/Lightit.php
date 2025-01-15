<?php

namespace Lightit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Lightit
 */
class Lightit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Lightit::class;
    }
}
