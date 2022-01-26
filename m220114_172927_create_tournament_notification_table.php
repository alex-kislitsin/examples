<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%tournament_notification}}`.
 */
class m220114_172927_create_tournament_notification_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->createTable('{{%tournament_notification}}', [
            'id' => $this->primaryKey(),
            'tournament_id' => $this->integer()->notNull()->comment('Турнир'),
            'user_id' => $this->integer()->notNull()->comment('Пользователь'),
            'status' => $this->smallInteger()->notNull()->defaultValue(0)->comment('Статус'),
            'text' => $this->text()->comment('Текст сообщения'),
            'subject' => $this->string()->comment('Тема сообщения'),
            'created_at' => $this->date()->comment('Дата создания'),
        ]);

        $this->addForeignKey(
            'fk_tournament_notification_tournament_id',
            '{{%tournament_notification}}',
            'tournament_id',
            '{{%tournaments}}',
            'id',
            'cascade',
            'cascade'
        );

        $this->addForeignKey(
            'fk_tournament_notification_user_id',
            '{{%tournament_notification}}',
            'user_id',
            'user',
            'id',
            'cascade',
            'cascade'
        );

        $this->createIndex('idx_tournament_notification_status','tournament_notification','status');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        $this->dropForeignKey('fk_tournament_notification_tournament_id','{{%tournament_notification}}');
        $this->dropForeignKey('fk_tournament_notification_user_id','{{%tournament_notification}}');
        $this->dropTable('{{%tournament_notification}}');
    }
}
