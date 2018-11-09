<?php

namespace backend\modules\api\modules\v1\controllers;


use backend\modules\api\controllers\ApiBaseController;
use backend\utilities\Payment;
use common\models\Countries;
use common\models\Orders;
use common\models\PaymentGateway;
use common\models\Settings;
use common\models\Transactions;

class PaymentController extends ApiBaseController
{
    /**
     * @return \yii\web\Response
     */
    public function actionGateway()
    {
        if (!\Yii::$app->request->isGet) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $country = \Yii::$app->request->get('country');
        if (is_null($country)) {
            return $this->getBadRequestResponse('Missing argument: country');
        }
        $country = Countries::findOne(['iso' => $country, 'activated' => true]);
        if (is_null($country)) {
            return $this->getBadRequestResponse('Country not found or disabled');
        }

        $payments = PaymentGateway::find()->where(['country' => $country->id, 'status' => true])->orderBy('name')->all();
        
        $data = [];

        foreach ($payments as $payment) {
            $data[] = [
                'gateway_id' => $payment->id,
                'gateway_name' => $payment->name,
                'gateway_logo' => \common\models\Files::getUrl($payment->logo),
                'currency' => $payment->currencies->iso
            ];
        }

//        if (empty($data)) {
//            $data = $this->getFakeGateways();
//        }

        return $this->getSuccessResponse($data);
    }

    /**
     * @return \yii\web\Response
     */
    public function actionMake()
    {
        if (!\Yii::$app->request->isPost) {
            return $this->getBadRequestResponse('Request method is not allowed');
        }

        $body = json_decode(\Yii::$app->request->getRawBody(), true);

        if (!isset($body['order_id'])) {
            return $this->getBadRequestResponse('Missing argument: order_id!');
        }

        if (!isset($body['gateway_id'])) {
            return $this->getBadRequestResponse('Missing argument: gateway_id!');
        }

        $order = Orders::findOne(intval($body['order_id']));
        if(!is_null($order) && $order->payment_status) {
            $transaction = Transactions::findOne(['payment_gateway_id'=>intval($body['gateway_id']),
                'order_id'=>intval($body['order_id']),'status'=>Transactions::STATUS_PAYED]);
            if(!is_null($transaction)){
                $data = [
                    'payment_status' => true,
                    'payment_id' => $transaction->id,
                    'url' => Settings::findOne(['key'=>'base_return_url'])->value.'?code='.$transaction->code
                ];
                return $this->getSuccessResponse($data);
            }
        }

        $transaction = Payment::makePayment($body['order_id'],$body['gateway_id']);

        $data = [
            'payment_status' => is_null($order)?false:($order->payment_status)?true:false,
            'payment_id' => $transaction->id,
            'url' => Payment::getPaymentUrl($transaction)
        ];
        return $this->getCreatedResponse($data);
    }

    /**
     * @return array
     */
    public function getFakeGateways()
    {
        return [
            'gateway_id' => 3,
            'gateway_name' => "LiqPay",
            'gateway_logo' => "https://dle-billing.ru/uploads/logo/liqpay.png",
            'currency' => "USD"
        ];
    }
}