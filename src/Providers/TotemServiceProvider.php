<?php

namespace Studio\Totem\Providers;

use Cron\CronExpression;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use Studio\Totem\Contracts\TaskInterface;
use Studio\Totem\Console\Commands\ListSchedule;
use Studio\Totem\Console\Commands\PublishAssets;
use Studio\Totem\Repositories\EloquentTaskRepository;
use Studio\Totem\SqlSrv\SqlSrvEloquentTaskRepository;

class TotemServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerResources();
        $this->defineAssetPublishing();

        Validator::extend('cron_expression', function ($attribute, $value, $parameters, $validator) {
            return CronExpression::isValidExpression($value);
        });

        Validator::extend('json_file', function ($attribute, UploadedFile $value, $validator) {
            return $value->getClientOriginalExtension() == 'json';
        });
    }

    /**
     * Register any services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/totem.php',
            'totem'
        );

        if (! defined('TOTEM_PATH')) {
            define('TOTEM_PATH', realpath(__DIR__.'/../../'));
        }

        if (! defined('TOTEM_TABLE_PREFIX')) {
            define('TOTEM_TABLE_PREFIX', config('totem.table_prefix'));
        }

        if (! defined('TOTEM_DATABASE_CONNECTION')) {
            define('TOTEM_DATABASE_CONNECTION', config('totem.database_connection', Schema::getConnection()->getName()));
        }

        $this->commands([
            ListSchedule::class,
            PublishAssets::class,
        ]);

        $this->app->bindIf('totem.tasks', $this->getEloquentDriver(), true);
        $this->app->alias('totem.tasks', TaskInterface::class);
        $this->app->register(TotemRouteServiceProvider::class);
        $this->app->register(TotemEventServiceProvider::class);
        $this->app->register(TotemFormServiceProvider::class);
        $this->app->register(ConsoleServiceProvider::class);
    }

    /**
     * Get of the Repository driver.
     *
     * @return void
     */
    protected function getEloquentDriver() {
        $connection = config('database.default');

        $driver = config("database.connections.{$connection}.driver");
        
        if($driver == 'sqlsrv') {
            return SqlSrvEloquentTaskRepository::class;
        }

        return EloquentTaskRepository::class;
    }

    /**
     * Register the Totem resources.
     *
     * @return void
     */
    protected function registerResources()
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'totem');
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'totem');
    }

    /**
     * Define the asset publishing configuration.
     *
     * @return void
     */
    public function defineAssetPublishing()
    {
        $this->publishes([
            TOTEM_PATH.'/public/js' => public_path('vendor/totem/js'),
        ], 'totem-assets');

        $this->publishes([
            TOTEM_PATH.'/public/css' => public_path('vendor/totem/css'),
        ], 'totem-assets');

        $this->publishes([
            TOTEM_PATH.'/public/img' => public_path('vendor/totem/img'),
        ], 'totem-assets');

        $this->publishes([
            TOTEM_PATH.'/resources/views' => resource_path('views/vendor/totem'),
        ], 'totem-views');

        $this->publishes([
            TOTEM_PATH.'/config' => config_path(),
        ], 'totem-config');
    }
}
