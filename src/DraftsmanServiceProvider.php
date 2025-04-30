<?php

namespace Draftsman\Draftsman;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Draftsman\Draftsman\Commands\DraftsmanCommand;

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
