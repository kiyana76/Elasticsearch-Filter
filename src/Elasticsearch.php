<?php

namespace Kiyana76\ElasticSearchFilter;

use Kiyana76\ElasticSearchFilter\Traits\ElasticsearchClientTrait;
use Kiyana76\ElasticSearchFilter\Traits\ElasticsearchExcelTrait;
use Kiyana76\ElasticSearchFilter\Traits\ElasticsearchQueryTrait;

class Elasticsearch
{
    use ElasticsearchClientTrait, ElasticsearchQueryTrait;
    use ElasticsearchExcelTrait {
        excel as protected traitExcel;
    }

    public $filterOrder = [
        'column'    => 'created_at',
        'direction' => 'ascending'
    ];

    protected $resourceCollection  = null;
    protected $excelIgnoreColumns  = [];
    protected $excelPrefixFileName = 'excel';


    public function __construct()
    {
        static::getClientInstance();
        $this->request = app('request');
    }

    public function search()
    {
        if (!$this->resourceCollection)
            throw new \Exception("resource not found");

        $params = $this->setParams();
        $elasticResult = $this->client->search($params);
        if (method_exists($this, 'aggs'))
            $result = $this->prepareAggsResult($elasticResult);
        else
            $result = $this->prepareResult($elasticResult);

        if (method_exists($this, 'aggsResponse'))
            $result = $this->aggsResponse($result);

        return $this->resourceCollection::collection($result);
    }

    public function pagination()
    {
        if (!$this->resourceCollection)
            throw new \Exception("resource not found");

        $paginationClass = new ElasticsearchPaginate();

        $params = $this->setParams();
        $params = $paginationClass->setPaginationParams($params);

        $elasticResult = $this->client->search($params);

        $totalCount = $elasticResult['hits']['total']['value'];
        $result     = $this->prepareResult($elasticResult);

        $result = $paginationClass->addPaginationParamsToResult($result, $totalCount);
        return $this->resourceCollection::collection($result);
    }

    public function searchAfterAggregationPagination($agg)
    {
        if (!$this->resourceCollection)
            throw new \Exception("resource not found");

        $paginationClass = new ElasticsearchPaginate();

        $params = $this->setParams();
        $params = $paginationClass->setSize($params);
        $params = $paginationClass->setSearchAfterAggregationParams($params, $agg);
        $params = $paginationClass->setTotalAggsParam($params, $agg);
        unset($params['body']['sort']); //sort of aggregation most be implemented in inherent class
        $elasticResult = $this->client->search($params);

        $result            = $this->prepareAggsResult($elasticResult);
        $searchAfterParam  = $this->getSearchAfterAggregationParam($result, $agg);
        //$searchBeforeParam = $this->getSearchBeforeAggregationParam($params); can't find solution
        $totalCount        = $result['total_' . $agg]['value'];
        $size              = $params['body']['aggs'][$agg]['composite']['size'];

        if (method_exists($this,'aggsResponse'))
            $result = $this->aggsResponse($result);

        return $this->resourceCollection::collection($result)->additional([
            'links' => [
                'next'  => $this->getNextPageUrl($searchAfterParam, $totalCount, $size),
                'prev'  => '',
                'first' => '',
                'last'  => '',
            ],
            'meta'  => [
                'current_page' => $this->request->get('page') ?? 1,
                'from'         => '',
                'last_page'    => '',
                'path'         => $this->request->url(),
                'pre_page'     => $params['size'],
                'to'           => '',
                'total'        => $totalCount
            ]
        ]);


    }

    public function searchAfterPagination()
    {
        if (!$this->resourceCollection)
            throw new \Exception("resource not found");
        if (!$this->request->has('filter_order')) {
            $jsonFilter = json_encode($this->filterOrder);
            $this->request->request->add(['filter_order' => $jsonFilter]);
        }

        $paginationClass = new ElasticsearchPaginate();

        $params = $this->setParams();
        $params = $paginationClass->setSize($params);
        $params = $paginationClass->setSearchAfterParams($params);

        $elasticResult = $this->client->search($params);

        $totalCount        = $elasticResult['hits']['total']['value'];
        $result            = $this->prepareResult($elasticResult);
        $searchAfterParam  = $this->getSearchAfterParam($elasticResult);
        $searchBeforeParam = $this->getSearchBeforeParam($params);

        return $this->resourceCollection::collection($result)->additional([
            'links' => [
                'next'  => $this->getNextPageUrl($searchAfterParam, $totalCount, $params['size']),
                'prev'  => $this->getBeforePageUrl($searchBeforeParam),
                'first' => '',
                'last'  => '',
            ],
            'meta'  => [
                'current_page' => $this->request->get('page') ?? 1,
                'from'         => '',
                'last_page'    => '',
                'path'         => $this->request->url(),
                'pre_page'     => $params['size'],
                'to'           => '',
                'total'        => $totalCount
            ]
        ]);
    }

    public function searchManualPagination($agg) {
        if (!$this->resourceCollection)
            throw new \Exception("resource not found");
        if (!$this->request->has('filter_order')) {
            $jsonFilter = json_encode($this->filterOrder);
            $this->request->request->add(['filter_order' => $jsonFilter]);
        }

        $params = $this->setParams();
        $elasticResult = $this->client->search($params);
        if (method_exists($this, 'aggs'))
            $result = $this->prepareAggsResult($elasticResult);
        else
            $result = $this->prepareResult($elasticResult);

        $beforePaginate = $result[$agg]['buckets'];
        $total      = count($beforePaginate);
        $url        = $this->removeSearchAfterAndPageParamFromUrl($this->request->fullUrl());

        $paginationClass = new ElasticsearchPaginate();


        $result[$agg]['buckets'] = $paginationClass->manualPagination(
            $beforePaginate,
            $this->request->get('page') ?? 1,
            $this->request->get('limit') ?? $this->size
        );;


        if (method_exists($this,'aggsResponse'))
            $result = $this->aggsResponse($result);

        $result = $paginationClass->addPaginationParamsToResult($result, $total, $url);

        return $this->resourceCollection::collection($result);

    }


    public function excel()
    {
        if (!$this->resourceCollection)
            throw new \Exception("resource not found");

        $params = $this->setParams();


        if (!method_exists($this, 'aggs'))
            return $this->traitExcel($params);
        else
            return $this->aggsExcel($this->search());

    }

    private function prepareResult($elasticResults): array
    {
        $result = [];
        foreach ($elasticResults['hits']['hits'] as $elasticResult) {
            $result[] = $elasticResult['_source'];
        }

        return $result;
    }

    private function prepareAggsResult($elasticResults): array
    {
        return $elasticResults['aggregations'];
    }

    private function getSearchAfterParam($elasticResults)
    {
        $total = count($elasticResults['hits']['hits']);
        return $elasticResults['hits']['hits'][$total - 1]['sort'] ?? null; //last hit res
    }

    private function getSearchBeforeParam($params)
    {
        // We must reverse the order of search and get search after of the last result
        $sort = $params['body']['sort'];
        foreach ($sort as $key => $value) {
            $sort[$key] = $value['order'] == 'asc' ? 'desc' : 'asc';
        }
        $params['body']['sort'] = $sort;

        $elasticRes = $this->client->search($params);
        $total      = count($elasticRes['hits']['hits']);
        return $elasticRes['hits']['hits'][$total - 1]['sort'] ?? null;

    }

    private function getSearchAfterAggregationParam($elasticResults, $agg) {
        return $elasticResults[$agg]['after_key'] ?? '';
    }

    private function getSearchBeforeAggregationParam($params) {
        $pathKeyString = $this->in_array_multi('mySort', $params);
        $paths = explode("*", $pathKeyString);
        $evalString = '$params';
        foreach ($paths as $path) {
            $evalString .= "['$path']";
        }
        $mySort = eval('return' . $evalString . ";");
        foreach ($mySort['bucket_sort']['sort'] as $key => $value) {
            $mySort['bucket_sort']['sort'][$key]['order'] = $value['order'] == 'asc' ? 'desc' : 'asc';
        }
        eval($evalString . "=\$mySort;");

        $elasticRes = $this->client->search($params);


    }

    public function getNextPageUrl($searchAfterParam, $totalCount, $size): string
    {
        $nextPage = $this->request->get('page') ? $this->request->get('page') + 1 : 2;
        $url      = $this->removeSearchAfterAndPageParamFromUrl($this->request->fullUrl());

        if (ceil($totalCount / $size) < $nextPage)
            return '';

        $queryParam = [
            'search_after_params' => $searchAfterParam,
            'page'                => $nextPage,
        ];

        return $this->addQueryParamToUrl($url, $queryParam);
    }

    public function getBeforePageUrl($searchBeforeParam): string
    {
        $queryParam = [
            'search_after_params' => $searchBeforeParam,
            'page'                => $this->request->get('page') ? $this->request->get('page') - 1 : null,
        ];

        $currentPage = $this->request->get('page') ?? 1;
        $url      = $this->removeSearchAfterAndPageParamFromUrl($this->request->fullUrl());
        if ($currentPage == 1)
            return '';
        if ($currentPage == 2)
            return $url;
        else
            return $this->addQueryParamToUrl($url, $queryParam);
    }

    // This function searches for needle inside multidimensional array haystack
    // Returns the path to the found element or false
    public function in_array_multi($needle, array $haystack)
    {
        if (!is_array($haystack)) return false;

        foreach ($haystack as $key => $value) {
            if ($key === $needle) {
                return $key;
            } else if (is_array($value)) {
                // multi search
                $key_result = $this->in_array_multi($needle, $value);
                if ($key_result !== false) {
                    return $key . '*' . $key_result;
                }
            }
        }

        return false;
    }

    public function removeSearchAfterAndPageParamFromUrl($fullUrl) {
        $parsedUrl = parse_url($fullUrl);
        if (!isset($parsedUrl['query']))
            return $fullUrl;

        $queries = [];
        parse_str($parsedUrl['query'], $queries);
        unset($queries['search_after_params']);
        unset($queries['page']);

        $parsedUrl['query'] = http_build_query($queries);

        return $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . $parsedUrl['path'] . '?' . $parsedUrl['query'];
    }

    public function addQueryParamToUrl($url, array $queryParam): string
    {
        $queryParam = http_build_query($queryParam);
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['query']))
            return $url . '?' . $queryParam;
        return $url . '&' . $queryParam;
    }


}
