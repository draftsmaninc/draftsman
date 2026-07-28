<?php

namespace Draftsman\Draftsman\Actions;

use Illuminate\Support\Facades\Process;

/**
 * Lists files with uncommitted git changes (vs HEAD) in the host app —
 * modified, added, renamed, untracked or deleted — as ABSOLUTE paths mapped
 * to a coarse status ('modified' | 'added' | 'deleted'), so callers can match
 * them against model file locations (getModels flags matching entries
 * `changed: true`, git-new files additionally `created: true`, and the
 * models/changed endpoint surfaces the deleted ones).
 *
 * Capability-gated like Browsershot: available() is false when the host isn't
 * a git work tree (or git isn't runnable), the config endpoint reports it
 * under capabilities.git, and no flags are emitted.
 *
 * git prints paths relative to the repo TOPLEVEL, which is not always
 * base_path() (monorepos keep .git above the app) — hence rev-parse
 * --show-toplevel rather than resolving against base_path directly.
 */
class ChangedFiles
{
    public function available(): bool
    {
        $result = Process::path(base_path())->run(['git', 'rev-parse', '--is-inside-work-tree']);

        return $result->successful() && trim($result->output()) === 'true';
    }

    /** @return array<string, string> absolute path => 'modified'|'added'|'deleted' */
    public function handle(): array
    {
        $toplevel = Process::path(base_path())->run(['git', 'rev-parse', '--show-toplevel']);
        $status = Process::path(base_path())->run(['git', 'status', '--porcelain']);
        if (! $toplevel->successful() || ! $status->successful()) {
            return [];
        }

        $root = trim($toplevel->output());
        $root = realpath($root) ?: $root;
        $changed = [];
        // rtrim, NOT trim: porcelain lines START with the two status columns,
        // and an unmodified-in-index file's first column is a space (" M …") —
        // a full trim eats it and shifts every offset on the first line.
        foreach (preg_split('/\r?\n/', rtrim($status->output())) as $line) {
            if ($line === '' || strlen($line) < 4) {
                continue;
            }
            $xy = substr($line, 0, 2);
            $path = substr($line, 3);
            // Renames print "R  old -> new": the NEW path is the live (added)
            // file, the OLD path is gone (deleted).
            if (str_contains($path, ' -> ')) {
                $old = substr($path, 0, strpos($path, ' -> '));
                $changed[$this->absolutize($root, trim($old, '"'))] = 'deleted';
                $path = substr($path, strpos($path, ' -> ') + 4);
                $xy = 'A ';
            }
            // git quotes paths containing exotic characters
            $path = trim($path, '"');
            $changed[$this->absolutize($root, $path)] = match (true) {
                str_contains($xy, 'D') => 'deleted',
                $xy === '??', str_contains($xy, 'A') => 'added',
                default => 'modified',
            };
        }

        return $changed;
    }

    /** Deleted files can't realpath (they're gone) — fall back to plain
     *  separator-normalized joining against the (real) toplevel. */
    private function absolutize(string $root, string $path): string
    {
        $joined = $root.DIRECTORY_SEPARATOR.strtr($path, '/', DIRECTORY_SEPARATOR);

        return realpath($joined) ?: $joined;
    }
}
