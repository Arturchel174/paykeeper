<?php

use yii\db\Migration;

/**
 * Class m210615_063719_paykeeper_invoice
 */
class m210615_063719_paykeeper_invoice extends Migration
{
    /**
     * @inheritdoc
     */
    public function up()
    {
        $this->createTable('paykeeper_invoice', [
            'id' => $this->primaryKey(),
            'related_id' => $this->integer()->null(),
            'related_model' => $this->string()->null(),
            'created_at' => $this->integer(),
            'paid_at' => $this->integer(),
            'data' => $this->text(),
            'url' => $this->text(),
            'invoice_id' => $this->string()->null(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function down()
    {
        $this->dropTable('paykeeper_invoice');
    }
}
