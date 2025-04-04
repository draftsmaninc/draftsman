<?php

namespace DraftsmanInc\Draftsman\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \DraftsmanInc\Draftsman\Draftsman
 */
class Draftsman extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \DraftsmanInc\Draftsman\Draftsman::class;
    }
}
