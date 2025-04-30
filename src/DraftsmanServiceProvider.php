<?php

namespace Draftsman\Draftsman;

use Draftsman\Draftsman\Commands\DraftsmanCommand;
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
            ->hasRoute('web')
            ->hasCommand(DraftsmanCommand::class);
    }
}
