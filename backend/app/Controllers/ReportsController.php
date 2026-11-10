<?php
namespace App\Controllers;

use App\Reports\WorkReport;
use App\Reports\ReportExport;
use App\Users\UserProblem;

class ReportsController extends BaseController
{
    public function options(string $resource, ?string $id = null)
    {
        if (! in_array($resource, ['workers', 'clients', 'services', 'teams', 'sites'], true)) return $this->response->setStatusCode(404)->setJSON(['error' => 'Filtro inexistente.']);
        $builder = db_connect()->table('agri_' . $resource)->select('id, name, is_active, version');
        if ($id !== null) {
            $item = $builder->where('id', (int) $id)->get()->getRowArray();
            return $item ? $this->response->setJSON(['item' => ['id' => (int) $item['id'], 'name' => $item['name'], 'is_active' => (bool) $item['is_active'], 'version' => (int) $item['version']]]) : $this->response->setStatusCode(404)->setJSON(['error' => 'Registo não encontrado.']);
        }
        $query = $this->request->getGet('q') ?? '';
        if (! is_string($query) || mb_strlen($query) > 160) return $this->response->setStatusCode(422)->setJSON(['error' => 'Pesquisa inválida.']);
        if ($query !== '') $builder->like('name', trim($query));
        if ($resource === 'sites' && ($client = $this->request->getGet('client_id')) !== null) {
            if (! is_string($client) || ! ctype_digit($client) || (int) $client < 1) return $this->response->setStatusCode(422)->setJSON(['error' => 'Cliente inválido.']);
            $builder->where('client_id', (int) $client);
        }
        $total = $builder->countAllResults(false);
        $items = $builder->orderBy('name')->get(25)->getResultArray();
        foreach ($items as &$item) { $item['id'] = (int) $item['id']; $item['version'] = (int) $item['version']; $item['is_active'] = (bool) $item['is_active']; }
        return $this->response->setJSON(['items' => $items, 'total' => $total, 'page' => 1, 'pageSize' => 25]);
    }
    public function index()
    {
        try {
            $report = new WorkReport(db_connect());
            $page = $this->request->getGet('page') ?? '1';
            if (! is_string($page) || ! ctype_digit($page) || (int) $page < 1 || (int) $page > 100000) throw new UserProblem(422, 'Página inválida.');
            return $this->response->setJSON($report->result($report->filters($this->request->getGet() ?? []), (int) $page));
        } catch (UserProblem $error) { return $this->response->setStatusCode($error->status)->setJSON(['error' => $error->getMessage()]); }
    }
    public function export()
    {
        try {
            $format = $this->request->getGet('format') ?? 'csv';
            if (! in_array($format, ['csv', 'xlsx', 'pdf'], true)) throw new UserProblem(422, 'Formato de exportação inválido.');
            $book = new WorkReport(db_connect()); $report = $book->result($book->filters($this->request->getGet() ?? []), 1, true);
            $mime = ['csv' => 'text/csv; charset=UTF-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'pdf' => 'application/pdf'][$format];
            $body = match ($format) { 'xlsx' => ReportExport::xlsx($report), 'pdf' => ReportExport::pdf($report), default => ReportExport::csv($report) };
            return $this->response->setHeader('Content-Type', $mime)->setHeader('Content-Disposition', 'attachment; filename="trabalhos-' . (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Lisbon')))->format('Ymd') . '.' . $format . '"')->setHeader('Cache-Control', 'no-store')->setBody($body);
        } catch (UserProblem $error) { return $this->response->setStatusCode($error->status)->setJSON(['error' => $error->getMessage()]); }
    }
}
