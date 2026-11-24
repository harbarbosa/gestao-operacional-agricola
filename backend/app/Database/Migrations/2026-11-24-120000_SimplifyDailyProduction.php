<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
class SimplifyDailyProduction extends Migration
{
 public function up(){
  $this->forge->addColumn('agri_work_logs',['simple_mode'=>['type'=>'TINYINT','constraint'=>1,'default'=>0]]);
  foreach(['site_id','service_id','client_id','payment_unit_id','billing_unit_id'] as $key)$this->forge->modifyColumn('agri_work_logs',[$key=>['type'=>'INT','unsigned'=>true,'null'=>true]]);
  $this->forge->modifyColumn('agri_earnings',['price_id'=>['type'=>'INT','unsigned'=>true,'null'=>true]]);
  $this->forge->addColumn('agri_work_log_attendees',['payment_rate'=>['type'=>'DECIMAL','constraint'=>'12,4','null'=>true]]);
 }
 public function down(){$this->forge->dropColumn('agri_work_log_attendees','payment_rate');$this->forge->dropColumn('agri_work_logs','simple_mode');}
}
