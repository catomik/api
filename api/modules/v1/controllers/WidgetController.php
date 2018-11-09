<?php

namespace backend\modules\api\modules\v1\controllers;


use backend\modules\api\controllers\ApiBaseController;
use common\models\ApiTranslation;
use common\models\Languages;
use yii\helpers\ArrayHelper;

class WidgetController extends ApiBaseController
{
    /**
     * @return \yii\web\Response
     */
    public function actionTranslation()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $language_param = \Yii::$app->request->get('language');
        if(is_null($language_param)){
            return $this->getBadRequestResponse('Missing argument: language!');
        }

        $language = Languages::findOne(['iso' => strtolower($language_param), 'activated' => true]);
        if(is_null($language)){
            return $this->getBadRequestResponse("Language '$language_param' is not activated");
        }

        \Yii::$app->session->set('language', $language->iso);

        $models = ApiTranslation::find()->translate($language->iso)->all();
        $items = ArrayHelper::map($models, 'code', 'value');

        $data = [
            'language' => $language_param,
            'translations' => $items
        ];

        \Yii::$app->session->remove('language');

        return $this->getSuccessResponse($data);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionStatistic()
    {
        if (!\Yii::$app->request->isPost) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $body = json_decode(\Yii::$app->request->getRawBody(), true);

        if(!isset($body['partner_code'])){
            return $this->getBadRequestResponse('Missing argument: partner_code!');
        }

        if(!isset($body['widget_id'])){
            return $this->getBadRequestResponse('Missing argument: widget_id!');
        }

        return $this->getCreatedResponse();
    }
}