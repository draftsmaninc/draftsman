<?php

namespace Draftsman\Draftsman;

use Draftsman\Draftsman\Commands\DraftsmanInstallCommand;
use Draftsman\Draftsman\Commands\DraftsmanRenderCommand;
use Draftsman\Draftsman\Commands\DraftsmanSnapshotCommand;
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
            ->hasRoute('web')
            ->hasCommands([
                DraftsmanInstallCommand::class,
                DraftsmanRenderCommand::class,
                DraftsmanSnapshotCommand::class,
            ]);
    }

    public function packageBooted(): void
    {
        // The render template is internal: registered straight on the view
        // factory (not hasViews()) so there is no draftsman-views publish tag
        // and no views/vendor/draftsman override path. Rendered output must
        // match what the UI saved — a stale host copy would silently win.
        $this->app['view']->addNamespace('draftsman', __DIR__.'/../resources/views');
    }
}
