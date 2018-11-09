<?php

namespace backend\modules\api;

/**
 * api module definition class
 */
class api extends \yii\base\Module
{
    /**
     * @inheritdoc
     */
    public $controllerNamespace = 'backend\modules\api\controllers';

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        // custom initialization code goes here

        $this->modules = [
            'v1' => [
                'class' => 'backend\modules\api\modules\v1\v1',
            ],
        ];
    }
}
