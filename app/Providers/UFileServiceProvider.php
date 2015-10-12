<?php namespace App\Providers;
use App\Services\UFile\UFile;
use Illuminate\Support\ServiceProvider;

class UFileServiceProvider extends ServiceProvider {

    /**
     * Register any application services.
     *
     * @return void
     */

    public function register()
    {
        $this->app->singleton('UFile', function($app)
        {
            return new UFile();
        }); 
    }

}
