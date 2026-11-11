import { useEffect, useState } from 'react'
import { api, ApiError, type CurrentUser } from './api'
import type { CatalogList } from './catalog'
import type { WorkLogList } from './field'
import { Icon } from './Icons'
import { formatDate, formatNumber, formatCents } from './locale'

type Props = { user: CurrentUser; onNavigate: (key: string) => void; onSessionExpired: () => void }
export default function Dashboard({ user, onNavigate, onSessionExpired }: Props) {
  const [data, setData] = useState<{workers: number; teams: number; logs: WorkLogList; finance:{pending_cents:string;paid_cents:string;receivable_cents?:number;received_cents?:number;cash_cents?:number}|null} | null>(null)
  const [error, setError] = useState('')
  const [attempt, setAttempt] = useState(0)
  const canCatalog = user.permissions.includes('catalog.manage')
  const canField = user.permissions.includes('field.manage') || user.permissions.includes('field.record')
  useEffect(() => {
    let active = true
    setError(''); setData(null)
    Promise.all([
      canCatalog ? api<CatalogList>('/catalog/workers') : Promise.resolve(null),
      canCatalog ? api<{items:unknown[]}>('/harvests').then(r=>({total:r.items.length})) : Promise.resolve(null),
      canField ? api<WorkLogList>('/work-logs?page=1') : Promise.resolve({items: [], total: 0, page: 1, pageSize: 25}),
      user.permissions.includes('finance.manage') ? api<{pending_cents:string;paid_cents:string;receivable_cents?:number;received_cents?:number;cash_cents?:number}>('/settlements/summary') : Promise.resolve(null),
    ]).then(([workers, teams, logs, finance]) => { if (active) setData({workers: workers?.total ?? 0, teams: teams?.total ?? 0, logs,finance}) }).catch(err => {
      if (!active) return
      if (err instanceof ApiError && err.status === 401) onSessionExpired()
      else setError('Não foi possível carregar o resumo. Verifique a ligação e tente novamente.')
    })
    return () => { active = false }
  }, [canCatalog, canField, onSessionExpired, attempt, user.permissions])
  return <>
    <div className="welcome"><div><p className="eyebrow">A SUA OPERAÇÃO, NUM SÓ LUGAR</p><h2>Bom dia, {user.username}</h2><p>Acompanhe os contratos e organize o trabalho no campo.</p></div>{canField && <button onClick={() => onNavigate('work-logs')}>＋ Registar trabalho</button>}</div>
    {error && <div className="notice" role="alert">{error} <button onClick={() => setAttempt(value => value + 1)}>Tentar novamente</button></div>}
    <div className="stats-grid">
      {canCatalog && <><div className="stat"><Icon name="teams"/><span>Trabalhadores registados</span><strong>{data ? formatNumber(data.workers) : '—'}</strong></div><div className="stat"><Icon name="teams"/><span>Contratos registados</span><strong>{data ? formatNumber(data.teams) : '—'}</strong></div></>}
      {user.permissions.includes('finance.manage') && <><div className="stat amber"><Icon name="prices"/><span>A pagar</span><strong>{data?.finance ? formatCents(data.finance.pending_cents) : '—'}</strong></div><div className="stat lime"><Icon name="arrow"/><span>A receber</span><strong>{data?.finance ? formatCents(data.finance.receivable_cents ?? 0) : '—'}</strong></div></>}
    </div>
    <div className="dashboard-grid"><section className="card production"><h2><Icon name="chart"/>Produção da semana</h2><div className="empty-chart"><div className="chart-grid"/><div><Icon name="chart"/><h3>A produção começa no campo</h3></div></div></section><section className="card"><h2><Icon name="clipboard"/>Acompanhamento</h2>{canField ? <><button className="pending-row" onClick={() => onNavigate('work-logs')}><Icon name="calendar"/><span>Consultar diários de campo<small>{data ? `${data.logs.total} registos no total` : 'A carregar resumo…'}</small></span><span>›</span></button></> : <p>Consulte o responsável para obter acesso à operação.</p>}</section>
    <section className="card recent-work"><div className="section-title"><h2><Icon name="clipboard"/>Trabalhos recentes</h2>{canField && <button onClick={() => onNavigate('work-logs')}>Ver trabalhos →</button>}</div><div className="table-wrap"><table><thead><tr><th>Cliente</th><th>Serviço</th><th>Controlador</th><th>Data</th><th>Estado</th></tr></thead><tbody>{data?.logs.items.slice(0, 5).map(item => <tr key={item.id}><td><strong>{item.client_name}</strong></td><td>{item.service_name}</td><td>{item.responsible_name ?? item.team_name}</td><td>{formatDate(item.work_date)}</td><td><span className={`status-pill ${item.status}`}>{item.status === 'closed' ? 'Fechado' : item.status === 'draft' ? 'Rascunho' : 'Cancelado'}</span></td></tr>)}{!data?.logs.items.length && <tr><td colSpan={5}>{error ? 'Resumo indisponível.' : data ? 'Ainda não existem trabalhos para apresentar.' : 'A carregar trabalhos…'}</td></tr>}</tbody></table></div></section>{user.permissions.includes('finance.manage') && <section className="card"><h2><Icon name="prices"/>Pagamentos</h2><div className="financial-row"><span>A pagar</span><strong>{data?.finance ? formatCents(data.finance.pending_cents) : '—'}</strong></div><div className="financial-row"><span>Pago</span><strong>{data?.finance ? formatCents(data.finance.paid_cents) : '—'}</strong></div><div className="financial-row"><span>Recebido nos contratos</span><strong>{data?.finance ? formatCents(data.finance.received_cents ?? 0) : '—'}</strong></div><div className="financial-row"><span>Saldo de caixa dos contratos</span><strong>{data?.finance ? formatCents(data.finance.cash_cents ?? 0) : '—'}</strong></div></section>}</div>
    <div className="mobile-catalog card"><h2>Gerir operação</h2><div>{user.permissions.includes('finance.manage')&&<button onClick={()=>onNavigate('finance')}><Icon name="prices"/>Produção e pagamentos</button>}{user.permissions.includes('performance.view')&&<button onClick={()=>onNavigate('performance')}><Icon name="chart"/>Desempenho</button>}{user.permissions.includes('reports.view') && <button onClick={() => onNavigate('reports')}><Icon name="chart"/>Relatórios</button>}{user.permissions.includes('users.view') && <button onClick={() => onNavigate('users')}><Icon name="teams"/>Utilizadores e permissões</button>}{canCatalog && ['workers', 'clients'].map((key, index) => <button key={key} onClick={() => onNavigate(key)}><Icon name={key}/>{['Trabalhadores', 'Clientes'][index]}</button>)}{user.permissions.includes('finance.manage') && <button onClick={()=>onNavigate('harvests')}><Icon name="services"/>Contratos</button>}</div></div>
  </>
}
