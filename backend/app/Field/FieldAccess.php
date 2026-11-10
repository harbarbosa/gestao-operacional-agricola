<?php
namespace App\Field;
class FieldAccess
{
    public static function manager(): bool { return auth()->user()->can('field.manage'); }
    public static function teamIds(): array { return array_map('intval',array_column(db_connect()->table('agri_teams')->select('id')->where('manager_user_id',auth()->id())->where('is_active',1)->get()->getResultArray(),'id')); }
    public static function check(int $team): void { if (!self::manager() && !in_array($team,self::teamIds(),true)) throw new FieldProblem(403,'Sem acesso a esta equipa.'); }
    public static function checkLog(array $log): void { if(!empty($log['harvest_id'])) { (new \App\Harvest\HarvestBook(db_connect()))->access((int)$log['harvest_id']); return; } self::check((int)$log['team_id']); }
    public static function requireField(): void { if (!auth()->user()->can('field.record') && !self::manager()) throw new FieldProblem(403,'Sem permissão para registar atividades.'); }
}
