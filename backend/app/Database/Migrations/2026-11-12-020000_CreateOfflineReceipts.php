<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class CreateOfflineReceipts extends Migration
{
 public function up(){ $this->forge->addField(['actor_id'=>['type'=>'INT','unsigned'=>true],'request_id'=>['type'=>'CHAR','constraint'=>36],'request_hash'=>['type'=>'CHAR','constraint'=>64],'response_json'=>['type'=>'LONGTEXT'],'created_at'=>['type'=>'DATETIME']]);$this->forge->addPrimaryKey(['actor_id','request_id']);$this->forge->addForeignKey('actor_id','users','id','RESTRICT','CASCADE');$this->forge->createTable('agri_offline_receipts',false,$this->db->getPlatform()==='MySQLi'?['ENGINE'=>'InnoDB']:[]); }
 public function down(){$this->forge->dropTable('agri_offline_receipts');}
}
