<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class CreateAgroperiClosures extends Migration
{
    public function up()
    {
        $opts = $this->db->getPlatform() === 'MySQLi' ? ['ENGINE'=>'InnoDB'] : [];
        $id=['type'=>'INT','unsigned'=>true]; $money=['type'=>'BIGINT','unsigned'=>true,'default'=>0]; $date=['type'=>'DATETIME'];
        $this->forge->addColumn('agri_teams', ['manager_user_id'=>$id+['null'=>true]]);
        $this->forge->addColumn('agri_work_logs', ['closure_hash'=>['type'=>'CHAR','constraint'=>64,'null'=>true], 'closed_at'=>$date+['null'=>true], 'closed_by'=>$id+['null'=>true]]);
        $this->forge->addColumn('agri_work_log_attendees', ['pool_key'=>['type'=>'VARCHAR','constraint'=>32,'null'=>true]]);
        $this->forge->addField(['work_log_id'=>$id,'pool_key'=>['type'=>'VARCHAR','constraint'=>32], 'name'=>['type'=>'VARCHAR','constraint'=>100], 'quantity'=>['type'=>'DECIMAL','constraint'=>'12,4']]);
        $this->forge->addPrimaryKey(['work_log_id','pool_key']); $this->forge->addForeignKey('work_log_id','agri_work_logs','id','RESTRICT','RESTRICT'); $this->forge->createTable('agri_work_pools',false,$opts);
        $this->forge->addField(['id'=>$id+['auto_increment'=>true], 'work_log_id'=>$id,'worker_id'=>$id,'price_id'=>$id, 'pool_key'=>['type'=>'VARCHAR','constraint'=>32,'null'=>true], 'quantity'=>['type'=>'DECIMAL','constraint'=>'12,4','null'=>true], 'unit_name'=>['type'=>'VARCHAR','constraint'=>160], 'rate'=>['type'=>'DECIMAL','constraint'=>'12,4'], 'amount_cents'=>$money, 'created_at'=>$date]);
        $this->forge->addPrimaryKey('id'); $this->forge->addUniqueKey(['work_log_id','worker_id']);
        foreach(['work_log_id'=>'agri_work_logs','worker_id'=>'agri_workers','price_id'=>'agri_prices'] as $field=>$table) $this->forge->addForeignKey($field,$table,'id','RESTRICT','RESTRICT');
        $this->forge->createTable('agri_earnings',false,$opts);
        $this->forge->addField(['id'=>$id+['auto_increment'=>true], 'worker_id'=>$id, 'date_from'=>['type'=>'DATE'],'date_to'=>['type'=>'DATE'], 'total_cents'=>$money,'paid_cents'=>$money,'version'=>$id+['default'=>1], 'created_by'=>$id,'created_at'=>$date,'request_id'=>['type'=>'CHAR','constraint'=>36],'request_hash'=>['type'=>'CHAR','constraint'=>64]]);
        $this->forge->addPrimaryKey('id'); $this->forge->addUniqueKey('request_id'); $this->forge->addForeignKey('worker_id','agri_workers','id','RESTRICT','RESTRICT'); $this->forge->createTable('agri_settlements',false,$opts);
        $this->forge->addField(['settlement_id'=>$id,'earning_id'=>$id]); $this->forge->addPrimaryKey('earning_id'); $this->forge->addForeignKey('settlement_id','agri_settlements','id','RESTRICT','RESTRICT'); $this->forge->addForeignKey('earning_id','agri_earnings','id','RESTRICT','RESTRICT'); $this->forge->createTable('agri_settlement_items',false,$opts);
        $this->forge->addField(['id'=>$id+['auto_increment'=>true],'settlement_id'=>$id,'amount_cents'=>$money,'paid_on'=>['type'=>'DATE'],'reference'=>['type'=>'VARCHAR','constraint'=>160,'null'=>true],'notes'=>['type'=>'VARCHAR','constraint'=>500,'null'=>true],'created_by'=>$id,'created_at'=>$date,'request_id'=>['type'=>'CHAR','constraint'=>36],'request_hash'=>['type'=>'CHAR','constraint'=>64]]);
        $this->forge->addPrimaryKey('id'); $this->forge->addUniqueKey('request_id'); $this->forge->addForeignKey('settlement_id','agri_settlements','id','RESTRICT','RESTRICT'); $this->forge->createTable('agri_payment_entries',false,$opts);
    }
    public function down()
    {
        foreach(['agri_payment_entries','agri_settlement_items','agri_settlements','agri_earnings','agri_work_pools'] as $table) $this->forge->dropTable($table);
        $this->forge->dropColumn('agri_work_log_attendees','pool_key'); $this->forge->dropColumn('agri_work_logs',['closed_at','closed_by','closure_hash']); $this->forge->dropColumn('agri_teams','manager_user_id');
    }
}
