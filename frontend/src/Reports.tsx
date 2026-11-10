import { useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError } from './api'
import ReferenceField from './ReferenceField'
import { Icon } from './Icons'
import Modal from './Modal'
import { formatDate, formatDateTime, formatNumber, formatTime, statusLabel } from './locale'

type Filters = { worker_id: string; client_id: string; service_id: string; team_id: string; site_id: string; date_from: string; date_to: string; status: string }
type ReportRow = { pool_name?:string|null;pool_quantity?:string|null; work_log_id: number | string; work_date: string; client_name: string; site_name: string; team_name: string; service_name: string; responsible_name: string | null; worker_id: number | string | null; worker_name: string | null; present: boolean | number | string | null; time_in: string | null; time_out: string | null; exit_next_day: boolean | number | string; payment_quantity: string | null; payment_unit_name: string; billing_quantity: string | null; billing_unit_name: string; score: number | string | null; worker_notes: string | null; work_notes: string | null; status: string; cancel_reason: string | null; created_at: string; updated_at: string; created_by: number | string; updated_by: number | string; version: number | string }
interface Report { items: ReportRow[]; total: number; page: number; pageSize: number; generated_at: string; summary: { work_logs: number; workers: number; presences: number; absences: number; statuses: {status: string; total: number | string}[]; group_production?: {unit:string;quantity:string|null}[]; billing: {unit: string; quantity: string | null}[]; production: {unit: string; quantity: string | null}[] } }
const blank: Filters = { worker_id: '', client_id: '', service_id: '', team_id: '', site_id: '', date_from: '', date_to: '', status: '' }
function params(filters: Filters) { return new URLSearchParams(Object.entries(filters).filter(([, value]) => value !== '')) }
const isTrue = (value: unknown) => value === true || value === 1 || value === '1'

export default function Reports({ onSessionExpired, onBusyChange }: { onSessionExpired: () => void; onBusyChange: (busy: boolean) => void }) {
  const [filters, setFilters] = useState(blank)
  const [applied, setApplied] = useState(blank)
  const [page, setPage] = useState(1)
  const [reload, setReload] = useState(0)
  const [report, setReport] = useState<Report | null>(null)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [detail, setDetail] = useState<ReportRow | null>(null)
  useEffect(() => {
    let active = true
    const query = params(applied); query.set('page', String(page))
    api<Report>(`/reports/work-logs?${query}`).then(data => { if (active) { setReport(data); setLoading(false) } }).catch(error => {
      if (!active) return
      setReport(null); setLoading(false)
      if (error instanceof ApiError && error.status === 401) onSessionExpired(); else setMessage(error instanceof Error ? error.message : 'Não foi possível carregar o relatório.')
    })
    return () => { active = false }
  }, [applied, page, reload, onSessionExpired])
  function apply(event: SubmitEvent<HTMLFormElement>) { event.preventDefault(); setPage(1); setApplied({ ...filters }); setLoading(true); setMessage('') }
  async function exportData(format: 'csv' | 'xlsx' | 'pdf') {
    setBusy(true); onBusyChange(true); setMessage('')
    try {
      const query = params(applied); query.set('format', format)
      const response = await fetch(`/api/v1/reports/work-logs/export?${query}`, { credentials: 'same-origin', cache: 'no-store' })
      if (!response.ok) { const error = await response.json().catch(() => ({})); throw new ApiError(response.status, error.error ?? 'Não foi possível exportar o relatório.') }
      const blob = await response.blob(); const url = URL.createObjectURL(blob)
      const anchor = document.createElement('a'); anchor.href = url; anchor.download = `trabalhos-${new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Lisbon' }).format(new Date())}.${format}`; document.body.append(anchor); anchor.click(); anchor.remove(); setTimeout(() => URL.revokeObjectURL(url), 30000)
      setMessage('Exportação concluída com todos os resultados dos filtros aplicados.')
    } catch (error) { if (error instanceof ApiError && error.status === 401) onSessionExpired(); else setMessage(error instanceof Error ? error.message : 'Não foi possível exportar.'); }
    finally { setBusy(false); onBusyChange(false) }
  }
  return <section className="catalog">
    <div className="section-title"><h2>Relatórios de trabalhos</h2><div className="actions">{(['xlsx', 'csv', 'pdf'] as const).map(format => <button key={format} disabled={busy || loading || !report?.total} onClick={() => exportData(format)}>{format === 'xlsx' ? 'Excel' : format.toUpperCase()}</button>)}</div></div>
    <form className="report-filters" onSubmit={apply}>
      {([{ key: 'worker_id', label: 'Trabalhador', resource: 'workers' }, { key: 'client_id', label: 'Cliente', resource: 'clients' }, { key: 'service_id', label: 'Tipo de trabalho / serviço', resource: 'services' }, { key: 'team_id', label: 'Equipa', resource: 'teams' }, { key: 'site_id', label: 'Local / exploração', resource: 'sites' }] as const).map(field => <div className="form-field" key={field.key}><label htmlFor={`report-${field.key}`}>{field.label}</label><ReferenceField field={{ ...field, source: 'reports', key: `report-${field.key}`, query: field.key === 'site_id' && filters.client_id ? `client_id=${filters.client_id}` : undefined }} value={filters[field.key]} onChange={value => setFilters(old => ({ ...old, [field.key]: value, ...(field.key === 'client_id' ? { site_id: '' } : {}) }))} disabled={busy} includeInactive emptyLabel="Todos" /></div>)}
      <div className="form-field"><label htmlFor="report-from">Data inicial</label><input id="report-from" type="date" lang="pt-PT" value={filters.date_from} max={filters.date_to || undefined} disabled={busy} onChange={event => setFilters(old => ({ ...old, date_from: event.target.value }))} /></div>
      <div className="form-field"><label htmlFor="report-to">Data final</label><input id="report-to" type="date" lang="pt-PT" value={filters.date_to} min={filters.date_from || undefined} disabled={busy} onChange={event => setFilters(old => ({ ...old, date_to: event.target.value }))} /></div>
      <div className="form-field"><label htmlFor="report-status">Estado</label><select id="report-status" value={filters.status} disabled={busy} onChange={event => setFilters(old => ({ ...old, status: event.target.value }))}><option value="">Todos</option><option value="draft">Rascunho</option><option value="closed">Fechado</option><option value="canceled">Cancelado</option></select></div>
      <div className="actions"><button disabled={busy}>Gerar relatório</button><button type="button" disabled={busy} onClick={() => { setFilters(blank); setApplied(blank); setPage(1); setLoading(true); setMessage(''); setReload(value => value + 1) }}>Limpar filtros</button></div>
    </form>
    <p role="status" aria-live="polite">{message}</p>
    {loading ? <p>A carregar relatório…</p> : report && <>
      <div className="stats-grid">{[['Diários', report.summary.work_logs], ['Trabalhadores', report.summary.workers], ['Presenças', report.summary.presences], ['Ausências', report.summary.absences]].map(([label, value]) => <div className="stat" key={label}><span>{label}</span><strong>{formatNumber(value)}</strong></div>)}</div>
      <div className="report-totals"><div><h3>Produção individual</h3>{report.summary.production.length ? report.summary.production.map((item, index) => <p key={index}>{formatNumber(item.quantity)} {item.unit}</p>) : <p>Sem quantidades registadas.</p>}</div><div><h3>Produção do cliente, por diário</h3>{report.summary.billing.length ? report.summary.billing.map((item, index) => <p key={index}>{formatNumber(item.quantity)} {item.unit}</p>) : <p>Sem quantidades registadas.</p>}</div></div>
      {!!report.summary.group_production?.length&&<div className="report-totals"><div><h3>Produção em grupo</h3>{report.summary.group_production.map((row,index)=><p key={index}>{formatNumber(row.quantity)} {row.unit}</p>)}</div></div>}
      <div className="table-wrap"><table><thead><tr><th>Diário / data</th><th>Cliente / local</th><th>Equipa / serviço</th><th>Trabalhador</th><th>Presença / horários</th><th>Produção individual</th><th>Produção cliente por diário</th><th>Avaliação / observações</th><th>Estado</th><th>Detalhes</th></tr></thead><tbody>{report.items.map((row, index) => <tr key={`${row.work_log_id}-${row.worker_id}-${index}`}><td>#{row.work_log_id}<br />{formatDate(row.work_date)}</td><td>{row.client_name}<br />{row.site_name}</td><td>{row.team_name}<br />{row.service_name}</td><td>{row.worker_name ?? 'Sem participantes'}{row.pool_name&&<small className="permissions-count">{row.pool_name} · {formatNumber(row.pool_quantity)} {row.payment_unit_name}</small>}</td><td>{row.worker_id ? isTrue(row.present) ? 'Presente' : 'Ausente' : '—'}<br />{formatTime(row.time_in)} – {formatTime(row.time_out)}{isTrue(row.exit_next_day) && <small> · Dia seguinte</small>}</td><td>{formatNumber(row.payment_quantity)} {row.payment_unit_name}</td><td>{formatNumber(row.billing_quantity)} {row.billing_unit_name}</td><td>{row.score ?? 'Sem avaliação'}<br /><span className="report-note">{row.worker_notes || row.work_notes || '—'}</span></td><td>{statusLabel(row.status)}</td><td><button className="icon-action" title="Consultar detalhes" aria-label={`Consultar diário ${row.work_log_id}`} onClick={() => setDetail(row)}><Icon name="view" /></button></td></tr>)}{!report.items.length && <tr><td colSpan={10}>Nenhum trabalho encontrado.</td></tr>}</tbody></table></div>
      <div className="actions"><button disabled={busy || page === 1} onClick={() => { setPage(value => value - 1); setLoading(true) }}>Anterior</button><span>Página {page}</span><button disabled={busy || page * report.pageSize >= report.total} onClick={() => { setPage(value => value + 1); setLoading(true) }}>Seguinte</button></div>
    </>}
    {detail && <Modal title={`Diário #${detail.work_log_id} · ${formatDate(detail.work_date)}`} onClose={() => setDetail(null)}><dl className="report-details">{Object.entries({ Cliente: detail.client_name, Local: detail.site_name, Equipa: detail.team_name, Responsável: detail.responsible_name, Grupo: detail.pool_name, 'Produção conjunta': detail.pool_quantity ? `${formatNumber(detail.pool_quantity)} ${detail.payment_unit_name}` : null, Serviço: detail.service_name, Trabalhador: detail.worker_name, 'Produção individual': `${formatNumber(detail.payment_quantity)} ${detail.payment_unit_name}`, 'Produção do cliente': `${formatNumber(detail.billing_quantity)} ${detail.billing_unit_name}`, Entrada: formatTime(detail.time_in), Saída: `${formatTime(detail.time_out)}${isTrue(detail.exit_next_day) ? ' (dia seguinte)' : ''}`, Avaliação: detail.score, 'Observações individuais': detail.worker_notes, 'Observações do diário': detail.work_notes, 'Motivo de cancelamento': detail.cancel_reason, 'Criado em': formatDateTime(detail.created_at), 'Atualizado em': formatDateTime(detail.updated_at), 'ID autor': detail.created_by, 'ID última alteração': detail.updated_by, Versão: detail.version }).map(([label, value]) => <div key={label}><dt>{label}</dt><dd>{value ?? '—'}</dd></div>)}</dl></Modal>}
  </section>
}
