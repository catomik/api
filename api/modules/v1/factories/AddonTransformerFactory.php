<?php

namespace backend\modules\api\modules\v1\factories;


use backend\modules\api\modules\v1\transformers\AddonTransformer;
use common\models\Currency;
use common\models\Languages;
use common\utilities\plan_services\Calls;
use common\utilities\plan_services\Internet;

class AddonTransformerFactory
{
    /**
     * @param array $params
     * @return AddonTransformer
     */
    public function create(array $params = [])
    {
        if (!isset($params['language']) || !($params['language'] instanceof Languages)) {
            $params['language'] = Languages::find()->where(['iso' => Languages::getDefault()])->one();
        }

        if (!isset($params['currency']) || !($params['currency'] instanceof Currency)) {
            $params['currency'] = Currency::find()->where(['iso' => Currency::getDefault()])->one();
        }

        $transformer = new AddonTransformer($params['language'], $params['currency']);

        if (
            isset($params['internet_format']) && ($params['internet_format'] instanceof Internet)
        ) {
            $transformer->setInternetFormat($params['internet_format']);
        }

        if (
            isset($params['calls_format']) && ($params['calls_format'] instanceof Calls)
        ) {
            $transformer->setCallsFormat($params['calls_format']);
        }

        return $transformer;
    }
}