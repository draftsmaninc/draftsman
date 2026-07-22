<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    protected $table = 'tags';

    public function teams(): MorphToMany
    {
        return $this->morphedByMany(Team::class, 'taggable');
    }
}
