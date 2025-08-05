<?php

use yii\db\Migration;

/**
 * Миграция для изменения типа поля related_id в таблице paykeeper_invoice
 */
class m231012_094500_change_related_id_type_in_paykeeper_invoice extends Migration
{
    /**
     * Применяет миграцию
     */
    public function up()
    {
        // Изменяем тип столбца на строку(36)
        $this->alterColumn('paykeeper_invoice', 'related_id', $this->string(36)->null());
    }

    /**
     * Откатывает миграцию
     */
    public function down()
    {
        // Возвращаем исходный тип integer
        $this->alterColumn('paykeeper_invoice', 'related_id', $this->integer()->null());
    }
}