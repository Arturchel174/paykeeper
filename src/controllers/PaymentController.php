<?php

namespace akhur0286\alfapay\controllers;

use akhur0286\paykeeper\models\PaykeeperInvoice;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class PaymentController extends \yii\web\Controller
{
    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'result-payment' => [
                'class' => '\akhur0286\alfapay\actions\BaseAction',
                'callback' => [$this, 'resultCallback'],
            ],
            'error-payment' => [
                'class' => '\akhur0286\alfapay\actions\BaseAction',
                'callback' => [$this, 'failCallback'],
            ],
        ];
    }

    public function resultCallback($orderId)
    {
        /* @var $model PaykeeperInvoice */
        $model = PaykeeperInvoice::findOne(['orderId' => $orderId]);
        if (is_null($model)) {
            throw new NotFoundHttpException();
        }

        $merchant = \Yii::$app->get('alfapay');
        $result = $merchant->checkStatus($orderId);
        //Проверяем статус оплаты если всё хорошо обновим инвойс и редерекним
        if (isset($result['OrderStatus']) && ($result['OrderStatus'] == $merchant->successStatus)) {
            //обработка при успешной оплате $model->related_id номер заказа
            echo 'ok';
        } else {
            $this->redirect($merchant->failUrl.'?orderId=' . $orderId);
        }
    }

    public function failCallback($orderId)
    {
        /* @var $model PaykeeperInvoice */
        $model = PaykeeperInvoice::findOne(['orderId' => $orderId]);
        if (is_null($model)) {
            throw new NotFoundHttpException();
        }
        //вывод страницы ошибки $model->related_id номер заказа

        echo 'error payment';
    }
}
