<?php

namespace Draftsman\Draftsman\Actions;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Container\Container;

class UpdateDraftsmanConfig
{
    /**
     * Handle updating the Draftsman config and, conditionally, the .env file.
     *
     * Rules:
     * - Ensure config/draftsman.php exists (publish if missing).
     * - Merge vendor defaults with incoming payload (payload overrides).
     * - Write merged config to config/draftsman.php.
     * - If update_env (from merged config) is true, update only existing
     *   DRAFTSMAN_* keys in .env. Never append new keys.
     * - Always run config:clear after writing config and any env updates.
     *
     * @param array $payload JSON body decoded to array
     * @return array{message:string, config:array, env_updated:bool}
     */
    public function handle(array $payload): array
    {
        $publishedConfigPath = base_path('config/draftsman.php');
        $vendorConfigPath = base_path('vendor/draftsmaninc/draftsman/config/draftsman.php');

        // Ensure published config exists
        if (! File::exists($publishedConfigPath)) {
            Artisan::call('vendor:publish', ['--tag' => 'draftsman-config']);
        }

        // Load defaults from vendor
        $defaults = [];
        if (File::exists($vendorConfigPath)) {
            $defaults = include $vendorConfigPath;
            if (! is_array($defaults)) {
                $defaults = [];
            }
        }

        // Merge defaults with incoming payload (payload overwrites)
        $merged = $this->arrayMergeRecursiveDistinct($defaults, $payload);

        // Write merged config (ensuring model class constants are emitted properly)
        $this->writePhpConfig($publishedConfigPath, $merged);

        $didWriteEnv = false;
        $updateEnvFlag = (bool) Arr::get($merged, 'package.update_env', Arr::get($merged, 'update_env', false));
        if ($updateEnvFlag === true) {
            $envUpdates = $this->buildEnvMapFromConfig($merged);
            if (count($envUpdates)) {
                $this->updateEnvFile($envUpdates);
                $didWriteEnv = true;
            }
        }

        // Always clear config when writing
        Artisan::call('config:clear');

        return [
            'message' => 'Draftsman configuration updated successfully.',
            'config' => $merged,
            'env_updated' => $didWriteEnv,
        ];
    }

    private function arrayMergeRecursiveDistinct(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = $this->arrayMergeRecursiveDistinct($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private function writePhpConfig(string $path, array $config): void
    {
        // Render the config file in well-defined blocks (per section), injecting
        // comments from configured maps and honoring line breaks. Build the
        // entire file content in memory and write once for atomicity.
        $contents = $this->renderConfigFileInBlocks($config);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }

    /**
     * Export config to PHP code, ensuring that model references (e.g., keys in
     * 'presentation') are written as class constants (Foo\\Bar\\Baz::class)
     * instead of plain strings, and resolving short names based on models_path.
     */
    // --- Comment-aware, per-section renderer (build then write once) ---

    // Parsed formatting data loaded from vendor config file to stay in sync
    // with original comments/structure.
    private array $vendorSectionComments = [];
    private array $vendorKeyComments = [];
    private ?array $vendorPresentationExample = null; // array of raw lines
    // Map of vendor RHS expressions to honor env()/helper code in generation
    // [section][key] => ['type' => 'env', 'env_key' => string, 'default_code' => string]
    //                 | ['type' => 'code', 'code' => string]
    private array $vendorKeyExpressions = [];

    // Whitelisted keys allowed under each model in presentation section
    // Updated to reflect new schema: only 'icon' and consolidated 'class'
    private array $presentationAllowedKeys = ['icon', 'class'];

    private function renderConfigFileInBlocks(array $config): string
    {
        // Load vendor formatting (comments, example blocks) once per render
        $this->loadVendorFormatting();

        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = '/*';
        $lines[] = '|--------------------------------------------------------------------------';
        $lines[] = '| Draftsman Config';
        $lines[] = '|--------------------------------------------------------------------------';
        $lines[] = '|';
        $lines[] = '| This file has now been auto-generated by Draftsman. If you update';
        $lines[] = '| manually, please refresh Draftsman to see your changes applied.';
        $lines[] = '|';
        $lines[] = '*/';
        $lines[] = '';
        $lines[] = 'return [';

        foreach ($config as $section => $values) {
            $this->appendSectionComment($lines, (string) $section, 1);
            $this->renderSectionBlock($lines, (string) $section, $values, $config, 1);
        }

        $lines[] = '];';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function renderSectionBlock(array &$lines, string $section, $value, array $mergedConfig, int $indentLevel): void
    {
        $indent = str_repeat('    ', $indentLevel);

        if (! is_array($value)) {
            $valCode = $this->exportScalar($value);
            $inline = $this->getInlineComment($section);
            $line = sprintf("%s%s => %s,", $indent, var_export($section, true), $valCode);
            if ($inline !== null) {
                $line .= ' // ' . $inline;
            }
            $lines[] = $line;
            return;
        }

        $lines[] = sprintf("%s%s => [", $indent, var_export($section, true));

        // Special handling for presentation: if empty, preserve vendor example block
        if ($section === 'presentation' && is_array($value) && count($value) === 0) {
            if (is_array($this->vendorPresentationExample) && ! empty($this->vendorPresentationExample)) {
                foreach ($this->vendorPresentationExample as $rawLine) {
                    $lines[] = $rawLine; // already indented in vendor file
                }
            }
            $lines[] = sprintf("%s],", $indent);
            $lines[] = '';
            return;
        }

        $isAssoc = $this->isAssoc($value);
        foreach ($value as $k => $v) {
            $path = [$section, $k];
            $this->appendKeyComments($lines, $path, $indentLevel + 1);

            $keyCode = $isAssoc
                ? $this->exportKeyWithModelAwareness($k, $section, $mergedConfig, $path)
                : null;

            $this->renderValueLine($lines, $keyCode, $v, $path, $mergedConfig, $indentLevel + 1);
        }

        $lines[] = sprintf("%s],", $indent);
        $lines[] = '';
    }

    private function renderValueLine(array &$lines, ?string $keyCode, $value, array $path, array $mergedConfig, int $indentLevel): void
    {
        $indent = str_repeat('    ', $indentLevel);

        // If this element corresponds to a vendor env()-patterned key, emit as env(KEY, <exported payload>)
        if ($keyCode !== null && count($path) >= 2) {
            $section = (string) $path[0];
            $k = $path[1];
            if (is_string($k) && isset($this->vendorKeyExpressions[$section][$k])) {
                $expr = $this->vendorKeyExpressions[$section][$k];
                if (($expr['type'] ?? null) === 'env' && isset($expr['env_key'])) {
                    // If vendor used a helper inside env default (e.g., base_path/app_path/storage_path),
                    // preserve that helper and wrap ONLY the relative portion of the payload into it.
                    if (isset($expr['helper']) && is_string($expr['helper']) && $expr['helper'] !== '') {
                        $defaultCode = $this->buildHelperDefaultCode($expr['helper'], $value);
                    } else {
                        $defaultCode = $this->exportPhpCode($value, $path, $indentLevel);
                    }
                    $line = $indent . $keyCode . ' => env(' . var_export($expr['env_key'], true) . ', ' . $defaultCode . '),';
                    $lines[] = $line;
                    return;
                }
            }
        }

        if (is_array($value)) {
            $prefix = $keyCode ? ($indent . $keyCode . ' => [') : ($indent . '[');
            $lines[] = $prefix;

            $isAssoc = $this->isAssoc($value);

            // If inside presentation section at model-level, filter allowed keys
            if ($isAssoc && ($path[0] ?? null) === 'presentation' && count($path) === 2) {
                $value = array_intersect_key($value, array_flip($this->presentationAllowedKeys));
            }
            foreach ($value as $k => $v) {
                $childPath = array_merge($path, [$k]);
                $this->appendKeyComments($lines, $childPath, $indentLevel + 1);
                $childKey = $isAssoc
                    ? $this->exportKeyWithModelAwareness($k, $path[0] ?? '', $mergedConfig, $childPath)
                    : null;
                $this->renderValueLine($lines, $childKey, $v, $childPath, $mergedConfig, $indentLevel + 1);
            }

            $lines[] = str_repeat('    ', $indentLevel) . '],';
            return;
        }

        $valCode = $this->exportScalar($value);
        $line = $keyCode ? ($indent . $keyCode . ' => ' . $valCode . ',') : ($indent . $valCode . ',');

        // Inline comments are only included if present in vendor map (rare)
        $inline = $this->getInlineComment($this->dotPath($path));
        if ($inline !== null && $inline !== '') {
            $line .= ' // ' . $inline;
        }

        $lines[] = $line;
    }

    private function exportKeyWithModelAwareness($key, string $topSection, array $mergedConfig, array $path = []): string
    {
        // Only convert model keys at the first level under 'presentation'.
        // Nested option keys like 'icon', 'bg_color', 'text_color' must remain plain strings.
        if ($topSection === 'presentation' && is_string($key)) {
            // Path is like ['presentation', <modelKey>] for model level
            if (count($path) === 2) {
                $fqcn = $this->resolveModelFqcn($key, $mergedConfig);
                return $fqcn . '::class';
            }
            // Any deeper levels should be exported as quoted strings
            return var_export($key, true);
        }
        return var_export($key, true);
    }

    private function appendSectionComment(array &$lines, string $section, int $indentLevel): void
    {
        $block = $this->vendorSectionComments[$section] ?? null;
        if ($block === null || $block === '') {
            return;
        }
        // block already contains correct indentation and comment delimiters
        foreach (preg_split("/\r?\n/", $block) as $l) {
            $lines[] = $l;
        }
    }

    private function appendKeyComments(array &$lines, array $path, int $indentLevel): void
    {
        // Only handle comments that immediately precede keys in vendor file
        // Path must have at least [section, key]
        if (count($path) < 2) {
            return;
        }
        $section = (string) $path[0];
        $key = $path[1];
        if (! is_string($key)) {
            return;
        }
        $comment = $this->vendorKeyComments[$section][$key] ?? null;
        if ($comment === null || $comment === '') {
            return;
        }
        foreach (preg_split("/\r?\n/", $comment) as $l) {
            $lines[] = $l; // raw vendor comment lines with proper indentation
        }
    }

    private function getInlineComment(string $dotPath): ?string
    {
        // Inline comments are not common in vendor; retain hook if needed in future
        return null;
    }

    private function dotPath(array $segments): string
    {
        return implode('.', array_map(function ($s) { return is_int($s) ? (string) $s : (string) $s; }, $segments));
    }

    private function exportScalar($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return var_export($value, true);
    }

    /**
     * Load and parse vendor config file to extract comment blocks and example content
     * so generated file mirrors original formatting and stays in sync automatically.
     */
    private function loadVendorFormatting(): void
    {
        $vendorConfigPath = base_path('vendor/draftsmaninc/draftsman/config/draftsman.php');
        if (! File::exists($vendorConfigPath)) {
            // Nothing to load
            $this->vendorSectionComments = [];
            $this->vendorKeyComments = [];
            $this->vendorPresentationExample = null;
            $this->vendorKeyExpressions = [];
            return;
        }

        $contents = File::get($vendorConfigPath);
        $lines = preg_split("/\r?\n/", $contents);

        // Identify section positions and extract their preceding block comments
        $sections = ['package', 'front', 'graph', 'presentation'];
        $sectionLineIndex = [];
        foreach ($lines as $i => $line) {
            foreach ($sections as $sec) {
                // Match: 4 spaces, 'section' => [
                if (preg_match("/^\s{4}'" . preg_quote($sec, '/') . "'\s*=>\s*\[/", $line)) {
                    $sectionLineIndex[$sec] = $i;
                }
            }
        }

        $this->vendorSectionComments = [];
        foreach ($sectionLineIndex as $sec => $idx) {
            // Walk backwards to find start of block comment '/*'
            $start = null;
            $end = null;
            for ($j = $idx - 1; $j >= 0; $j--) {
                if ($end === null && strpos($lines[$j], '*/') !== false) {
                    $end = $j;
                    continue;
                }
                if ($end !== null && strpos($lines[$j], '/*') !== false) {
                    $start = $j;
                    break;
                }
                // Stop if we hit another section or 'return ['
                if (preg_match("/^\s{4}'[a-z_]+?'\s*=>\s*\[/i", $lines[$j]) || trim($lines[$j]) === 'return [' ) {
                    break;
                }
            }
            if ($start !== null && $end !== null && $start <= $end) {
                $blockLines = array_slice($lines, $start, $end - $start + 1);
                $this->vendorSectionComments[$sec] = implode("\n", $blockLines);
            }
        }

        // Extract per-key preceding // comments within each section
        $this->vendorKeyComments = [];
        foreach ($sectionLineIndex as $sec => $idx) {
            // Find end of section: the matching closing line with same indent: 4 spaces ] ,
            $endIdx = null;
            for ($k = $idx + 1; $k < count($lines); $k++) {
                if (preg_match("/^\s{4}\],\s*$/", $lines[$k])) {
                    $endIdx = $k;
                    break;
                }
            }
            if ($endIdx === null) {
                continue;
            }
            $this->vendorKeyComments[$sec] = [];
            $this->vendorKeyExpressions[$sec] = [];
            $buffer = [];
            for ($k = $idx + 1; $k < $endIdx; $k++) {
                $ln = $lines[$k];
                if (preg_match("/^\s*\/\/ /", $ln)) {
                    // Accumulate consecutive // comment lines
                    $buffer[] = $ln;
                    continue;
                }
                // Key line pattern: 8 spaces 'key' =>
                if (preg_match("/^\s{8}'([^']+)'\s*=>\s*(.+?),\s*$/", $ln, $m)) {
                    $keyName = $m[1];
                    $rhs = $m[2]; // raw RHS code up to trailing comma
                    if (! empty($buffer)) {
                        $this->vendorKeyComments[$sec][$keyName] = implode("\n", $buffer);
                        $buffer = [];
                    }
                    // Detect env() pattern or raw helper/literal code
                    $envMatch = [];
                    if (preg_match("/^env\(\s*'([^']+)'\s*,\s*(.+)\)$/", $rhs, $envMatch)) {
                        $expr = [
                            'type' => 'env',
                            'env_key' => $envMatch[1],
                            'default_code' => $envMatch[2],
                        ];
                        // Detect helper call inside default (e.g., base_path('...'), app_path('...'), storage_path('...'))
                        $defaultTrim = trim($envMatch[2]);
                        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $defaultTrim, $hm)) {
                            $helper = $hm[1];
                            if (in_array($helper, ['base_path', 'app_path', 'storage_path'], true)) {
                                $expr['helper'] = $helper;
                            }
                        }
                        $this->vendorKeyExpressions[$sec][$keyName] = $expr;
                    } else {
                        $this->vendorKeyExpressions[$sec][$keyName] = [
                            'type' => 'code',
                            'code' => $rhs,
                        ];
                    }
                } else {
                    // Reset buffer if any other non-comment content
                    $buffer = [];
                }
            }
        }

        // Extract presentation example block: lines between section open and close
        $this->vendorPresentationExample = [];
        if (isset($sectionLineIndex['presentation'])) {
            $startIdx = $sectionLineIndex['presentation'];
            // Find open bracket line (same line), capture subsequent lines until closing of section
            $endIdx = null;
            for ($k = $startIdx + 1; $k < count($lines); $k++) {
                if (preg_match("/^\s{4}\],\s*$/", $lines[$k])) {
                    $endIdx = $k;
                    break;
                }
            }
            if ($endIdx !== null) {
                // Slice the body lines (excluding opening and closing lines)
                $body = array_slice($lines, $startIdx + 1, $endIdx - ($startIdx + 1));
                // Keep as-is
                $this->vendorPresentationExample = $body;
            }
        }
    }

    private function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Export a PHP value to code (short array syntax) preserving booleans/null/strings.
     * Used to render the default argument for env() when honoring vendor patterns.
     */
    private function exportPhpCode($value, array $path = [], int $indentLevel = 0): string
    {
        if (is_array($value)) {
            $isAssoc = $this->isAssoc($value);
            // Filter presentation options when exporting nested arrays at model level
            if ($isAssoc && ($path[0] ?? null) === 'presentation' && count($path) === 2) {
                $value = array_intersect_key($value, array_flip($this->presentationAllowedKeys));
            }
            if (empty($value)) {
                return '[]';
            }
            $indent = str_repeat('    ', $indentLevel + 1);
            $closeIndent = str_repeat('    ', $indentLevel);
            $items = [];
            foreach ($value as $k => $v) {
                if ($isAssoc) {
                    $items[] = $indent . var_export($k, true) . ' => ' . $this->exportPhpCode($v, $path, $indentLevel + 1);
                } else {
                    $items[] = $indent . $this->exportPhpCode($v, $path, $indentLevel + 1);
                }
            }
            return "[\n" . implode(",\n", $items) . "\n" . $closeIndent . "]";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return var_export($value, true);
    }

    /**
     * Build default code for helper-backed env() defaults by removing the absolute root
     * part returned by the helper and keeping only the relative remainder as the helper argument.
     * Example (Windows):
     *  value = "C:\\projects\\draftsmanalpha\\vendor/draftsmaninc/draftsman"
     *  base_path() => "C:\\projects\\draftsmanalpha"
     *  result => base_path('vendor/draftsmaninc/draftsman')
     */
    private function buildHelperDefaultCode(string $helper, $value): string
    {
        // Non-string (e.g., arrays) — just wrap exported code as-is
        if (! is_string($value)) {
            return $helper . '(' . $this->exportPhpCode($value) . ')';
        }

        $root = $this->helperRoot($helper);
        if ($root === '') {
            // Unknown helper; fallback
            return $helper . '(' . $this->exportPhpCode($value) . ')';
        }

        [$normValueLower, $normRootLower] = $this->normalizeForCompare($value, $root);

        if (str_starts_with($normValueLower, $normRootLower)) {
            $relative = substr($normValueLower, strlen($normRootLower));
            // Trim leading separators
            $relative = ltrim($relative, "\\/");
            // Use forward slashes inside helper args for readability (matches vendor style)
            $relative = str_replace(['\\\\', '\\'], '/', $relative);
            if ($relative === '') {
                // Exactly the root path — call helper with no args
                return $helper . '()';
            }
            return $helper . '(' . var_export($relative, true) . ')';
        }

        // Not under the helper root; if it's already a relative path, pass it as-is,
        // else wrap the absolute value (converted to vendor-style forward slashes)
        $arg = $value;
        if ($this->isAbsolutePath($value)) {
            $arg = str_replace(['\\\\', '\\'], '/', $value);
        }
        return $helper . '(' . var_export($arg, true) . ')';
    }

    private function helperRoot(string $helper): string
    {
        try {
            switch ($helper) {
                case 'base_path':
                    return (string) base_path();
                case 'app_path':
                    return (string) app_path();
                case 'storage_path':
                    return (string) storage_path();
                default:
                    return '';
            }
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function normalizeForCompare(string $value, string $root): array
    {
        // Normalize separators to backslash for Windows-aware prefix compare, then lower-case
        $normValue = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value);
        $normRoot = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $root);
        // Ensure root ends with a separator for accurate prefixing
        if (! str_ends_with($normRoot, DIRECTORY_SEPARATOR)) {
            $normRoot .= DIRECTORY_SEPARATOR;
        }
        // On Windows filesystems, treat paths as case-insensitive; lower both sides
        // Use mb_strtolower to be safe
        $lowerValue = function_exists('mb_strtolower') ? mb_strtolower($normValue) : strtolower($normValue);
        $lowerRoot = function_exists('mb_strtolower') ? mb_strtolower($normRoot) : strtolower($normRoot);
        return [$lowerValue, $lowerRoot];
    }

    private function isAbsolutePath(string $p): bool
    {
        // Windows: starts with drive letter or UNC path; POSIX: starts with /
        if (preg_match('/^[A-Za-z]:\\\\|^\\\\\\\\/u', $p) === 1) {
            return true;
        }
        return str_starts_with($p, '/');
    }

    private function pathIsPresentation(array $path): bool
    {
        // True when currently exporting inside the 'presentation' array,
        // so keys of this array represent model classes.
        return (count($path) === 1 && $path[0] === 'presentation');
    }

    /**
     * Resolve a model key (which may be a class string like "App\\Models\\User",
     * a relative name like "User" or "Models\\User") to a fully qualified
     * class name that respects the configured models_path.
     */
    private function resolveModelFqcn(string $modelKey, array $mergedConfig): string
    {
        $appNs = Container::getInstance()->getNamespace(); // e.g., "App\\"
        $appNs = rtrim($appNs, '\\');

        $modelsPath = Arr::get($mergedConfig, 'package.models_path', Arr::get($mergedConfig, 'models_path'));
        $appPath = base_path('app');
        $subNamespace = '';
        if (is_string($modelsPath)) {
            // Normalize separators
            $modelsPathNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $modelsPath);
            $appPathNorm = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $appPath);
            if (str_starts_with($modelsPathNorm, $appPathNorm)) {
                $rel = trim(substr($modelsPathNorm, strlen($appPathNorm)), DIRECTORY_SEPARATOR);
                if ($rel !== '') {
                    $subNamespace = str_replace(DIRECTORY_SEPARATOR, '\\', $rel);
                }
            }
        }

        $key = ltrim(str_replace('/', '\\', $modelKey), '\\');

        // If already fully qualified under app namespace, return as-is
        if (stripos($key, $appNs . '\\') === 0) {
            return $key;
        }

        // If key has namespace but doesn't start with appNs, assume provided FQCN
        if (strpos($key, '\\') !== false && stripos($key, $appNs . '\\') !== 0) {
            return $key;
        }

        // If key is relative (no namespace) or relative under models sub-namespace
        if ($subNamespace !== '') {
            return $appNs . '\\' . $subNamespace . '\\' . $key;
        }

        return $appNs . '\\' . $key;
    }

    private function buildEnvMapFromConfig(array $config): array
    {
        $map = [];

        $addSection = function (string $section, array $values) use (&$map) {
            $iterator = function ($arr, $prefix = '') use (&$map, $section, & $iterator) {
                foreach ($arr as $k => $v) {
                    $keyPart = strtoupper(is_int($k) ? (string) $k : str_replace(['-', '.'], '_', $k));
                    $currentPrefix = $prefix === '' ? $keyPart : $prefix . '_' . $keyPart;
                    if (is_array($v)) {
                        $iterator($v, $currentPrefix);
                    } else {
                        if ($section === 'package') {
                            $envKey = 'DRAFTSMAN_' . $currentPrefix;
                        } else {
                            $envKey = 'DRAFTSMAN_' . strtoupper($section) . '_' . $currentPrefix;
                        }
                        $map[$envKey] = $this->scalarToEnv($v);
                    }
                }
            };
            $iterator($values);
        };

        foreach ($config as $section => $values) {
            if (! is_array($values)) {
                $map['DRAFTSMAN_' . strtoupper($section)] = $this->scalarToEnv($values);
                continue;
            }
            $addSection($section, $values);
        }

        return $map;
    }

    private function scalarToEnv($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }
        return (string) $value;
    }

    private function updateEnvFile(array $updates): void
    {
        $envPath = base_path('.env');
        $lines = File::exists($envPath) ? preg_split("/\r?\n/", File::get($envPath)) : [];
        $existingKeys = [];
        foreach ($lines as $idx => $line) {
            if (preg_match('/^([A-Z0-9_]+)\s*=.*/', $line, $m)) {
                $existingKeys[$m[1]] = $idx;
            }
        }

        foreach ($updates as $k => $v) {
            // Only update keys that start with DRAFTSMAN_
            if (strpos($k, 'DRAFTSMAN_') !== 0) {
                continue;
            }
            // Update only if key already exists; do not append missing keys
            if (array_key_exists($k, $existingKeys)) {
                $entry = $k . '=' . $this->quoteEnvValue($v);
                $lines[$existingKeys[$k]] = $entry;
            }
        }

        File::put($envPath, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    private function quoteEnvValue(string $value): string
    {
        if (preg_match('/\s|#|\\"|\\' . "'" . '/u', $value)) {
            $escaped = str_replace('"', '\\"', $value);
            return '"' . $escaped . '"';
        }
        return $value;
    }
}
