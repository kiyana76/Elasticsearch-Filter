<?php

namespace Kiyana76\ElasticSearchFilter;


use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\PaginatedResourceResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class ElasticsearchPaginate
{
    public $request;

    public function __construct()
    {
        $this->request = app('request');
    }

    public function setPaginationParams($params)
    {
        if (!$this->request->has('page') || !$this->request->has('limit'))
            throw new \LogicException('limit or page params not found in request');

        $page  = $this->request->get('page');
        $limit = $this->request->get('limit');

        $params['size'] = $limit;
        $params['from'] = ($page - 1) * $limit;

        return $params;

    }

    public function addPaginationParamsToResult($data, $total = null, $url = null)
    {
        $page  = $this->request->get('page');
        $limit = $this->request->get('limit');
        $url   = $url ?? $this->request->url();

        if ($total)
            $paginator = new LengthAwarePaginator($data, $total, $limit, $page, ['path' => $url]);
        else
            $paginator = new Paginator($data, $limit, $page, ['path' => $url]);

        return $paginator;

    }

    public function setSearchAfterParams($params): array
    {
        if ($this->request->has('search_after_params'))
            $params['body']['search_after'] = $this->request->get('search_after_params');

        return $params;
    }

    public function setSearchAfterAggregationParams($params, $aggKey): array
    {
        $aggregations = $params['body']['aggs'][$aggKey];
        $terms        = $aggregations['terms'];
        unset($terms['size']); // We should set size in composite
        $aggs                            = $aggregations['aggs'];
        $remake                          = [
            'composite' => [
                'size'    => $params['size'],
                'sources' => [
                    [
                        $aggKey => [
                            'terms' => $terms,
                        ],
                    ],
                ],
            ],
            'aggs'      => $aggs
        ];
        $params['size']                  = 0; // Because we don't need records we need just aggregations
        $params['body']['aggs'][$aggKey] = $remake;
        if ($this->request->has('search_after_params'))
            $params['body']['aggs'][$aggKey]['composite']['after'] = $this->request->get('search_after_params');

        return $params;
    }

    public function setSize($params)
    {

        $limit          = $this->request->get('limit') ?? 15;
        $params['size'] = $limit;

        return $params;
    }

    public function setTotalAggsParam($params, $aggKey): array
    {
        $params['body']['aggs']['total_' . $aggKey] = [
            'cardinality' => [
                'field' => $aggKey,
            ]
        ];
        return $params;
    }

    public function manualPagination($result, $page = 1, $limit = null): array
    {
        if (!$limit)
            $limit = $this->request->limit ?? 15;
        $result = $this->sortResult($result, $this->request->get('filter_order'));
        $offset = ($page - 1) * $limit;
        if( $offset < 0 )
            $offset = 0;

        return array_slice($result, $offset, $limit);
    }
    private function sortResult(array $arr, string $filterCol): array
    {
        $filterCol     = json_decode($filterCol);
        $column        = $filterCol->column;
        $order         = $filterCol->direction == 'ascending' ? 'asc' : 'desc';
        usort($arr, function ($a, $b) use ($column, $order){
            if ($order == 'asc')
                return $a[$column]['value'] - $b[$column]['value'];
            return $b[$column]['value'] - $a[$column]['value'];
        });
        return $arr;
    }

}
