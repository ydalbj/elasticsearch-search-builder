<?php
namespace Ydalbj\ElasticsearchSearchBuilder;

class ElasticsearchClient
{
    private $clients;

    public function getInstance(string $name)
    {
        if (!isset($this->clients[$name])) {
            $this->clients[$name] = $this->make($name);
        }

        return $this->clients[$name];
    }

    private function make(string $name)
    {
        $hosts = config("elasticsearch.{$name}.hosts");
        if (empty($hosts)) {
            throw new \Exception("elatic {$name} config not exist");
        }
        $builder = \Elasticsearch\ClientBuilder::create()->setHosts($hosts);
        return $builder->build();
    }
}