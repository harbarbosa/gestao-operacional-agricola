<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateWorkerPortraits extends Migration
{
 public function up(){
  $this->forge->addColumn('agri_workers',['photo_version'=>['type'=>'INT','unsigned'=>true,'default'=>0]]);
  $this->forge->addField(['worker_id'=>['type'=>'INT','unsigned'=>true],'filename'=>['type'=>'VARCHAR','constraint'=>80],'mime'=>['type'=>'VARCHAR','constraint'=>40],'updated_by'=>['type'=>'INT','unsigned'=>true],'updated_at'=>['type'=>'DATETIME']]);$this->forge->addPrimaryKey('worker_id');$this->forge->addForeignKey('worker_id','agri_workers','id','RESTRICT','RESTRICT');$this->forge->createTable('agri_worker_portraits',false,$this->db->getPlatform()==='MySQLi'?['ENGINE'=>'InnoDB']:[]);
 }
 public function down(){$this->forge->dropTable('agri_worker_portraits');$this->forge->dropColumn('agri_workers','photo_version');}
}
