<?php

namespace backend\modules\api\modules\v1\controllers;


use backend\modules\api\controllers\ApiBaseController;
use common\components\EventEmitter;
use common\events\api\MailBuy;
use common\models\ApiKeys;
use common\models\DeliveryPoints;
use common\models\Languages;
use common\models\Templates;

class MailController extends ApiBaseController
{
    /**
     * @return \yii\web\Response
     */
    public function actionCheckAirport()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $points = DeliveryPoints::findAll(['type' => DeliveryPoints::AIRPORT]);

        $data = array_map(function (DeliveryPoints $point) {
            return [
                'code' => $point->airport_code
            ];
        }, $points);

        return $this->getSuccessResponse($data);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionBuy()
    {
        if (!\Yii::$app->request->isPost) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $body = json_decode(\Yii::$app->request->getRawBody(), true);
        if (!$body) {
            return $this->getBadRequestResponse("Request body has to be valid json string. Error message: '".json_last_error_msg()."'");
        }


        $language_param = isset($body['language']) ? $body['language'] : Languages::getDefault();
        $language = Languages::findOne(['iso' => strtolower($language_param), 'activated' => true]);
        if(is_null($language)){
            return $this->getBadRequestResponse("Language '$language_param' is not activated");
        }
        \Yii::$app->session->set('language',$language->iso);


        if (!isset($body['template_code'])) {
            return $this->getBadRequestResponse("Parameter 'template_code' is required in request body");
        }

        if (!isset($body['airport_to'])) {
            return $this->getBadRequestResponse("Parameter 'airport_to' is required in request body");
        }

        if (!isset($body['tourist_email'])) {
            return $this->getBadRequestResponse("Parameter 'tourist_email' is required in request body");
        }

        if (!isset($body['tourist_name'])) {
            return $this->getBadRequestResponse("Parameter 'tourist_name' is required in request body");
        }

        if (!isset($body['arrival_date'])) {
            return $this->getBadRequestResponse("Parameter 'arrival_date' is required in request body");
        }

        if(!filter_var($body['tourist_email'],FILTER_VALIDATE_EMAIL)) {
            return $this->getBadRequestResponse("Parameter 'tourist_email' is not a valid email. Given '{$body['tourist_email']}'");
        }

        $template = Templates::findOne(['key' => $body['template_code']]);
        if (!$template) {
            return $this->getBadRequestResponse("Template with code '{$body['template_code']}' was not found");
        }

        $point = DeliveryPoints::findOne(['airport_code' => $body['airport_to']]);
        if (!$point) {
            return $this->getBadRequestResponse("There is no delivery points in airport '{$body['airport_to']}'");
        }

        $api_key_str = \Yii::$app->request->headers->get('x-api-key');
        $api_key = ApiKeys::findOne(['key' => $api_key_str]);
        $partner = $api_key->partner;

        $is_demo = isset($body['is_demo']) ? (bool) $body['is_demo'] : false;

        $event = new MailBuy(
            $template, $body['airport_to'], $body['tourist_email'], $body['tourist_name'],
            $body['arrival_date'], $partner, $is_demo
        );

        /** @var EventEmitter $event_emiter */
        $event_emiter = \Yii::$container->get('eventEmitterApi');

        $event_emiter::trigger($event);

        \Yii::$app->session->remove('language');

        return $this->getCreatedResponse([
            'email_id' => $event->getEmailLogId()
        ]);
    }
}