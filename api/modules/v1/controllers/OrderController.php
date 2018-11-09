<?php

namespace backend\modules\api\modules\v1\controllers;


use backend\modules\api\controllers\ApiBaseController;
use backend\utilities\OrderSave;

class OrderController extends ApiBaseController
{
    /**
     * @return \yii\web\Response
     */
    public function actionMake()
    {
        if (!\Yii::$app->request->isPost) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $body = json_decode(\Yii::$app->request->getRawBody(), true);

        if (!isset($body['tariffs']) || empty($body['tariffs'])) {
            return $this->getBadRequestResponse("Parameter 'tariffs' is absent or empty");
        }

        if (!isset($body['tourist_data']) || empty($body['tourist_data'])) {
            return $this->getBadRequestResponse("Parameter 'tourist_data' is absent or empty");
        }

        if (!isset($body['delivery_point_id']) || empty($body['delivery_point_id'])) {
            return $this->getBadRequestResponse("Parameter 'delivery_point_id' is absent or empty");
        }

        try {
            $order = (new OrderSave())->saveWithApi($body);
        }
        catch (\Exception $e)
        {
            return $this->getBadRequestResponse($e->getMessage());
        }

        if (is_array($order))
            return $this->getBadRequestResponse($order['error']);

        $data = [
            'order_id' => $order
        ];

        return $this->getCreatedResponse($data);
    }
}