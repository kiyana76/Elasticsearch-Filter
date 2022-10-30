<?php

namespace Kiyana76\ElasticSearchFilter;

use Illuminate\Support\ServiceProvider;

class ElasticsearchFilterServiceProvider extends ServiceProvider
{

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/elasticsearch_filter.php',
            'elastic_filter'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

    }

}