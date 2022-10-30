<?php

namespace Kiyana76\ElasticSearchFilter\Traits;

use Illuminate\Support\Str;

trait ElasticsearchQueryTrait
{
    private $ranges  = [];
    private $must    = [];
    private $mustNot = [];
    private $query   = [];
    private $aggs    = [];
    private $sort    = [];

    public $size;

    // -----------------------------------Setter & Getter -----------------------------
    public function setSize($size)
    {
        $this->size = $size;
    }

    public function setAggregation()
    {
        if (method_exists($this, 'aggs')) {
            $this->aggs = $this->aggs();
        }
        return $this;
    }

    private function setQuery()
    {
        // ------------------------------------ must ------------------------------------
        if (count($this->must)) {
            $this->query['bool']['must'] = $this->must;
        }

        if (count($this->mustNot)) {
            $this->query['bool']['must_not'] = $this->mustNot;
        }

        // ------------------------------------ Ranges ------------------------------------
        if (count($this->ranges)) {
            foreach ($this->ranges as $key => $range) {
                $this->query['bool']['must'][]['range'][$key] = $range;
            }
        }
        return $this;
    }

    private function setSort()
    {
        if (!$this->request->has('filter_order')) {
            return $this;
        }
        $filterCol = json_decode($this->request->get('filter_order'));
        $column    = $filterCol->column;
        $order     = $filterCol->direction;

        $this->sort = [
            $column => [
                'order' => $order == 'ascending' ? 'asc' : 'desc',
            ],

        ];
        if (in_array($column, ['start_at', 'created_at'])) {
            $this->sort[$column]['format']       = 'yyyy-MM-dd HH:mm:ss';
            $this->sort[$column]['numeric_type'] = 'date_nanos';
        }

        return $this;
    }

    private function setParams()
    {
        $this->setQuery();
        $this->setAggregation();
        $this->setSort();

        $params = [
            'index'            => $this->index,
            'track_total_hits' => true
        ];
        if (isset($this->size))
            $params['size'] = $this->size;
        if (count($this->query))
            $params['body']['query'] = $this->query;
        if (count($this->aggs))
            $params['body']['aggs'] = $this->aggs;
        if (count($this->sort))
            $params['body']['sort'] = $this->sort;

        return $params;
    }


    // ------------------------------------ Filter ------------------------------------
    public function whereStartAt($from = NULL, $to = NULL)
    {
        if (!$from && !$to) {
            return;
        }

        $ranges = [
            'start_at' => [],
        ];

        if ($from)
            $ranges['start_at']['gte'] = $from;

        if ($to)
            $ranges['start_at']['lte'] = $to;

        $this->ranges = array_merge($this->ranges, $ranges);

        return $this;
    }

    public function whereCreatedAt($from = NULL, $to = NULL)
    {
        if (!$from && !$to) {
            return;
        }

        $ranges = [
            'created_at' => [],
        ];

        if ($from)
            $ranges['created_at']['gte'] = $from;

        if ($to)
            $ranges['created_at']['lte'] = $to;

        $this->ranges = array_merge($this->ranges, $ranges);

        return $this;
    }

    private function whereId(string $field, $ids)
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $must = [
            [
                "terms" => [
                    $field => $ids,
                ],
            ],
        ];

        $this->must = array_merge($this->must, $must);

        return $this;
    }

    private function whereNotId(string $field, $ids)
    {
        if (!is_array($ids)) {
            $ids = [$ids];
        }

        $mustNot = [
            [
                "terms" => [
                    $field => $ids,
                ],
            ],
        ];

        $this->mustNot = array_merge($this->mustNot, $mustNot);

        return $this;
    }

    // ------------------------------------ magics ------------------------------------
    public function __call($name, $arguments)
    {
        $name = Str::of($name);
        if (substr($name, 0, 8) == 'whereNot') {
            $field = $name->substr(8)->snake();

            $this->whereNotId($field, ...$arguments);
        } elseif (substr($name, 0, 5) == 'where') {
            $field = $name->substr(5)->snake();

            $this->whereId($field, ...$arguments);
        }

        return $this;
    }
}
