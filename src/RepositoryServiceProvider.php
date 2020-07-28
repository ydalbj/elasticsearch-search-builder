<?php

namespace Ydalbj\ElasticsearchSearchBuilder;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // vendor:publish 生成配置文件
        $config_path = __DIR__ . '/../../config/elasticsearch.php';
        $publish_path = config_path('elasticsearch.php');
        $this->publishes([$config_path => $publish_path], 'elasticsearch');
    }

    public function register()
    {
        $this->app->singleton('es', function () {
            return new ElasticsearchClient();
        });

        // 实例类绑定example
        /*
        $this->app->when(MonologRepository::class)
            ->needs(AbstractSearchBuilder::class)
            ->give(MonologSearchBuilder::class);

        $this->app->when(MonologRepository::class)
            ->needs(AbstractModel::class)
            ->give(Monolog::class);
        */
    }
}
