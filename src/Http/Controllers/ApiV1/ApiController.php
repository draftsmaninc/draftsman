<?php

namespace Draftsman\Draftsman\Http\Controllers\ApiV1;

use Draftsman\Draftsman\Actions\GetDraftsmanConfig;
use Draftsman\Draftsman\Actions\RenderGraphImage;
use Draftsman\Draftsman\Actions\UpdateDraftsmanConfig;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ApiController extends BaseController
{
    /*
    * Eloquent models:
    * BelongsTo
    * BelongsToMany
    * HasMany
    * HasManyThrough
    * HasOne
    * HasOneOrMany * model that can be used in place where both are needed
    * HasOneThrough
    * MorphMany
    * MorphOne
    * MorphOneOrMany * model that can be used in place where both are needed
    * MorphTo
    * MorphToMany
    * MorphPivot * custom many-to-many pivot models should extend
    * Pivot * custom polymorphic many-to-many pivot models should extend
    * https://laravel.com/docs/10.x/eloquent-relationships#defining-custom-intermediate-table-models
    */

    protected $relationsOmitList = [];

    protected $relationsRestrictToList = [];

    /**
     * `connection` names the topology of a relation — what, if anything, the
     * edge traverses between its endpoints — 1:1 with the metadata it carries:
     *   direct  — plain FK link, no intermediate
     *   pivot   — traverses a join table (a model only when ->using());
     *             carries pivot_class / pivot_from / pivot_to
     *   through — traverses an intermediate model; carries through_class /
     *             through_from / through_to
     * Renderers use this to spot shortcut edges that parallel a path through a
     * model already on the graph (contract-tested in ConnectionTest).
     */
    protected $relationsConnectionMap = [
        'BelongsTo' => 'direct',
        'BelongsToMany' => 'pivot',
        'HasMany' => 'direct',
        'HasManyThrough' => 'through',
        'HasOne' => 'direct',
        'HasOneThrough' => 'through',
        'MorphMany' => 'direct',
        'MorphOne' => 'direct',
        'MorphTo' => 'direct',
        'MorphToMany' => 'pivot',
    ];

    protected $relationsTypeMap = [
        'BelongsTo' => 'one',
        'BelongsToMany' => 'many',
        'HasMany' => 'many',
        'HasManyThrough' => 'many',
        'HasOne' => 'one',
        'HasOneThrough' => 'one',
        'MorphMany' => 'many',
        'MorphOne' => 'one',
        'MorphTo' => 'one',
        'MorphToMany' => 'many',
    ];

    /**
     * relationship_key is "<scope>:<endpoints>" — the two "Model.attribute"
     * endpoints joined by '.' in alphabetical order, so the key is
     * direction-indifferent and mirrored declarations emit the identical key.
     * The scope keeps semantically different categories of connection between
     * the same two endpoints distinct — a through shortcut (e.g. owned teams
     * via memberships) never dedups against the direct pair it parallels
     * (e.g. belongsToMany team membership). Unlisted types scope as 'direct'.
     * (MorphTo bypasses this map entirely: it emits targetless, keyed
     * 'morph:<its morph_key>' — see relationsMorphSkipDefintions.)
     * Deliberately independent of $relationsConnectionMap: connection drives
     * rendering, and the two DO diverge — many-to-many types report a 'pivot'
     * connection but keep the 'direct' key scope, so relationship keys (and
     * every saved graph built on them) never move.
     */
    protected $relationshipKeyScopeMap = [
        'BelongsTo' => 'direct',
        'BelongsToMany' => 'direct',
        'HasMany' => 'direct',
        'HasManyThrough' => 'through',
        'HasOne' => 'direct',
        'HasOneThrough' => 'through',
        'MorphMany' => 'direct',
        'MorphOne' => 'direct',
        'MorphTo' => 'direct',
        'MorphToMany' => 'direct',
    ];

    /**
     * The crow's-foot fields (https://www.red-gate.com/blog/crow-s-foot-notation/).
     *
     * multiplicity — the cardinality glyph at the FROM model's own end of the
     * edge: how many from-rows one related row can have. A BelongsTo's declarer
     * is the many side; a HasMany's declarer is the one side. Note this is the
     * INVERSE of the relation's return cardinality ($relationsTypeMap), so a
     * renderer can decorate both ends of an edge from one relation record:
     * from end via multiplicity, to end via type. (MorphTo emits targetless —
     * no edge — but carries the fields like any FK-holder record.)
     */
    protected $relationsMultiplicityMap = [
        'BelongsTo' => 'many',
        'BelongsToMany' => 'many',
        'HasMany' => 'one',
        'HasManyThrough' => 'one',
        'HasOne' => 'one',
        'HasOneThrough' => 'one',
        'MorphMany' => 'one',
        'MorphOne' => 'one',
        'MorphTo' => 'many',
        'MorphToMany' => 'many',
    ];

    /**
     * mandatory — whether the edge's "one" side is required. A string names the
     * relation field holding the FK column to check for nullability (possible
     * only when the FK lives on the DECLARING model: BelongsTo/MorphTo; other
     * types' FKs sit on the related model, which isn't loaded in this pass, so
     * they assume the conventional non-null FK). false for the *ToMany types,
     * which have no "one" side.
     */
    protected $relationsMandatoryMap = [
        'BelongsTo' => 'from_attribute',
        'BelongsToMany' => false,
        'HasMany' => true,
        'HasManyThrough' => true,
        'HasOne' => true,
        'HasOneThrough' => true,
        'MorphMany' => true,
        'MorphOne' => true,
        'MorphTo' => 'from_attribute',
        'MorphToMany' => false,
    ];

    protected $relationsFromAttribute = [
        'BelongsTo' => 'getForeignKeyName',
        'BelongsToMany' => 'getParentKeyName',
        'HasMany' => 'getLocalKeyName',
        'HasManyThrough' => 'getLocalKeyName',
        'HasOne' => 'getLocalKeyName',
        'HasOneThrough' => 'getLocalKeyName',
        'MorphMany' => 'getLocalKeyName',
        'MorphOne' => 'getLocalKeyName',
        'MorphTo' => 'getForeignKeyName',
        'MorphToMany' => 'getParentKeyName',
    ];

    protected $relationsToAttribute = [
        'BelongsTo' => 'getOwnerKeyName',
        'BelongsToMany' => 'getRelatedKeyName',
        'HasMany' => 'getForeignKeyName',
        'HasManyThrough' => 'getForeignKeyName',
        'HasOne' => 'getForeignKeyName',
        'HasOneThrough' => 'getForeignKeyName',
        'MorphMany' => 'getForeignKeyName',
        'MorphOne' => 'getForeignKeyName',
        'MorphTo' => 'getForeignKeyName',
        'MorphToMany' => 'getRelatedKeyName',
    ];

    protected $relationsPivotsAttributes = [
        'BelongsToMany' => [
            'class' => 'getPivotClass',
            'from' => 'getForeignPivotKeyName',
            'to' => 'getRelatedPivotKeyName',
        ],
        'MorphToMany' => [
            'class' => 'getPivotClass',
            'from' => 'getForeignPivotKeyName',
            'to' => 'getRelatedPivotKeyName',
        ],
    ];

    protected $relationsThroughAttributes = [
        'HasManyThrough' => [
            // 'class' => 'getThroughParentClass',
            // throughParent is a private attribute
            'from' => 'getFirstKeyName',
            'to' => 'getSecondLocalKeyName',
        ],
        'HasOneThrough' => [
            // 'class' => 'getThroughParentClass',
            // throughParent is a private attribute
            'from' => 'getFirstKeyName',
            'to' => 'getSecondLocalKeyName',
        ],
    ];

    protected $relationsMorphAttributes = [
        'MorphMany' => [
            'attribute' => 'getMorphType',
        ],
        'MorphOne' => [
            'attribute' => 'getMorphType',
        ],
        'MorphTo' => [
            'attribute' => 'getMorphType',
        ],
        'MorphToMany' => [
            'attribute' => 'getMorphType',
        ],
    ];

    /**
     * Relation types whose degenerate self-reference (from === to &&
     * from_attribute === to_attribute) marks an UNRESOLVABLE target: model:show
     * can never resolve a MorphTo's real target (it's runtime data in the
     * *_type column) and always reports the declaring model itself, so every
     * MorphTo trips this. Such records used to be dropped outright; they now
     * emit TARGETLESS (to/to_attribute null, morph fields intact, morph_key
     * matching the owner side's) so the child keeps knowledge of its own
     * morph column pair — see MorphChildTest.
     * MorphToMany used to be listed here too, but was inert (its to_attribute
     * was a pivot key, so the attributes never matched) — and once
     * relationsToAttribute reported real parent keys for it, keeping it would
     * have dropped legitimate self-referential relations (e.g. User.follows
     * via morphToMany(User::class)), so it was removed.
     */
    protected $relationsMorphSkipDefintions = [
        'MorphTo',
    ];

    public function getPrivateProperty($object, $property)
    {
        // hack the private property
        return (fn () => $this->{$property})->call($object);
    }

    /**
     * Return the current Draftsman config (config/draftsman.php) as JSON.
     * If the published config file does not exist, attempt to publish it,
     * then fall back to the vendor default if still unavailable.
     *
     * Alongside the stored config, `capabilities` reports what this backend
     * can DO right now — computed per request, never persisted. Frontends
     * shape their UI on it (e.g. the download menu offers pdf only when
     * render/{slug}?format=pdf would actually work).
     */
    public function getConfig(GetDraftsmanConfig $action, RenderGraphImage $imageRenderer)
    {
        try {
            $data = $action->handle();
            $data['capabilities'] = [
                'render' => [
                    'formats' => $imageRenderer->available()
                        ? ['html', ...RenderGraphImage::FORMATS]
                        : ['html'],
                ],
            ];
            // The host app's `artisan about` report (versions, drivers,
            // environment) — for frontends to show current-stack info. Same
            // call pattern as getModelShow's model:show: trust the output
            // only when the command exits 0.
            $data['about'] = [];
            if (Artisan::call('about', ['--json' => true]) === 0) {
                $data['about'] = json_decode(Artisan::output(), true) ?? [];
            }

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to load Draftsman configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getPrivatePropertyClass($object, $property)
    {
        $value = $this->getPrivateProperty($object, $property);
        if (! $value) {
            return null;
        }

        return get_class($value);
    }

    /**
     * model:show invokes every relation method with no per-method rescue, so a
     * single throwing relation (spatie/laravel-medialibrary's Media::
     * temporaryUploads() throws unless the pro package is installed — found
     * live on BHE, 2026-07-26) kills the whole model's introspection. When it
     * throws, fall back to introspecting ourselves: attributes from the schema,
     * relations enumerated by reflection with a per-method try/catch — the
     * grumpy relation costs itself, not the model.
     */
    public function getModelShow($model): ?\stdClass
    {
        try {
            $shown = Artisan::call('model:show', ['model' => $model, '--json' => true]) === 0;
        } catch (\Throwable) {
            $shown = false;
            $fallback = $this->showFromSchema($model);
        }
        if ($shown || isset($fallback)) {
            $data = $shown ? json_decode(Artisan::output()) : $fallback;
            if (! $data) {
                return null;
            }
            $mod = new $model;
            $ref = new \ReflectionClass($model);
            $data->namespace = substr($data->class, 0, strrpos($data->class, '\\'));
            $data->file = $ref?->getFileName() ?? null;
            $data->attributes_count = count($data->attributes) ?? 0;
            $data->relations_count = count($data->relations) ?? 0;
            $keyed_attributes = collect($data->attributes)->keyBy('name');
            $realated_models = [];

            foreach ($data->relations as &$relation) {
                if (in_array($relation->type, $this->relationsOmitList)) {
                    $relation = null;

                    continue;
                }
                if (isset($this->relationsRestrictToList) && count($this->relationsRestrictToList) && ! in_array($relation->type, $this->relationsRestrictToList)) {
                    $relation = null;

                    continue;
                }
                $function = $relation->name;
                $related = $relation->related;
                $framework_type = $relation->type;
                unset($relation->related);
                $relation->framework_type = $framework_type;
                $relation->type = (array_key_exists($framework_type, $this->relationsTypeMap)) ? $this->relationsTypeMap[$framework_type] : null;
                $relation->connection = (array_key_exists($framework_type, $this->relationsConnectionMap)) ? $this->relationsConnectionMap[$framework_type] : null;
                $relation->multiplicity = (array_key_exists($framework_type, $this->relationsMultiplicityMap)) ? $this->relationsMultiplicityMap[$framework_type] : null;
                $relation->mandatory = (array_key_exists($framework_type, $this->relationsMandatoryMap)) ? $this->relationsMandatoryMap[$framework_type] : false;
                $relation->key = $model.'.'.$function;
                $rel = $mod->$function();
                $relref = $ref->getMethod($function);
                $relation->file = $relref?->getFileName() ?? null;
                $relation->line = $relref?->getStartLine() ?? null;
                $from_attribute = null;
                $to_attribute = null;
                $pivot_attributes = [];
                $through_attributes = [];
                $morph_attributes = [];
                if (array_key_exists($framework_type, $this->relationsFromAttribute)) {
                    $from_attribute = $this->relationsFromAttribute[$framework_type];
                    $from_attribute = $rel->$from_attribute();
                }
                if (array_key_exists($framework_type, $this->relationsToAttribute)) {
                    $to_attribute = $this->relationsToAttribute[$framework_type];
                    $to_attribute = $rel->$to_attribute();
                }
                if (array_key_exists($framework_type, $this->relationsPivotsAttributes)) {
                    $pivot_attributes = $this->relationsPivotsAttributes[$framework_type];
                    foreach ($pivot_attributes as $pivot_key => $pivot_attribute) {
                        $pivot_attributes[$pivot_key] = $rel->$pivot_attribute();
                    }
                    // Both BASE pivot classes mean "generic join table, no
                    // model" — dot-join the table so the payload names it
                    // (and the bare class can't masquerade as a real model).
                    // MorphPivot arrives via ->using(MorphPivot::class), the
                    // spatie/laravel-tags shape.
                    if (in_array($pivot_attributes['class'], [Pivot::class, MorphPivot::class])) {
                        $pivot_attributes['class'] .= '.'.$rel->getTable();
                    }
                }
                if (array_key_exists($framework_type, $this->relationsThroughAttributes)) {
                    $through_attributes = $this->relationsThroughAttributes[$framework_type];
                    foreach ($through_attributes as $through_key => $through_attribute) {
                        $through_attributes[$through_key] = $rel->$through_attribute();
                    }
                    $through_attributes['class'] = $this->getPrivatePropertyClass($rel, 'throughParent');
                    // seems to throw an error if put BEFORE the foreach
                }
                if (array_key_exists($framework_type, $this->relationsMorphAttributes)) {
                    $morph_attributes = $this->relationsMorphAttributes[$framework_type];
                    foreach ($morph_attributes as $morph_key => $morph_attribute) {
                        $morph_attributes[$morph_key] = $rel->$morph_attribute();
                    }
                }
                $relation->from = $model;
                $relation->from_attribute = $from_attribute;
                $relation->to = $related;
                $relation->to_attribute = $to_attribute;
                if (in_array($framework_type, $this->relationsMorphSkipDefintions)) {
                    if (($relation->from === $relation->to) && ($relation->from_attribute === $relation->to_attribute)) {
                        // model:show can't resolve a MorphTo's target (runtime
                        // data in the *_type column) and reports the declaring
                        // model itself. Emit the record TARGETLESS rather than
                        // dropping it: the child keeps knowledge of its own
                        // morph column pair (so renderers can badge notable_id
                        // as a key even with every owner off-graph), and the
                        // morph_key below joins it to its owner edges.
                        $relation->to = null;
                        $relation->to_attribute = null;
                    }
                }
                // targetless records skip the count, so a fabricated morph
                // self-target can't register a phantom related_model
                if ($relation->to !== null) {
                    $realated_models[$related] ??= 0;
                    $realated_models[$related]++;
                }
                if ($pivot_attributes) {
                    foreach ($pivot_attributes as $pivot_key => $pivot_attribute) {
                        $relation->{'pivot_'.$pivot_key} = $pivot_attribute;
                    }
                }
                if ($through_attributes) {
                    foreach ($through_attributes as $through_key => $through_attribute) {
                        $relation->{'through_'.$through_key} = $through_attribute;
                    }
                }
                if ($morph_attributes) {
                    foreach ($morph_attributes as $morph_key => $morph_attribute) {
                        $relation->{'morph_'.$morph_key} = $morph_attribute;
                    }
                    // morph_key always names the CHILD's column pair — the
                    // owner side reaches it via related/to_attribute, the
                    // targetless child via its own from side — so both sides
                    // of one morph emit the identical key (MorphChildTest).
                    $relation->{'morph_key'} = ($relation->to === null)
                        ? $relation->from.'.'.$relation->from_attribute.'.'.$relation->{'morph_attribute'}
                        : $related.'.'.$to_attribute.'.'.$relation->{'morph_attribute'};
                }
                if (is_string($relation->mandatory)) {
                    $check_attr = $relation->{$relation->mandatory} ?? null;
                    $keyed_attr = $keyed_attributes[$check_attr] ?? null;
                    if ($check_attr && $keyed_attr) {
                        $mandatory_attr = collect($keyed_attr)->toArray();
                        // mandatory is the NEGATION of the column's nullable flag
                        $relation->mandatory = ! ($mandatory_attr['nullable'] ?? false);
                    } else {
                        // column not introspectable — assume the conventional
                        // non-null FK, matching the static entries above
                        $relation->mandatory = true;
                    }
                }
                if ($relation->to === null) {
                    // No second endpoint to sort against — scope the key to
                    // the child's own column pair. 'morph:' never collides
                    // with the owner edges' keys, and a second MorphTo on the
                    // same model gets its own.
                    $relation->relationship_key = 'morph:'.$relation->morph_key;
                } else {
                    $key_parts = [
                        $relation->from.'.'.$relation->from_attribute,
                        $relation->to.'.'.$relation->to_attribute,
                    ];
                    sort($key_parts, SORT_STRING);
                    $relation->relationship_key = ($this->relationshipKeyScopeMap[$framework_type] ?? 'direct').':'.implode('.', $key_parts);
                }
            }
            $data->relations = array_values(array_filter($data->relations)) ?? [];
            $data->relations_count = count($data->relations) ?? 0;
            arsort($realated_models);
            $data->related_models = array_keys($realated_models);

            return $data;
        }

        return null;
    }

    public function getModel($model)
    {
        return $this->getModelShow($model);
    }

    /**
     * Hand-rolled stand-in for model:show's JSON, used when model:show itself
     * throws (see getModelShow). Attributes come from the live schema;
     * relations are enumerated by reflection — public zero-parameter methods
     * with a Relation return type — each invoked inside its own try/catch, so
     * a throwing relation method is skipped instead of sinking the model.
     * Same shape as the model:show payload, so the enrichment loop runs on it
     * unchanged.
     */
    protected function showFromSchema(string $model): ?\stdClass
    {
        try {
            $mod = new $model;
            $table = $mod->getTable();
            if (! Schema::hasTable($table)) {
                return null;
            }

            $uniques = collect(Schema::getIndexes($table))
                ->filter(fn ($i) => ($i['unique'] ?? false) && count($i['columns']) === 1)
                ->map(fn ($i) => $i['columns'][0])
                ->flip();

            $attributes = collect(Schema::getColumns($table))->map(fn ($c) => (object) [
                'name' => $c['name'],
                'type' => $c['type'],
                'increments' => (bool) ($c['auto_increment'] ?? false),
                'nullable' => (bool) ($c['nullable'] ?? false),
                'default' => $c['default'] ?? null,
                'unique' => isset($uniques[$c['name']]),
                'fillable' => $mod->isFillable($c['name']),
                'hidden' => in_array($c['name'], $mod->getHidden()),
                'appended' => false,
                'cast' => null,
            ])->values()->all();

            $relations = [];
            foreach ((new \ReflectionClass($model))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || $method->getNumberOfParameters() > 0) {
                    continue;
                }
                $return = $method->getReturnType();
                if (! $return instanceof \ReflectionNamedType || ! is_subclass_of($return->getName(), Relation::class)) {
                    continue;
                }
                try {
                    $rel = $mod->{$method->getName()}();
                } catch (\Throwable) {
                    continue; // the throwing relation costs itself, not the model
                }
                $relations[] = (object) [
                    'name' => $method->getName(),
                    'type' => class_basename($rel),
                    'related' => get_class($rel->getRelated()),
                ];
            }

            return (object) [
                'class' => $model,
                'database' => $mod->getConnection()->getName(),
                'table' => $table,
                'policy' => null,
                'attributes' => $attributes,
                'relations' => $relations,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * All models the graph needs: the app_path() scan, PLUS every model those
     * relations reach that the scan can't see — package models (Spatie
     * Activity/Media, Cashier Subscription, DatabaseNotification, …), flagged
     * `vendor: true`. Followed transitively (a queue with a seen-set: vendor
     * models relate onward, e.g. Subscription -> SubscriptionItem), because a
     * mature Laravel app leans on dozens of these and every un-followed
     * target is a silently missing edge. Un-introspectable targets (no table,
     * abstract, not a model) are skipped like any other failed show.
     */
    public function getModels(): array
    {
        $data = [];
        $queue = $this->getModelsList();
        $appModels = array_flip($queue);
        $seen = $appModels;

        while ($queue) {
            $model = array_shift($queue);
            $show = $this->getModelShow($model);
            if (! $show) {
                continue;
            }
            if (! isset($appModels[$model])) {
                $show->vendor = true;
            }
            $data[] = $show;

            foreach ($show->relations as $relation) {
                // to = the far endpoint; pivot/through = the traversed model.
                // class_exists screens out generic pivots ("…\Pivot.<table>")
                // and MorphTo's null target without special-casing either.
                $targets = [
                    $relation->to ?? null,
                    $relation->pivot_class ?? null,
                    $relation->through_class ?? null,
                ];
                foreach ($targets as $target) {
                    if (! $target || isset($seen[$target]) || ! class_exists($target)) {
                        continue;
                    }
                    $seen[$target] = true;
                    $queue[] = $target;
                }
            }
        }

        return array_merge($data, $this->pivotTableShows($data));
    }

    /**
     * Pseudo-models for GENERIC pivot tables — a BelongsToMany/MorphToMany
     * declared without ->using() traverses a join table no model represents
     * (pivot_class "<base class>.<table>"). Emitting the table as a model of
     * its own lets the graph draw it as a real junction node, exactly like a
     * model-backed pivot (Membership) already appears.
     *
     * One entry per TABLE (several base classes can name the same table —
     * Pivot.taggables and MorphPivot.taggables — the alphabetically first
     * dotted id wins), skipping tables a scanned model already owns.
     * Attributes come from the schema; relations are the two underlying
     * halves of each traversal as BelongsTo records (pivot column ->
     * endpoint key), deduped by relationship_key across mirror declarations —
     * plus a targetless MorphTo per morph column pair, exactly like a morph
     * child model, so the *_id/*_type columns badge correctly.
     */
    protected function pivotTableShows(array $shows): array
    {
        $ownedTables = [];
        foreach ($shows as $show) {
            $ownedTables[$show->table] = true;
        }

        // table => ['ids' => dotted classes seen, 'traversals' => relation records]
        $tables = [];
        foreach ($shows as $show) {
            foreach ($show->relations as $relation) {
                $pivotClass = $relation->pivot_class ?? null;
                if (! $pivotClass || ! str_contains($pivotClass, '.')) {
                    continue;
                }
                $table = substr($pivotClass, strpos($pivotClass, '.') + 1);
                if (isset($ownedTables[$table])) {
                    continue;
                }
                $tables[$table]['ids'][$pivotClass] = true;
                $tables[$table]['traversals'][] = $relation;
            }
        }

        $pivots = [];
        foreach ($tables as $table => $info) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $class = min(array_keys($info['ids']));

            $uniques = collect(Schema::getIndexes($table))
                ->filter(fn ($i) => ($i['unique'] ?? false) && count($i['columns']) === 1)
                ->map(fn ($i) => $i['columns'][0])
                ->flip();
            $attributes = collect(Schema::getColumns($table))->map(fn ($c) => (object) [
                'name' => $c['name'],
                'type' => $c['type'],
                'increments' => (bool) ($c['auto_increment'] ?? false),
                'nullable' => (bool) ($c['nullable'] ?? false),
                'default' => $c['default'] ?? null,
                'unique' => isset($uniques[$c['name']]),
                'appended' => false,
                'cast' => null,
            ])->values();
            $byName = $attributes->keyBy('name');
            $mandatory = fn (string $column) => ! ($byName[$column]->nullable ?? false);

            $relations = [];
            $keyed = [];
            foreach ($info['traversals'] as $traversal) {
                // Each traversal decomposes into (pivot_from -> from) and
                // (pivot_to -> to); mirror declarations produce the same
                // halves, deduped by relationship_key.
                $halves = [
                    [$traversal->pivot_from, $traversal->from, $traversal->from_attribute],
                    [$traversal->pivot_to, $traversal->to, $traversal->to_attribute],
                ];
                foreach ($halves as [$column, $to, $toAttribute]) {
                    $key_parts = [$class.'.'.$column, $to.'.'.$toAttribute];
                    sort($key_parts, SORT_STRING);
                    $relationshipKey = 'direct:'.implode('.', $key_parts);
                    if (isset($keyed[$relationshipKey])) {
                        continue;
                    }
                    $keyed[$relationshipKey] = true;
                    $relations[] = (object) [
                        'name' => str_replace('_id', '', $column),
                        'framework_type' => 'BelongsTo',
                        'type' => 'one',
                        'connection' => 'direct',
                        'multiplicity' => 'many',
                        'mandatory' => $mandatory($column),
                        'key' => $class.'.'.$column.'->'.$to,
                        'file' => null,
                        'line' => null,
                        'from' => $class,
                        'from_attribute' => $column,
                        'to' => $to,
                        'to_attribute' => $toAttribute,
                        'relationship_key' => $relationshipKey,
                    ];
                }

                // Morph pivots also carry the *_id/*_type pair — emit the
                // targetless MorphTo so the columns badge like a morph child.
                $morphType = $traversal->morph_attribute ?? null;
                if ($morphType) {
                    $morphId = str_replace('_type', '_id', $morphType);
                    $morphKey = $class.'.'.$morphId.'.'.$morphType;
                    if (! isset($keyed['morph:'.$morphKey])) {
                        $keyed['morph:'.$morphKey] = true;
                        $relations[] = (object) [
                            'name' => str_replace('_type', '', $morphType),
                            'framework_type' => 'MorphTo',
                            'type' => 'one',
                            'connection' => 'direct',
                            'multiplicity' => 'many',
                            'mandatory' => $mandatory($morphId),
                            'key' => $class.'.'.$morphId.'->morph',
                            'file' => null,
                            'line' => null,
                            'from' => $class,
                            'from_attribute' => $morphId,
                            'to' => null,
                            'to_attribute' => null,
                            'morph_attribute' => $morphType,
                            'morph_key' => $morphKey,
                            'relationship_key' => 'morph:'.$morphKey,
                        ];
                    }
                }
            }

            $related = array_values(array_unique(array_filter(array_map(fn ($r) => $r->to, $relations))));

            $pivots[] = (object) [
                'class' => $class,
                'database' => config('database.default'),
                'table' => $table,
                'policy' => null,
                'attributes' => $attributes->all(),
                'relations' => $relations,
                'namespace' => substr($class, 0, strrpos($class, '\\')),
                'file' => null,
                'attributes_count' => $attributes->count(),
                'relations_count' => count($relations),
                'related_models' => $related,
            ];
        }

        return $pivots;
    }

    public function getModelsList(): array
    {
        $models = collect(File::allFiles(app_path()))
            ->map(function ($item) {
                $path = $item->getRelativePathName();
                $class = sprintf('%s%s',
                    Container::getInstance()->getNamespace(),
                    strtr(substr($path, 0, strrpos($path, '.')), '/', '\\'));

                return $class;
            })
            ->filter(function ($class) {
                $valid = false;
                if (class_exists($class)) {
                    $reflection = new \ReflectionClass($class);
                    $valid = $reflection->isSubclassOf(EloquentModel::class) &&
                        ! $reflection->isAbstract();
                }

                return $valid;
            });

        return $models->values()->sort()->toArray();
    }

    /**
     * Update Draftsman configuration and optionally ENV values.
     */
    public function updateConfig(Request $request, UpdateDraftsmanConfig $action)
    {
        try {
            $payload = $request->json()->all()['config'];
            if (! is_array($payload)) {
                return response()->json([
                    'message' => 'Invalid JSON body. Expecting an object matching draftsman.php structure.',
                ], 422);
            }

            $result = $action->handle($payload);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to update Draftsman configuration.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
