<?php namespace Codesleeve\LaravelStapler;

use Config;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Codesleeve\LaravelStapler\Services\ImageRefreshService;
use Codesleeve\Stapler\Stapler;

class LaravelStaplerServiceProvider extends BaseServiceProvider 
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = false;

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $packageRoot = dirname(__DIR__);

        // config
        $this->publishes([
            $packageRoot . '/config/filesystem.php' => config_path('laravel-stapler/filesystem.php'),
            $packageRoot . '/config/s3.php' => config_path('laravel-stapler/s3.php'),
            $packageRoot . '/config/stapler.php' => config_path('laravel-stapler/stapler.php')
        ]);

        $this->mergeConfigFrom($packageRoot . '/config/filesystem.php', 'laravel-stapler.filesystem');
        $this->mergeConfigFrom($packageRoot . '/config/s3.php', 'laravel-stapler.s3');
        $this->mergeConfigFrom($packageRoot . '/config/stapler.php', 'laravel-stapler.stapler');

        // views
        $this->loadViewsFrom($packageRoot . '/views', 'laravel-stapler');

        $this->bootstrapStapler();
    }

    public function register()
    {
        $this->registerStaplerFastenCommand();
        $this->registerStaplerRefreshCommand();

        // services
        $this->registerImageRefreshService();

        $this->commands('stapler.fasten');
        $this->commands('stapler.refresh');
    }

    /**
     * Bootstrap up the stapler package:
     * - Boot stapler.
     * - Set the config driver.
     * - Set public_path config using laravel's public_path() method (if necessary).
     * - Set base_path config using laravel's base_path() method (if necessary).
     * 
     * @return void
     */
    protected function bootstrapStapler()
    {
        Stapler::boot();

        $config = new IlluminateConfig(Config::getFacadeRoot(), 'laravel-stapler', '.');
        Stapler::setConfigInstance($config);

        if (!$config->get('stapler.public_path')) {
            $config->set('stapler.public_path', realpath(public_path()));
        }

        if (!$config->get('stapler.base_path')) {
            $config->set('stapler.base_path', realpath(base_path()));
        }
    }

    /**
     * Register the stapler fasten command with the container.
     *
     * @return void
     */
    protected function registerStaplerFastenCommand()
    {
        $this->app->bind('stapler.fasten', function($app)
        {
            $migrationsFolderPath = base_path() . '/database/migrations';

            return new FastenCommand($app['view'], $app['files'], $migrationsFolderPath);
        });
    }

    /**
     * Register the stapler refresh command with the container.
     *
     * @return void
     */
    protected function registerStaplerRefreshCommand()
    {
        $this->app->bind('stapler.refresh', function($app)
        {
            $refreshService = $app['ImageRefreshService'];

            return new RefreshCommand($refreshService);
        });
    }

    /**
     * Register the image refresh service with the container.
     * 
     * @return void 
     */
    protected function registerImageRefreshService()
    {
        $this->app->singleton('ImageRefreshService', function($app, $params) {
            return new ImageRefreshService($app);
        });
    }
}
