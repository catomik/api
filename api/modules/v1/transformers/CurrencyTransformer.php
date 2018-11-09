<?php

namespace backend\modules\api\modules\v1\transformers;


use common\models\Currency;
use common\models\Languages;
use yii\db\ActiveRecord;

class CurrencyTransformer implements TransformerInterface
{

    /**
     * @param ActiveRecord $currency
     * @param array $params
     * @return array
     * @throws \Exception
     */
    public function transform(ActiveRecord $currency, array $params = [])
    {
        if (!($currency instanceof Currency)) {
            throw new \Exception("Entity has to be instance of " . Currency::class);
        }

        if (!isset($params['language']) || !($params['language'] instanceof Languages)) {
            throw new \Exception("Key 'language' is required in params array and has to be instance of " . Languages::class);
        }

        return [
            'code' => $currency->iso,
            'name' => $currency->name,
        ];
    }
}