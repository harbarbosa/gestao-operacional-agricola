<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFieldDrafts extends Migration
{
    public function up()
    {
        $attributes = $this->db->getPlatform() === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : [];
        $id = ['type' => 'INT', 'unsigned' => true];
        $text = ['type' => 'VARCHAR', 'constraint' => 160];
        $this->forge->addField([
            'id' => $id + ['auto_increment' => true], 'client_id' => $id, 'name' => $text,
            'locality' => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 2000, 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'version' => $id + ['default' => 1], 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true); $this->forge->addKey('name');
        $this->forge->addForeignKey('client_id', 'agri_clients', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('agri_sites', false, $attributes);
        $columns = [
            'id' => $id + ['auto_increment' => true],
            'request_id' => ['type' => 'CHAR', 'constraint' => 36], 'creation_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'work_date' => ['type' => 'DATE'], 'team_id' => $id, 'site_id' => $id, 'service_id' => $id, 'client_id' => $id,
            'team_name' => $text, 'site_name' => $text, 'service_name' => $text, 'client_name' => $text,
            'responsible_name' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'draft'],
            'billing_quantity' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'null' => true],
            'notes' => ['type' => 'VARCHAR', 'constraint' => 2000, 'null' => true], 'version' => $id + ['default' => 1],
            'created_by' => $id, 'updated_by' => $id, 'created_at' => ['type' => 'DATETIME'], 'updated_at' => ['type' => 'DATETIME'],
            'cancel_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ];
        foreach (['payment', 'billing'] as $kind) {
            $columns[$kind . '_unit_id'] = $id;
            $columns[$kind . '_unit_name'] = $text;
            $columns[$kind . '_unit_code'] = ['type' => 'VARCHAR', 'constraint' => 32];
        }
        $this->forge->addField($columns);
        $this->forge->addKey('id', true); $this->forge->addUniqueKey('request_id'); $this->forge->addKey(['work_date', 'team_id']);
        foreach (['team_id' => 'teams', 'site_id' => 'sites', 'service_id' => 'services', 'client_id' => 'clients', 'payment_unit_id' => 'units', 'billing_unit_id' => 'units'] as $field => $table) {
            $this->forge->addForeignKey($field, 'agri_' . $table, 'id', 'RESTRICT', 'RESTRICT');
        }
        $this->forge->createTable('agri_work_logs', false, $attributes);
        $this->forge->addField([
            'work_log_id' => $id, 'worker_id' => $id, 'worker_name' => $text,
            'present' => ['type' => 'TINYINT', 'constraint' => 1],
            'time_in' => ['type' => 'CHAR', 'constraint' => 5, 'null' => true], 'time_out' => ['type' => 'CHAR', 'constraint' => 5, 'null' => true],
            'exit_next_day' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'payment_quantity' => ['type' => 'DECIMAL', 'constraint' => '12,4', 'null' => true],
            'score' => ['type' => 'TINYINT', 'null' => true], 'notes' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);
        $this->forge->addKey(['work_log_id', 'worker_id'], true);
        $this->forge->addForeignKey('work_log_id', 'agri_work_logs', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->addForeignKey('worker_id', 'agri_workers', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('agri_work_log_attendees', false, $attributes);
        $jsonType = $this->db->getPlatform() === 'MySQLi' ? 'MEDIUMTEXT' : 'TEXT';
        $this->forge->addField([
            'id' => $id + ['auto_increment' => true], 'work_log_id' => $id, 'actor_id' => $id,
            'action' => ['type' => 'VARCHAR', 'constraint' => 12],
            'before_json' => ['type' => $jsonType, 'null' => true], 'after_json' => ['type' => $jsonType], 'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true); $this->forge->addForeignKey('work_log_id', 'agri_work_logs', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('agri_work_log_audit', false, $attributes);
    }

    public function down()
    {
        foreach (['work_log_audit', 'work_log_attendees', 'work_logs', 'sites'] as $table) { $this->forge->dropTable('agri_' . $table); }
    }
}
