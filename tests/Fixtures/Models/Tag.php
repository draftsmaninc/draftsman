<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    protected $table = 'tags';

    public function teams(): MorphToMany
    {
        return $this->morphedByMany(Team::class, 'taggable');
    }

    /** ->using(MorphPivot::class) mirrors spatie/laravel-tags — the base
     *  morph pivot named explicitly, no custom pivot model. */
    public function relatedTags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->using(MorphPivot::class);
    }
}
