<?php

namespace VendorLib;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stand-in for a package-provided model (Spatie Media, Cashier Subscription…):
 * a real Eloquent model whose file lives OUTSIDE app_path(), so the models
 * scan never finds it — it can only enter the payload via the vendor-target
 * tracking in getModels(). Chains onward to WidgetPart to prove the tracking
 * is transitive.
 */
class Widget extends Model
{
    public function parts(): HasMany
    {
        return $this->hasMany(WidgetPart::class);
    }

    /**
     * Mirrors spatie/laravel-medialibrary's Media::temporaryUploads(), which
     * throws FunctionalityNotAvailable unless the pro package is installed —
     * a relation METHOD that explodes when invoked. Introspection must skip
     * it, not die (found live on BHE, 2026-07-26).
     */
    public function proParts(): HasMany
    {
        throw new \RuntimeException('You need to have widgets pro installed to make this work.');
    }
}
