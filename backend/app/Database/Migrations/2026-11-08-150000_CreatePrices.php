<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePrices extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'service_id' => ['type' => 'INT', 'unsigned' => true],
            'kind' => ['type' => 'VARCHAR', 'constraint' => 10],
            'worker_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'client_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'unit_id' => ['type' => 'INT', 'unsigned' => true],
            'unit_name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'unit_code' => ['type' => 'VARCHAR', 'constraint' => 32],
            'amount' => ['type' => 'DECIMAL', 'constraint' => '12,4'],
            'currency' => ['type' => 'CHAR', 'constraint' => 3],
            'valid_from' => ['type' => 'DATE'],
            'valid_to' => ['type' => 'DATE', 'null' => true],
            'version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'created_by' => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME'],
            'canceled_at' => ['type' => 'DATETIME', 'null' => true],
            'cancel_reason' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['service_id', 'kind', 'worker_id', 'client_id', 'valid_from']);
        foreach (['service_id' => 'services', 'worker_id' => 'workers', 'client_id' => 'clients', 'unit_id' => 'units'] as $field => $table) {
            $this->forge->addForeignKey($field, 'agri_' . $table, 'id', 'RESTRICT', 'RESTRICT');
        }
        $this->forge->createTable('agri_prices', false, $this->db->getPlatform() === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : []);
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'price_id' => ['type' => 'INT', 'unsigned' => true],
            'actor_id' => ['type' => 'INT', 'unsigned' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 10],
            'before_json' => ['type' => 'TEXT', 'null' => true],
            'after_json' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('price_id', 'agri_prices', 'id', 'RESTRICT', 'RESTRICT');
        $this->forge->createTable('agri_price_audit', false, $this->db->getPlatform() === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : []);
    }

    public function down()
    {
        $this->forge->dropTable('agri_price_audit');
        $this->forge->dropTable('agri_prices');
    }
}
