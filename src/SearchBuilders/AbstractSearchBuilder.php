<?php
namespace Ydalbj\ElasticsearchSearchBuilder\SearchBuilders;

use Carbon\Carbon;

abstract class AbstractSearchBuilder
{
    /**
     * Elasticsearch请求参数
     * @var array
     */
    protected $params;

    /**
     * 索引名称
     * @var string
     */
    protected $index;

    public function __construct()
    {
    }

    /**
     * 获取请求参数
     */
    public function getParams()
    {
        return $this->params;
    }

    public function getIndex()
    {
        return $this->params['index'];
    }

    public function id(string $id)
    {
        $this->params['id'] = $id;
        return $this;
    }

    public function body(array $body)
    {
        $this->params['body'] = $body;
        return $this;
    }

    public function minScore(int $score)
    {
        $this->params['body']['min_score'] = $score;
        return $this;
    }

    public function trackTotalHits($max)
    {
        $this->params['track_total_hits'] = $max;

        return $this;
    }

    /**
     * 设置查询字段
     * @param array $fields
     * @return AbstractSearchBuilder $this
     */
    public function columns(array $columns)
    {
        $this->params['_source'] = $columns;

        return $this;
    }

    /**
     * 设置routing
     * @param string $routing
     * @return $this
     */
    public function routing(string $routing)
    {
        $this->params['routing'] = $routing;

        return $this;
    }

    /**
     * 添加分页查询
     * @param int $page
     * @param int $size
     * @return AbstractSearchBuilder $this
     */
    public function paginate(int $page, int $size)
    {
        $this->params['from'] = ($page - 1) * $size;
        $this->params['size'] = $size;

        return $this;
    }

    /**
     * 设置排序
     * @param string $field 排序字段
     * @param string $order 升序降序
     * @return AbstractSearchBuilder $this
     */
    public function sort(string $field, string $order = 'asc')
    {
        $this->params['body']['sort'][] = [$field => ['order' => $order]];

        return $this;
    }

    /**
     * 筛选日期范围
     * @param string $start 日期
     * @param string $end 日期
     * @return AbstractSearchBuilder $this
     */
    public function range($start, $end, string $field = 'date')
    {
        $range = $this->getRange($start, $end);
        if (!$range) {
            return $this;
        }

        // 对于时间范围，因为存入的时候没有设置时区，所以查询时也不需指定时区
        // 需要指定时区的范围查询不适用本方法
        $query = [
            'range' => [
                $field => [$range],
            ],
        ];

        return $this->addFilter($query);
    }

    public function term($field, $value)
    {
        $query = [
            'term' => [
                $field => $value
            ],
        ];

        return $this->addFilter($query);
    }

    public function terms($field, $value)
    {
        $query = [
            'terms' => [
                $field => $value
            ],
        ];

        return $this->addFilter($query);
    }

    /**
     * 筛选日期范围
     * @param string $start 日期
     * @param string $end 日期
     * @return AbstractSearchBuilder $this
     * @deprecated 废弃 改用dateRange2方法
     */
    public function dateRange(string $start, string $end, string $field = 'datetime')
    {
        $start = Carbon::parse($start)->toIso8601ZuluString();
        $end = Carbon::parse($end)->toIso8601ZuluString();

        $query = [
            'range' => [
                $field => [
                    'gte' => $start,
                    'lt' => $end,
                    'time_zone' => '+08:00',
                ],
            ],
        ];

        return $this->addFilter($query);
    }

    /**
     * 筛选日期范围
     * @param string $start 日期
     * @param string $end 日期
     * @param string $field
     * @return AbstractSearchBuilder $this
     */
    public function dateRange2($start, $end, string $field)
    {
        if (!$start && !$end) {
            return $this;
        }

        $query = [
            'range' => [
                $field => [],
            ],
        ];

        if ($start) {
            $start = Carbon::parse($start)->toIso8601ZuluString();
            $query['range'][$field]['gte'] = $start;
        }

        if ($end) {
            $end = Carbon::parse($end)->toIso8601ZuluString();
            $query['range'][$field]['lte'] = $end;
        }

        $query['range'][$field]['time_zone'] = '+08:00';

        return $this->addFilter($query);
    }

    public function keywords(string $keywords, string $field, int $score = 4)
    {
        $query = [
            'match' => [
                $field => [
                    'query' => $keywords,
                    'operator' => 'and',
                ],
            ],
        ];

        $this->minScore($score);

        return $this->addMust($query);
    }

    /**
     * 添加BOOL FILTER查询
     * @param array $query
     * @return AbstractSearchBuilder $this
     */
    public function addFilter(array $query)
    {
        if (!isset($this->params['body']['query']['bool']['filter'])) {
            $this->params['body']['query']['bool']['filter'] = [];
        }

        $this->params['body']['query']['bool']['filter'][] = $query;

        return $this;
    }

    /**
     * 添加BOOL MUST查询
     * @param array $query
     * @return AbstractSearchBuilder $this
     */
    public function addMust(array $query)
    {
        if (!isset($this->params['body']['query']['bool']['must'])) {
            $this->params['body']['query']['bool']['must'] = [];
        }

        $this->params['body']['query']['bool']['must'][] = $query;

        return $this;
    }

    /**
     * 添加BOOL MUST NOT查询
     * @param array $query
     * @return AbstractSearchBuilder $this
     */
    public function addMustNot(array $query)
    {
        if (!isset($this->params['body']['query']['bool']['must_not'])) {
            $this->params['body']['query']['bool']['must_not'] = [];
        }

        $this->params['body']['query']['bool']['must_not'][] = $query;

        return $this;
    }

    /**
     * 添加BOOL SHOULD查询
     * @param array $query
     * @return AbstractSearchBuilder $this
     */
    public function addShould(array $query)
    {
        if (!isset($this->params['body']['query']['bool']['should'])) {
            $this->params['body']['query']['bool']['should'] = [];
        }

        $this->params['body']['query']['bool']['should'][] = $query;

        return $this;
    }

    /**
     * 设置aggs参数
     * @param array $aggs 聚合结构体
     * @return $this
     */
    public function addAggregates(array $aggs)
    {
        if (!isset($this->params['body']['aggs'])) {
            $this->params['body']['aggs'] = $aggs;
        } else {
            $this->params['body']['aggs'] += $aggs;
        }

        return $this;
    }

    /**
     * 字段collapse
     * @param string $field
     */
    public function collapse(string $field)
    {
        $this->params['body']['collapse'] = [
            'field' => $field
        ];

        return $this;
    }

    /**
     * 设置aggs参数
     * @param array $aggs 聚合结构体
     * @return $this
     * @deprecated 废弃，改为addAggregates, 与addFilter等一致
     */
    public function setAggs(array $aggs)
    {
        if (!isset($this->params['body']['aggs'])) {
            $this->params['body']['aggs'] = $aggs;
        } else {
            $this->params['body']['aggs'] += $aggs;
        }

        return $this;
    }

    /**
     * 重置query查询
     * @deprecated 本方法废弃，改为reset（因为不知重置query）
     */
    public function resetQuery()
    {
        $this->params['body']['query'] = [];

        return $this;
    }

    /**
     * 重置query查询
     */
    public function reset()
    {
        $index = $this->params['index'];
        $type = $this->params['type'];
        $this->params = [];
        $this->params['index'] = $index;
        $this->params['type'] = $type;

        return $this;
    }

    /**
     * 获取range范围查询参数
     */
    protected function getRange($min = null, $max = null)
    {
        $range = [];
        
        if (isset($min)) {
            $range['gte'] = $min;
        }
        
        if (isset($max)) {
            $range['lte'] = $max;
        }
        
        return $range;
    }

    /********************×××××× 更新操作 ****××××××××××××××××××*/

    public function setUpdateScript(array $query)
    {
        $this->params['body']['script'] = $query;

        return $this;
    }

    /**
     * 更新字段脚本
     */
    public function setFieldUpdateScript(string $column, $value)
    {
        if (!isset($value)) {
            $script = 'ctx._source.remove("' . $column . '")';
            $query = [
                'source' => $script,
            ];
        } else {
            $script = 'ctx._source.' . $column . ' = value';

            $query = [
                'source' => $script,
                'params' => [
                    'value' => $value
                ],
            ];
        }

        $this->setUpdateScript($query);

        return $this;
    }

    /********************×××××× 聚合 ****××××××××××××××××××*/

    protected function aggSum(string $field)
    {
        return [
            'sum' => ['field' => $field],
        ];
    }

    protected function aggAvg(string $field)
    {
        return [
            'avg' => ['field' => $field],
        ];
    }

    protected function aggMax(string $field)
    {
        return [
            'max' => ['field' => $field],
        ];
    }

    protected function aggCardinality(string $field)
    {
        return [
            'cardinality' => ['field' => $field],
        ];
    }

    /**
     * Terms聚合
     */
    protected function aggTerms(string $field, int $size = 10, array $order = ['_key' => 'asc'])
    {
        return [
            'terms' => [
                'field' => $field,
                'size' => $size,
                'order' => $order,
            ],
        ];
    }
}
