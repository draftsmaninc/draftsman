<?php

namespace Draftsman\Draftsman\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Draftsman\Draftsman\Draftsman
 */
class Draftsman extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Draftsman\Draftsman\Draftsman::class;
    }
}
