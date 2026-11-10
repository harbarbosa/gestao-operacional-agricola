<?php
namespace App\Reports;

use App\Users\UserProblem;

class WorkReport
{
    public function __construct(private \CodeIgniter\Database\BaseConnection $db) {}
    public function filters(array $input): array
    {
        $filters = [];
        foreach (['worker_id', 'client_id', 'service_id', 'team_id', 'site_id'] as $key) {
            $value = $input[$key] ?? '';
            if ($value !== '' && (! is_string($value) || ! ctype_digit($value) || (int) $value < 1)) throw new UserProblem(422, 'Selecione filtros válidos.');
            $filters[$key] = $value;
        }
        foreach (['date_from', 'date_to'] as $key) {
            $value = $input[$key] ?? '';
            if ($value !== '' && (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) || (\DateTimeImmutable::createFromFormat('!Y-m-d', $value) ?: null)?->format('Y-m-d') !== $value)) throw new UserProblem(422, 'Indique datas válidas.');
            $filters[$key] = $value;
        }
        if ($filters['date_from'] && $filters['date_to'] && $filters['date_from'] > $filters['date_to']) throw new UserProblem(422, 'A data inicial não pode ser posterior à final.');
        $filters['status'] = $input['status'] ?? '';
        if (! in_array($filters['status'], ['', 'draft', 'closed', 'canceled'], true)) throw new UserProblem(422, 'Selecione um estado válido.');
        return $filters;
    }
    private function logs(array $filters)
    {
        $builder = $this->db->table('agri_work_logs l');
        foreach (['client_id', 'service_id', 'team_id', 'site_id'] as $key) if ($filters[$key]) $builder->where('l.' . $key, (int) $filters[$key]);
        if ($filters['worker_id']) $builder->whereIn('l.id', $this->db->table('agri_work_log_attendees')->select('work_log_id')->where('worker_id', (int) $filters['worker_id']));
        if ($filters['date_from']) $builder->where('l.work_date >=', $filters['date_from']);
        if ($filters['date_to']) $builder->where('l.work_date <=', $filters['date_to']);
        if ($filters['status']) $builder->where('l.status', $filters['status']);
        return $builder;
    }
    private function detail(array $filters)
    {
        $builder = $this->logs($filters)->select('l.id AS work_log_id, l.work_date, l.client_id, l.client_name, l.site_id, l.site_name, l.team_id, l.team_name, l.service_id, l.service_name, l.responsible_name, l.status, l.billing_quantity, l.billing_unit_name, l.notes AS work_notes, l.cancel_reason, l.created_at, l.updated_at, l.created_by, l.updated_by, l.version, l.payment_unit_name, a.worker_id, a.worker_name, a.present, a.time_in, a.time_out, a.exit_next_day, a.payment_quantity, a.score, a.notes AS worker_notes, a.pool_key, p.name AS pool_name, p.quantity AS pool_quantity')
            ->join('agri_work_log_attendees a', 'a.work_log_id = l.id', 'left')->join('agri_work_pools p','p.work_log_id=l.id AND p.pool_key=a.pool_key','left');
        if ($filters['worker_id']) $builder->where('a.worker_id', (int) $filters['worker_id']);
        return $builder;
    }
    private function attendees(array $filters)
    {
        $builder = $this->db->table('agri_work_log_attendees a')->join('agri_work_logs l', 'l.id = a.work_log_id')->whereIn('l.id', $this->logs($filters)->select('l.id'));
        if ($filters['worker_id']) $builder->where('a.worker_id', (int) $filters['worker_id']);
        return $builder;
    }
    public function result(array $filters, int $page = 1, bool $all = false): array
    {
        $builder = $this->detail($filters);
        $total = $builder->countAllResults(false);
        if ($all && $total > 10000) throw new UserProblem(422, 'A exportação está limitada a 10 000 linhas. Reduza o período ou aplique outros filtros.');
        $builder->orderBy('l.work_date', 'DESC')->orderBy('l.id', 'DESC')->orderBy('a.worker_name');
        $rows = $all ? $builder->get()->getResultArray() : $builder->get(50, ($page - 1) * 50)->getResultArray();
        $logs = $this->logs($filters);
        $logCount = $logs->countAllResults();
        $statuses = $this->logs($filters)->select('l.status, COUNT(*) AS total')->groupBy('l.status')->get()->getResultArray();
        $billing = $this->logs($filters)->select('l.billing_unit_name AS unit, SUM(l.billing_quantity) AS quantity')->groupBy('l.billing_unit_id')->groupBy('l.billing_unit_name')->get()->getResultArray();
        $people = $this->attendees($filters)->select('COUNT(DISTINCT a.worker_id) AS workers, SUM(CASE WHEN a.present = 1 THEN 1 ELSE 0 END) AS presences, SUM(CASE WHEN a.present = 0 THEN 1 ELSE 0 END) AS absences', false)->get()->getRowArray();
        $production = $this->attendees($filters)->select('l.payment_unit_name AS unit, SUM(a.payment_quantity) AS quantity')->where('a.present', 1)->groupBy('l.payment_unit_id')->groupBy('l.payment_unit_name')->get()->getResultArray();
        $groupBuilder=$this->db->table('agri_work_pools p')->join('agri_work_logs l','l.id=p.work_log_id')->whereIn('l.id',$this->logs($filters)->select('l.id'));
        if($filters['worker_id']) $groupBuilder->join('agri_work_log_attendees ga','ga.work_log_id=p.work_log_id AND ga.pool_key=p.pool_key AND ga.worker_id='.(int)$filters['worker_id']);
        $groupProduction=$groupBuilder->select('l.payment_unit_name AS unit,SUM(p.quantity) AS quantity')->groupBy('l.payment_unit_id')->groupBy('l.payment_unit_name')->get()->getResultArray();
        return [
            'items' => $rows, 'total' => $total, 'page' => $page, 'pageSize' => 50,
            'summary' => ['work_logs' => $logCount, 'workers' => (int) ($people['workers'] ?? 0), 'presences' => (int) ($people['presences'] ?? 0), 'absences' => (int) ($people['absences'] ?? 0), 'statuses' => $statuses, 'billing' => $billing, 'production' => $production, 'group_production'=>$groupProduction],
            'filters' => $filters, 'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }
}
