<?php

namespace Draftsman\Draftsman\Commands;

use Illuminate\Console\Command;

class DraftsmanInstallCommand extends Command
{
    public $signature = 'draftsman:install';

    public $description = 'Installs Draftsman';

    public function handle(): int
    {
        // TODO Add any relevant install methods here.

        $this->info("
 ███████████████████████████████████
 ███████████████████████████████████
 ███                             ███
 ███                             ███
 ███         ███    ███          ███
 ███         ███    ███          ███
 ███         ███    ███          ███
 ███         ███    ███          ███
 ███         ███    ███          ███
 ███         ███    ███          ███
 ███                             ███
 ███                             ███
 ███     ███            ███     ████
 ███     █████        █████     ███
 ░███      ██████   █████      ████
  ████        ████████         ███
   ████         ████         ████
    █████                  █████
      ██████            ██████
         ██████████████████
             ██████████
");

        return self::SUCCESS;
    }
}
