// @vitest-environment happy-dom
import { act } from 'react'
import { createRoot, type Root } from 'react-dom/client'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import Reports from './Reports'
import { api } from './api'
vi.mock('./api', async importOriginal => ({ ...await importOriginal<typeof import('./api')>(), api: vi.fn() }))
let root: Root; let host: HTMLDivElement
const result = { items: [{ work_log_id: 1, work_date: '2026-07-10', client_name: 'Cliente', site_name: 'Quinta', team_name: 'Equipa', service_name: 'Colheita', worker_id: 3, worker_name: 'Ana', present: '1', time_in: '08:00', time_out: '17:00', exit_next_day: '0', payment_quantity: '40.0000', payment_unit_name: 'Caixa', billing_quantity: '100.0000', billing_unit_name: 'Caixa', score: 5, status: 'draft' }], total: 1, page: 1, pageSize: 50, generated_at: '2026-07-10T08:00:00Z', notice: 'Sem apuramento financeiro.', summary: { work_logs: 1, workers: 1, presences: 1, absences: 0, statuses: [], production: [{unit:'Caixa', quantity:'40.0000'}], billing: [{unit:'Caixa',quantity:'100.0000'}] } }
beforeEach(() => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true })
  vi.mocked(api).mockResolvedValue(result)
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
  vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:test')
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ok:true, blob:async()=>new Blob(['report'])}))
  host = document.createElement('div'); document.body.append(host); root = createRoot(host)
})
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.restoreAllMocks(); vi.unstubAllGlobals(); vi.clearAllMocks() })
async function render() { await act(async () => root.render(<Reports onSessionExpired={vi.fn()} onBusyChange={vi.fn()} />)) }
it('apresenta detalhe operacional com datas portuguesas e quantidades formatadas', async () => {
  await render()
  expect(host.querySelector('tbody')!.textContent).toContain('10/07/2026')
  expect(host.querySelector('tbody')!.textContent).toContain('Ana')
  expect(host.querySelector('tbody')!.textContent).toContain('40 Caixa')
  expect(host.textContent).not.toContain('Sem apuramento financeiro.'); expect(host.textContent).not.toContain('Exportações incluem'); expect(host.textContent).not.toContain('Emitido em')
})
it('exporta os filtros aplicados e não altera o relatório com filtros ainda não aplicados', async () => {
  await render()
  await act(async () => {
    const input=host.querySelector<HTMLInputElement>('#report-from')!
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,'2026-07-01')
    input.dispatchEvent(new Event('input',{bubbles:true}))
  })
  await act(async () => host.querySelector('form')!.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})))
  expect(api).toHaveBeenLastCalledWith('/reports/work-logs?date_from=2026-07-01&page=1')
  await act(async () => [...host.querySelectorAll('button')].find(button=>button.textContent==='Excel')!.click())
  expect(fetch).toHaveBeenCalledWith('/api/v1/reports/work-logs/export?date_from=2026-07-01&format=xlsx', expect.objectContaining({credentials:'same-origin'}))
})
