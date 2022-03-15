<?php

namespace KagaDorapeko\Laravel\Alipay;

use Illuminate\Support\ServiceProvider;

class AlipayServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/alipay.php', 'alipay');

        $this->app->singleton(AlipayService::class, function () {
            return new AlipayService;
        });
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/alipay.php' => config_path('alipay.php'),
            ], 'laravel-alipay-config');
        }
    }
}