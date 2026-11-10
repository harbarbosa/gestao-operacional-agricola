<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUserManagement extends Migration
{
    public function up()
    {
        $attributes = $this->db->getPlatform() === 'MySQLi' ? ['ENGINE' => 'InnoDB'] : [];
        $this->forge->addField([
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
            'security_version' => ['type' => 'INT', 'unsigned' => true, 'default' => 1],
        ]);
        $this->forge->addPrimaryKey('user_id');
        $this->forge->createTable('agri_user_versions', false, $attributes);
        // A linha zero serializa alterações administrativas, incluindo a proteção do último administrador.
        $this->db->table('agri_user_versions')->insert(['user_id' => 0, 'version' => 1, 'security_version' => 1]);
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true],
            'actor_id' => ['type' => 'INT', 'unsigned' => true],
            'action' => ['type' => 'VARCHAR', 'constraint' => 12],
            'before_json' => ['type' => 'TEXT', 'null' => true],
            'after_json' => ['type' => 'TEXT'],
            'password_changed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['user_id', 'created_at']);
        $this->forge->createTable('agri_user_audit', false, $attributes);
    }
    public function down()
    {
        $this->forge->dropTable('agri_user_audit');
        $this->forge->dropTable('agri_user_versions');
    }
}
