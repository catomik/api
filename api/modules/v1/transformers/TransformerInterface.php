<?php

namespace backend\modules\api\modules\v1\transformers;


use yii\db\ActiveRecord;

interface TransformerInterface
{
    /**
     * @param ActiveRecord $model
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function transform(ActiveRecord $model, array $params = []);
}