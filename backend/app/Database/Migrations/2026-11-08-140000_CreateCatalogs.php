<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCatalogs extends Migration
{
    public function up()
    {
        $definitions = [
            'teams' => ['responsible_name' => 120, 'notes' => 2000],
            'clients' => ['contact_name' => 120, 'email' => 254, 'phone' => 40, 'notes' => 2000],
            'units' => ['code' => 32],
            'workers' => ['email' => 254, 'phone' => 40, 'notes' => 2000],
            'services' => ['notes' => 2000],
        ];
        foreach ($definitions as $table => $fields) {
            $columns = [
                'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 160],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
                'created_at' => ['type' => 'DATETIME'],
                'updated_at' => ['type' => 'DATETIME'],
            ];
            foreach ($fields as $name => $length) {
                $columns[$name] = ['type' => 'VARCHAR', 'constraint' => $length, 'null' => true];
            }
            if ($table === 'workers') {
                $columns['team_id'] = ['type' => 'INT', 'unsigned' => true, 'null' => true];
            }
            if ($table === 'services') {
                foreach (['payment_unit_id', 'billing_unit_id'] as $name) {
                    $columns[$name] = ['type' => 'INT', 'unsigned' => true];
                }
            }
            $this->forge->addField($columns);
            $this->forge->addKey('id', true);
            $this->forge->addKey('name');
            if ($table === 'units') {
                $this->forge->addUniqueKey('code');
            }
            if ($table === 'workers') {
                $this->forge->addForeignKey('team_id', 'agri_teams', 'id', 'RESTRICT', 'RESTRICT');
            }
            if ($table === 'services') {
                foreach (['payment_unit_id', 'billing_unit_id'] as $name) {
                    $this->forge->addForeignKey($name, 'agri_units', 'id', 'RESTRICT', 'RESTRICT');
                }
            }
            $this->forge->createTable('agri_' . $table);
        }
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'resource' => ['type' => 'VARCHAR', 'constraint' => 20],
            'record_id' => ['type' => 'INT', 'unsigned' => true],
            'actor_id' => ['type' => 'INT', 'unsigned' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 10],
            'before_json' => ['type' => 'TEXT', 'null' => true],
            'after_json' => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['resource', 'record_id']);
        $this->forge->createTable('agri_catalog_audit');
    }

    public function down()
    {
        foreach (['catalog_audit', 'services', 'workers', 'units', 'clients', 'teams'] as $table) {
            $this->forge->dropTable('agri_' . $table);
        }
    }
}
