<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Note extends Model
{
    protected $table = 'notes';

    public function notable(): MorphTo
    {
        return $this->morphTo();
    }
}
