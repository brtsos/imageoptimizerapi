<?php

use Phinx\Migration\AbstractMigration;

class AddHistoryTable extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $history = $this->table('history');
        $history->addColumn('userid', 'integer', array('limit' => 20))
                ->addColumn('filename', 'string', array('limit' => 250))
                ->addColumn('directory', 'string', array('limit' => 100))
                ->addColumn('created_at', 'datetime')
                ->addColumn('updated_at', 'datetime')
                ->addIndex(array('userid', 'filename'))
                ->addForeignKey(
                    'userid',
                    'users',
                    'id',
                    array('delete'=> 'CASCADE', 'update'=> 'NO_ACTION'))
                ->create();
    }
}
