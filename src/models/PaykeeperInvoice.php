<?php

namespace akhur0286\paykeeper\models;

use pantera\yii2\pay\sberbank\Module;
use Yii;
use yii\base\InvalidParamException;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use function call_user_func;
use function is_array;

/**
 * This is the model class for table "paykeeper_invoice".
 *
 * @property int $id
 * @property int $related_id
 * @property string $related_model
 * @property string $invoice_id
 * @property int $created_at
 * @property int $paid_at
 * @property array|string $data
 * @property string $url
 */
class PaykeeperInvoice extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'paykeeper_invoice';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['related_id', 'created_at', 'paid_at'], 'integer'],
            [['related_id', 'related_model'], 'required'],
            [['invoice_id'], 'string'],
            [['data'], 'safe'],
            [['url'], 'string', 'max' => 255],
        ];
    }

    public function beforeSave($insert)
    {
        if ($insert) {
            $this->created_at = time();
        }

        if (is_array($this->data) === false) {
            $this->data = [];
        }
        $this->data = Json::encode($this->data);
        return parent::beforeSave($insert);
    }

    public function afterSave($insert, $changedAttributes)
    {
        $this->data = Json::decode($this->data);
        parent::afterSave($insert, $changedAttributes);
    }

    public function afterFind()
    {
        if ($this->data) {
            $this->data = Json::decode($this->data);
        }
        parent::afterFind();
    }

    /**
     * Добавление оплаты через сбербанк
     * @param integer|null $relatedID Идентификатор заказа
     * @param string|null $relatedModel Название модели
     * @param int $invoiceID id заказа на paykeeper
     * @param string $url Ссылка на оплату
     * @param array $data Массив дополнительные данных
     * @return self
     */
    public static function add($relatedID, $relatedModel, $invoiceID, $url, $data = [])
    {
        $model = new self();
        $model->related_id = $relatedID;
        $model->related_model = $relatedModel;
        $model->invoice_id = $invoiceID;
        $model->url = $url;
        $model->data = $data;
        $model->save();
        return $model;
    }

    public static function getInvoiceID($relatedID, $relatedModel)
    {
        $model = self::findOne(['related_id' => $relatedID, 'related_model' => $relatedModel]);
        if ($model) {
            return $model->invoice_id;
        }

        return null;
    }

    /**
     * @return string
     */
    public function getOrderNumber()
    {
        return $this->related_id;
    }
}
