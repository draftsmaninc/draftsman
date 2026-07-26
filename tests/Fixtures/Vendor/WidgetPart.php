<?php

namespace VendorLib;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Second hop of the vendor chain — reachable only through Widget. */
class WidgetPart extends Model
{
    public function widget(): BelongsTo
    {
        return $this->belongsTo(Widget::class);
    }
}
