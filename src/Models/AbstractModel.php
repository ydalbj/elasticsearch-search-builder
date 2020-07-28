<?php
namespace Wobatu\Repository\Elasticsearch\Models;

abstract class AbstractModel
{
    /**
     * 
     */
    public function getMappingProperties()
    {
        $data = [];

        // example, 具体的索引模型类重写本方法,建立自己的索引映射
        /*
        $data['id'] = ['type' => 'long'];
        $data['item_id'] = ['type' => 'keyword'];
        $data['item_title'] = [
            'type' => 'text',
        ];
        */

        return [];
    }
}