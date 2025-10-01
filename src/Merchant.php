<?php

namespace akhur0286\paykeeper;

use akhur0286\paykeeper\models\PaykeeperInvoice;
use Yii;
use yii\base\BaseObject;
use yii\base\ErrorException;
use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Response;
use yii\helpers\StringHelper;

class Merchant extends BaseObject
{
    public $merchantLogin;

    public $merchantPassword;

    /**
     * @var string Адрес платежного шлюза
     */
    public $serverUrl;

    public $orderModel;

    /**
     * Создание оплаты редеректим в шлюз сберабнка
     * @param $orderID - id заказа
     * @param $sum - сумма заказа
     * @param null $additionalData - доп данные(email, телефон и тд)
     * @return mixed
     */
    public function create($orderID, $sum, $additionalData = null)
    {
        $relatedModel = $this->getRelatedModel();

        $invoice = PaykeeperInvoice::findOne(['related_id' => $orderID, 'related_model' => $relatedModel]);

        if ($invoice) {
            return $invoice->url;
        }

        $data = [
            'orderid' => $orderID,
            'pay_amount' => $sum,
            'service_name' => '',
        ];
        if (isset($additionalData['service_name'])) {
            $data['service_name'] = $additionalData['service_name'];
        }
        if (isset($additionalData['clientid'])) {
            $data['clientid'] = $additionalData['clientid'];
        }
        if (isset($additionalData['email'])) {
            $data['client_email'] = $additionalData['email'];
        }
        if (isset($additionalData['phone'])) {
            $data['client_phone'] = $additionalData['phone'];
            $data['phone'] = $additionalData['phone'];
        }

        $response = $this->sendGet('/info/settings/token/', $data);

        if (!$response || !isset($response['token'])) {
            throw new ErrorException('Возникла ошибка при оплате');
        }

        $data['token'] = $response['token'];

        $response = $this->sendPost( '/change/invoice/preview/', $data);

        if (!$response || !isset($response['invoice_id'])) {
            throw new ErrorException('Возникла ошибка при оплате');
        }

        $invoiceID = $response['invoice_id'];
        $formUrl = $this->serverUrl . '/bill/' . $invoiceID . '/';;

        PaykeeperInvoice::add($orderID, $this->relatedModel, $invoiceID, $formUrl, $data);

        return $formUrl;
    }

    /**
     * @throws ErrorException
     */
    public function revoke(string $invoice_id)
    {
        $response = $this->sendGet('/info/settings/token/', []);

        if (!$response || !isset($response['token'])) {
            throw new ErrorException('Возникла ошибка при получении токена');
        }

        $data['token'] = $response['token'];
        $data['id'] = $invoice_id;

        $response = $this->sendPost( '/change/invoice/revoke/', $data);

        if (!$response || !isset($response['result'])) {
            throw new ErrorException('Возникла ошибка при отмене');
        }
        
        if($response['result'] === 'success'){
            return PaykeeperInvoice::del($invoice_id);
        }

        return false;
    }

    /**
     * Получает данные счета по его ID в системе PayKeeper.
     * @see https://docs.paykeeper.ru/dokumentatsiya-json-api/scheta/#zapros-polucheniya-dannykh-schyota-infoinvoicebyid
     *
     * @param string $invoice_id ID счета в системе PayKeeper.
     * @return array|null Данные счета или null в случае ошибки.
     * @throws ErrorException
     */
    public function getInvoiceById(string $invoice_id)
    {
        $response = $this->sendGet('/info/settings/token/', []);

        if (!$response || !isset($response['token'])) {
            throw new ErrorException('Возникла ошибка при получении токена');
        }

        $data['token'] = $response['token'];
        $data['id'] = $invoice_id;

        $action = '/info/invoice/byid/';

        $response = $this->sendGet( $action, $data);

        // API возвращает массив с одним элементом или пустой массив
        if (is_array($response)) {
            return $response[0] ?? null;
        }

        if (isset($response['msg'])) {
            throw new ErrorException('Ошибка при получении данных счета: ' . $response['msg']);
        }

        return null;
    }

    /**
     * Получает реестр платежей за указанный период с возможностью фильтрации.
     * @see https://docs.paykeeper.ru/dokumentatsiya-json-api/platezhi/#zapros-polucheniya-reestra-platezhey-infopaymentsbydate
     *
     * @param string $startDate Дата начала периода в формате YYYY-MM-DD.
     * @param string $endDate Дата конца периода в формате YYYY-MM-DD.
     * @param array $options Дополнительные параметры фильтрации.
     *   - 'payment_system_id' (int): Идентификатор платежной системы (обязательный, если не указан глобально).
     *   - 'status' (array): Массив статусов для фильтрации (например, ['obtained', 'success']).
     *   - 'from' (int): Пропустить N значений.
     *   - 'limit' (int): Количество возвращаемых значений.
     * @return array|null Массив платежей или null в случае ошибки.
     * @throws ErrorException
     */
    public function getPaymentsByDate(string $startDate, string $endDate, array $options = [])
    {
        $action = '/info/payments/bydate/';

        // Подготовка обязательных параметров
        $data = [
            'start' => $startDate,
            'end' => $endDate,
        ];

        // Добавление опциональных параметров
        if (isset($options['payment_system_id'])) {
            $data['payment_system_id'] = $options['payment_system_id'];
        }

        if (isset($options['status']) && is_array($options['status'])) {
            // PayKeeper ожидает параметры status[]
            $data['status'] = $options['status'];
        }

        if (isset($options['from'])) {
            $data['from'] = (int)$options['from'];
        }

        if (isset($options['limit'])) {
            $data['limit'] = (int)$options['limit'];
        }

        // Для GET запроса с массивом статусов, http_build_query сам создаст правильные ключи status[]
        $queryString = http_build_query($data);

        // Используем метод sendGet, но модифицируем его для передачи параметров в URL
        $base64 = base64_encode($this->merchantLogin . ':' . $this->merchantPassword);
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $base64,
        ];

        $url = $this->serverUrl . $action . '?' . $queryString;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $out = curl_exec($curl);
        curl_close($curl);

        $response = Json::decode($out);

        // Простая проверка на ошибку
        if (isset($response['msg'])) {
            throw new ErrorException('Ошибка при получении реестра платежей: ' . $response['msg']);
        }

        return $response;
    }

    /**
     * Откправка запроса в api сбербанка
     * @param $action string типа запрос
     * @param $data array Параметры которые передаём в запрос
     * @return mixed Ответ сбербанка
     */
    public function sendGet($action, $data)
    {
        $authData = [
            'userName' => $this->merchantLogin,
            'password' => $this->merchantPassword,
        ];
        $data = array_merge($authData,$data);

        $url = $this->serverUrl . $action;

        $base64 = base64_encode($this->merchantLogin . ':' . $this->merchantPassword);
        $headers = [];
        array_push($headers, 'Content-Type: application/x-www-form-urlencoded');
        array_push($headers, 'Authorization: Basic ' . $base64);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $out = curl_exec($curl);

        return Json::decode($out);
    }

    public function sendPost($action, $data)
    {
        $base64 = base64_encode($this->merchantLogin . ':' . $this->merchantPassword);
        $headers = [];
        array_push($headers, 'Content-Type: application/x-www-form-urlencoded');
        array_push($headers, 'Authorization: Basic ' . $base64);

        $curl = curl_init();
        $request = http_build_query($data);

        curl_setopt($curl, CURLOPT_URL, $this->serverUrl . $action);
        curl_setopt($curl, CURLOPT_HTTPHEADER , $headers);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $request);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        $response = curl_exec($curl);
        curl_close($curl);

        return Json::decode($response);
    }

    public function getRelatedModel()
    {
        return strtolower(StringHelper::basename($this->orderModel));
    }
}
