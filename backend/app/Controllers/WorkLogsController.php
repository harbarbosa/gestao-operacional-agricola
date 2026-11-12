<?php
namespace App\Controllers;

use App\Field\DraftBook;
use App\Field\FieldProblem;
use App\Field\FieldAccess;

class WorkLogsController extends BaseController
{
    public function index()
    {
        try { FieldAccess::requireField(); } catch (FieldProblem $e) { return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]); }
        $page = $this->request->getGet('page') ?? '1'; $date = $this->request->getGet('date');
        if (! is_string($page) || ! ctype_digit($page) || (int) $page < 1 || (int) $page > 100000
            || ($date !== null && (! is_string($date) || ! preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', $date)))) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Filtros inválidos.']);
        }
        if ($date !== null) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (! $parsed || $parsed->format('Y-m-d') !== $date) { return $this->response->setStatusCode(422)->setJSON(['error' => 'Data inválida.']); }
        }
        $builder = db_connect()->table('agri_work_logs');
        if (!FieldAccess::manager()) $builder->groupStart()->where('controller_id',auth()->id())->orGroupStart()->where('harvest_id',null)->whereIn('team_id', FieldAccess::teamIds() ?: [0])->groupEnd()->groupEnd();
        if ($date !== null) { $builder->where('work_date', $date); }
        $total = $builder->countAllResults(false);
        $rows = $builder->orderBy('work_date', 'DESC')->orderBy('id', 'DESC')->get(25, ((int) $page - 1) * 25)->getResultArray();
        return $this->response->setJSON(['items' => array_map(fn ($row) => \App\Harvest\HarvestBook::operational((new DraftBook(db_connect()))->normalize($row)), $rows), 'total' => $total, 'page' => (int) $page, 'pageSize' => 25]);
    }
    public function show(string $id) { if ($deny = $this->access($id)) return $deny; return $this->run(fn ($book, $input) => ['item' => $book->get((int) $id)], false); }
    public function create() { return $this->run(function($book,$input) { if(empty($input['harvest_id'])) FieldAccess::check((int)($input['team_id'] ?? 0)); return $book->create($input,(int)auth()->id()); }, true, true); }
    public function update(string $id) { if ($deny = $this->access($id)) return $deny; return $this->run(fn ($book, $input) => $book->update((int) $id, $input, (int) auth()->id())); }
    public function cancel(string $id) { if ($deny = $this->access($id)) return $deny; return $this->run(fn ($book, $input) => $book->update((int) $id, $input, (int) auth()->id(), true)); }
    private function access(string $id)
    {
        try { FieldAccess::requireField(); $row = db_connect()->table('agri_work_logs')->where('id',(int)$id)->get()->getRowArray(); if (!$row) throw new FieldProblem(404,'Atividade não encontrada.'); FieldAccess::checkLog($row); return null; }
        catch (FieldProblem $e) { return $this->response->setStatusCode($e->status)->setJSON(['error'=>$e->getMessage()]); }
    }
    private function run(callable $operation, bool $body = true, bool $create = false)
    {
        try {
            $input = $body ? $this->request->getJSON(true) : [];
            if (! is_array($input)) { throw new FieldProblem(422, 'Envie um objeto com os dados.'); }
            FieldAccess::requireField();
            if ($this->request->getMethod() === 'POST' && $this->request->getUri()->getPath() === 'api/v1/work-logs' && isset($input['team_id']) && empty($input['harvest_id'])) FieldAccess::check((int)$input['team_id']);
            $result = $operation(new DraftBook(db_connect()), $input);
            if (!\App\Harvest\HarvestBook::admin() && isset($result['item'])) $result['item']=\App\Harvest\HarvestBook::operational($result['item']);
            return $this->response->setStatusCode($create && ! $result['replayed'] ? 201 : 200)->setJSON($result);
        } catch (FieldProblem $error) { return $this->response->setStatusCode($error->status)->setJSON(['error' => $error->getMessage(), 'fields' => $error->fields]); }
    }
}
