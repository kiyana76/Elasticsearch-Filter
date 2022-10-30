# About

Elasticsearch Filter is package that implement simple search and search with aggregation with pagination and excel for laravel project.

## Installation



```bash
composer require kiyana76/elasticsearch_filter
```

## Usage
Create class that extend .
```php
Kiyana76\ElasticSearchFilter\Elasticsearch
```

You can create API Resource and add to related class to all response implement base on Resource.

You can set index name of elsticsearch in related class.

You can set pagination size in related class.

For aggregations you can add custom aggregations parameters and custom aggregations response in related class.

This class accept $filterOrder parameter for sorting and you can customize setAggregationSort() parameters for customizing sorting in elasticsearch.

You can set $excelPrefixFileName and $excelIgnoreColumns parameters for customizing excel export.
 