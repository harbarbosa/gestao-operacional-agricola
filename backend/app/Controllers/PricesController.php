<?php

namespace App\Controllers;

use App\Pricing\PriceBook;
use App\Pricing\PriceProblem;

class PricesController extends BaseController
{
    private function book(): PriceBook { return new PriceBook(db_connect()); }

    public function index()
    {
        $service = $this->request->getGet('service_id');
        $page = $this->request->getGet('page') ?? '1';
        if (! is_string($service) || ! ctype_digit($service) || (int) $service < 1
            || ! is_string($page) || ! ctype_digit($page) || (int) $page < 1 || (int) $page > 100000) {
            return $this->response->setStatusCode(422)->setJSON(['error' => 'Selecione serviço e página válidos.']);
        }
        $builder = db_connect()->table('agri_prices')->select('agri_prices.*, agri_workers.name AS worker_name, agri_clients.name AS client_name')
            ->join('agri_workers', 'agri_workers.id = agri_prices.worker_id', 'left')
            ->join('agri_clients', 'agri_clients.id = agri_prices.client_id', 'left')
            ->where('service_id', (int) $service);
        $total = $builder->countAllResults(false);
        $rows = $builder->orderBy('agri_prices.id', 'DESC')->get(25, ((int) $page - 1) * 25)->getResultArray();
        return $this->response->setJSON(['items' => array_map(fn ($row) => $this->book()->normalize($row), $rows), 'total' => $total, 'page' => (int) $page, 'pageSize' => 25]);
    }

    public function create() { return $this->run(fn ($input) => ['item' => $this->book()->create($input, (int) auth()->id())], 201); }
    public function end(string $id) { return $this->run(fn ($input) => ['item' => $this->book()->change((int) $id, $input, (int) auth()->id(), false)]); }
    public function cancel(string $id) { return $this->run(fn ($input) => ['item' => $this->book()->change((int) $id, $input, (int) auth()->id(), true)]); }
    public function preview() { return $this->run(fn ($input) => $this->book()->preview($input)); }

    private function run(callable $operation, int $status = 200)
    {
        try {
            $input = $this->request->getJSON(true);
            if (! is_array($input)) { throw new PriceProblem(422, 'Envie um objeto com os dados.'); }
            return $this->response->setStatusCode($status)->setJSON($operation($input));
        } catch (PriceProblem $error) {
            return $this->response->setStatusCode($error->status)->setJSON(['error' => $error->getMessage(), 'fields' => $error->fields]);
        }
    }
}
