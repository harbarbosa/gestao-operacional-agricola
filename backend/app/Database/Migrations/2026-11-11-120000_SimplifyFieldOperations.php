<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class SimplifyFieldOperations extends Migration
{
 public function up(){
  $this->forge->addColumn('agri_workers',['is_blocked'=>['type'=>'TINYINT','constraint'=>1,'default'=>0],'block_reason'=>['type'=>'VARCHAR','constraint'=>500,'null'=>true]]);
  $contracts=$this->db->table('agri_contracts c')->select('c.*')->join('agri_harvests h','h.contract_id=c.id','left')->where('h.id',null)->get()->getResultArray();
  foreach($contracts as$c){$id=$c['id'];unset($c['id']);$c+=['contract_id'=>$id,'controller_id'=>0,'starts_on'=>substr($c['created_at'],0,10),'status'=>'active'];$this->db->table('agri_harvests')->insert($c);}
 }
 public function down(){$this->forge->dropColumn('agri_workers',['is_blocked','block_reason']);}
}
