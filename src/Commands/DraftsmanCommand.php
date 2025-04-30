<?php

namespace Draftsman\Draftsman\Commands;

use Illuminate\Console\Command;

class DraftsmanCommand extends Command
{
    public $signature = 'draftsman';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
