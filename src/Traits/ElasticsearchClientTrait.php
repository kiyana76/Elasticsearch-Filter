<?php
namespace Kiyana76\ElasticSearchFilter\Traits;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

trait ElasticsearchClientTrait
{
    public $index;
    public $client;

    public function setIndex($index) {
        $this->index = $index;
    }

    public function getClientInstance() {
        $host = config('database.elastic_search.host');

        if (!isset($this->index)) {
            throw new \LogicException('No elasticsearch index found!');
        }

        $this->client = ClientBuilder::create()->setHosts([$host])->build();


        return $this->client;
    }

}
