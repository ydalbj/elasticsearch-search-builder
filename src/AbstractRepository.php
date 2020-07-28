<?php

namespace Ydalbj\ElasticsearchSearchBuilder;

use Ydalbj\ElasticsearchSearchBuilder\SearchBuilders\AbstractSearchBuilder;
use Wobatu\Repository\Elasticsearch\Models\AbstractModel;
use Carbon\Carbon;

abstract class AbstractRepository
{
    /**
     * Elasticsearch Client对象
     */
    protected $client;

    /**
     * Elasticsearch 查询构建对象
     */
    protected $builder;

    /**
     * Elasticsearch模型对象
     */
    protected $model;

    /**
     * 构造函数
     * @param AbstractSearchBuilder $builder
     */
    public function __construct(AbstractSearchBuilder $builder, AbstractModel $model)
    {
        $this->builder = $builder;
        $this->model = $model;

        $index = $this->builder->getIndex();
        
        $this->client = app('es')->getInstance($index);
    }

    /**
     * 分页获取数据
     * @param array $where 查询条件
     * @param array $columns 查询字段
     * @param array $sorts 排序
     * @param int $page
     * @param int $size
     * @return array $paged_data
     */
    public function paginate(
        array $where,
        array $columns = [],
        array $sorts = [],
        int $page = 1,
        int $size = 20,
        $track_total_hits = 20000
    ) {
        $this->builder->paginate($page, $size);
        
        $columns && $this->builder->setColumns($columns);
        $sorts && $this->setSorts($sorts);
        $this->setConditions($where);
        $this->builder->trackTotalHits($track_total_hits);

        $result = $this->search();
        $this->reset();

        return $this->pagedResult($result, $page, $size);
    }

    /**
     * 按条件搜索文档
     * @param array $where 查询条件
     * @param array $columns 查询字段
     * @param array $sorts 排序
     * @return array $list
     */
    public function findWhere(
        array $where,
        array $columns = [],
        array $sorts = [],
        int $page = 1,
        int $size = 10,
        int $max = 20000
    ) {
        $columns && $this->builder->setColumns($columns);
        $sorts && $this->setSorts($sorts);
        $this->setConditions($where);
        $this->builder->trackTotalHits($max);
        $this->builder->paginate($page, $size);

        $result = $this->search();
        $this->reset();

        return $this->result($result);
    }

    /**
     * 设置排序
     * @param array #endregion
     */
    protected function setSorts(array $sorts)
    {
        foreach ($sorts as $sort) {
            // 如果sorts参数不是二维数组（兼容多个排序），则直接对sorts参数可认为是一个排序条件
            // [['datetime' => 'desc']] 和 ['datetime' => 'desc']两种排序结构
            if (!is_array($sort)) {
                $this->builder->sort(key($sorts), current($sorts));
                break;
            }
            $this->builder->sort(key($sort), current($sort));
        }
    }

    /**
     * 执行查询，返回原生结果
     * @return array $result
     */
    public function search()
    {
        # dump(json_encode($this->builder->getParams()['body']));
        return $this->client->search($this->builder->getParams());
    }

    public function update()
    {
        $response = $this->client->updateByQuery($this->builder->getParams());
        $this->reset();
        return $response['updated'];
    }

    public function updateByQuery(array $where, array $script)
    {
        $this->setConditions($where);
        $params = $this->builder->getParams();
        $script['lang'] = 'painless';
        $params['body']['script'] = $script;
        $params['conflicts'] = 'proceed';
        $params['wait_for_completion'] = false;
        $response = $this->client->updateByQuery($params);
        $this->reset();
        return $response;
    }

    public function create(array $data = [])
    {
        $response = $this->client->index($this->builder->getParams());
        $this->reset();
        return $response;
    }

    public function get()
    {
        $response = $this->client->get($this->builder->getParams());
        $this->reset();
        return $response;
    }

    public function bulk(array $data)
    {
        $bulk = [];
        foreach ($data as $k => $row) {
            $bulk['body'][] = [
                'index' => ['_index' => $this->builder->getIndex()],
            ];

            $bulk['body'][] = $row;
        }

        return $this->client->bulk($bulk);
    }

    public function reindex(string $from, string $to)
    {
        $params = [
            'body' => [
                'source' => [
                    'index' => $from,
                ],
                'dest' => [
                    'index' => $to,
                ],
            ],
        ];

        return $this->client->reindex($params);
    }

    public function createMapping(string $index_name = '', array $settings = [])
    {
        $properties = $this->model->getMappingProperties();

        if (!$properties) {
            return null;
        }

        if (!$index_name) {
            $index_name = $this->builder->getIndex();
        }

        $default_settings =[
            'number_of_shards' => 5,
            'number_of_replicas' => 1
        ];

        $settings = array_merge($default_settings, $settings);

        if (!$this->client->indices()->exists(['index' => $index_name])) {
            $params = [
                'index' => $index_name,
                'body' => [
                    'settings' => $settings,
                    'mappings' => [
                        'properties' => $properties,
                    ]
                ],
            ];
            return $this->client->indices()->create($params);
        }

        $params = [
            'index' => $index_name,
            'body' => [
                'properties' => $properties,
            ],
        ];
        return $this->client->indices()->putMapping($params);
    }


    /**
     * 查询数量
     * @param array $where 查询条件
     * @return int $count
     */
    public function count($where, $max = 1000000)
    {
        $this->setConditions($where);
        $max && $this->builder->trackTotalHits($max);
        $ret = $this->client->search($this->builder->paginate(1, 0)->getParams());
        $this->resetQuery();

        if (is_array($ret['hits']['total'])) { // 兼容7.1临时方案
            return $ret['hits']['total']['value'];
        }

        return $ret['hits']['total'];
    }

    /**
     * 从result从获取source
     * @param array $result
     * @return array $list
     */
    public function result(array $result)
    {
        $list = [];
        $hits = $result['hits']['hits'];
        if ($hits) {
            $list = array_column($hits, '_source');
        }

        return $list;
    }

    /**
     * 从result从获取source
     * @param array $result
     * @return array $list
     */
    public function pagedResult(array $result, int $page, int $size)
    {
        // todo, 考虑用scroll参数重写
        /*
        $list = $this->result($result);

        if (is_array($result['hits']['total'])) { // 兼容7.1临时方案
            $total = $result['hits']['total']['value'];
        } else {
            $total = $result['hits']['total'];
        }
        $page_info = Helper::pageInfo($total, $page, $size);

        return array_merge($page_info, ['data' => $list]);
        */
    }

    /**
     * 重置query查询
     */
    public function resetQuery()
    {
        $this->builder->resetQuery();
    }

    public function reset()
    {
        $this->builder->reset();
    }

    /**
     * 从参数获取开始结束日期范围
     * @param array $where
     * @return array $range
     */
    protected function getDateRange(array $where)
    {
        $start = $end = null;
        if (isset($where['range'])) {
            $start = $where['range'][0];
            $end = $where['range'][1];
        } elseif (isset($where['start']) && isset($where['end'])) {
            $start = $where['start'];
            $end = $where['end'];
        } elseif (isset($where['start'])) {
            $start = $where['start'];
            $end = (new Carbon($start))->addDays(30)->toDateString();
        } elseif (isset($where['end'])) {
            $end = $where['end'];
            $start = (new Carbon($end))->subDays(30)->toDateString();
        }

        return [$start, $end];
    }
}
