<?php

namespace DraftsmanInc\Draftsman;

use DraftsmanInc\Draftsman\Commands\DraftsmanCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DraftsmanServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('draftsman')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_draftsman_table')
            ->hasCommand(DraftsmanCommand::class);
    }
}
