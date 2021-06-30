Библиотека для приема платежей через интернет для Сбербанк.
===========================================================
Библиотека для приема платежей через интернет для Сбербанк.

Установка с помощью Composer
------------


```
php composer.phar require akhur0286/yii2-paykeeper "*"
```

или добавьте в composer.json

```
"akhur0286/yii2-paykeeper": "*"
```

Подключение компонента
-----

```php
[
    'components' => [
        'paykeeper' => [
            'class' => 'akhur0286\paykeeper\Merchant',
            'merchantLogin' => '',
            'merchantPassword' => '',
            'orderModel' => '', //модель таблицы заказов
        ],
        //..
    ],
];
```

Пример работы библиотеки
-----

```
class PaymentController extends \yii\web\Controller
{
    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'success-payment' => [
                'class' => '\akhur0286\paykeeper\actions\BaseAction',
                'callback' => [$this, 'successCallback'],
            ],
            'error-payment' => [
                'class' => '\akhur0286\paykeeper\actions\BaseAction',
                'callback' => [$this, 'failCallback'],
            ],
        ];
    }

    public function successCallback($orderId)
    {
        /* @var $model PaykeeperInvoice */
        $model = PaykeeperInvoice::findOne(['invoice_id' => $orderId]);
        if (is_null($model)) {
            throw new NotFoundHttpException();
        }

        $merchant = \Yii::$app->get('paykeeper');
        $result = $merchant->checkStatus($orderId);
        //Проверяем статус оплаты если всё хорошо обновим инвойс и редерекним
        if (isset($result['OrderStatus']) && ($result['OrderStatus'] != $merchant->successStatus)) {
            //обработка при успешной оплате $model->related_id номер заказа
            echo 'ok';
        } else {
            $this->redirect($merchant->failUrl.'?orderId=' . $orderId);
        }
    }
    
    public function failCallback($orderId)
    {
        /* @var $model PaykeeperInvoice */
        $model = PaykeeperInvoice::findOne(['invoice_id' => $orderId]);
        if (is_null($model)) {
            throw new NotFoundHttpException();
        }
        //вывод страницы ошибки $model->related_id номер заказа

        echo 'error payment';
    }
}
```

