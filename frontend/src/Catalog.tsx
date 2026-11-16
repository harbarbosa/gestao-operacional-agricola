import { useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError } from './api'
import Modal from './Modal'
import WorkerPortrait from './WorkerPortrait'
import {formatNumber} from './locale'
import WorkerHistory from './WorkerHistory'
import { Icon } from './Icons'
import { catalogs, type CatalogItem, type CatalogList, type Resource } from './catalog'

export { default as ReferenceField } from './ReferenceField'
import CatalogEditor from './CatalogEditor'

export default function Catalog({ resource, onSessionExpired, onBusyChange,onInspect }: { onInspect?:(item:CatalogItem)=>void;resource: Resource; onSessionExpired: () => void; onBusyChange: (value: boolean) => void }) {
  const [workerHistory,setWorkerHistory]=useState<number|null>(null)
  const [rows, setRows] = useState<CatalogItem[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [state,setState]=useState('')
  const [query, setQuery] = useState('')
  const [reload, setReload] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [editing, setEditing] = useState<CatalogItem | null>(null)
  const [removing, setRemoving] = useState<CatalogItem | null>(null)
  const [open, setOpen] = useState(false)
  const [message, setMessage] = useState('')
  const config = catalogs[resource]
  useEffect(() => {
    let current = true
    api<CatalogList>(`/catalog/${resource}?page=${page}&q=${encodeURIComponent(query)}&state=${state}`)
      .then(data => { if (current) { setRows(data.items); setTotal(data.total); setLoading(false) } })
      .catch(error => {
        if (!current) return
        setRows([]); setLoading(false)
        if (error instanceof ApiError && error.status === 401) onSessionExpired()
        else setMessage(error instanceof Error ? error.message : 'Falha ao carregar cadastros.')
      })
    return () => { current = false }
  }, [resource, page, query,state, reload, onSessionExpired])
  function start(item: CatalogItem | null) {
    setEditing(item); setMessage(''); setOpen(true)
  }
  async function remove(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); if (!removing) return
    setBusy(true); onBusyChange(true); setMessage('')
    try {
      const result = await api<{ action: string; message: string }>(`/catalog/${resource}/${removing.id}`, { method: 'DELETE', body: JSON.stringify({ version: removing.version }) })
      setRemoving(null); setMessage(result.message); setLoading(true); setReload(count => count + 1)
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else { setMessage(error instanceof Error ? error.message : 'Não foi possível confirmar a remoção.'); setLoading(true); setReload(count => count + 1) }
    } finally { setBusy(false); onBusyChange(false) }
  }
  function filter(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); setLoading(true); setPage(1); setQuery(search); setReload(count => count + 1)
  }
  return <section className="catalog">
    <div className="section-title"><h2>{config.label}</h2><button onClick={() => start(null)} disabled={busy}>Novo registo</button></div>
    <form onSubmit={filter} className="search"><label htmlFor="catalog-search">Pesquisar por nome</label><input id="catalog-search" maxLength={160} value={search} onChange={e => setSearch(e.target.value)} /><label htmlFor="catalog-state">Estado</label><select id="catalog-state" value={state} onChange={e=>{setState(e.target.value);setPage(1);setLoading(true)}}><option value="">Todos</option><option value="active">Ativos</option><option value="inactive">Inativos</option>{resource==='workers'&&<><option value="blocked">Bloqueados</option><option value="low">Baixo desempenho</option></>}</select><button disabled={busy}>Pesquisar</button></form>
    <p role="status" aria-live="polite">{message}</p>
    {workerHistory!==null&&<WorkerHistory id={workerHistory} admin onClose={()=>setWorkerHistory(null)} onChanged={()=>setReload(x=>x+1)}/>}
    {open && <CatalogEditor resource={resource} item={editing} onSessionExpired={onSessionExpired} onBusyChange={value => { setBusy(value); onBusyChange(value) }} onClose={() => { setOpen(false); setLoading(true); setReload(count => count + 1) }} onSaved={() => { setOpen(false); setEditing(null); setMessage('Registo guardado.'); setLoading(true); setReload(count => count + 1) }} />}
    {removing && <Modal title="Excluir ou desativar registo" busy={busy} onClose={() => setRemoving(null)}><form onSubmit={remove}>
      <p>Deseja excluir <strong>{removing.name}</strong>?</p>
      <p role="status" aria-live="polite">{message}</p><div className="actions"><button disabled={busy}>{busy ? 'A processar…' : 'Confirmar'}</button><button type="button" disabled={busy} onClick={() => setRemoving(null)}>Cancelar</button></div>
    </form></Modal>}
    {loading ? <p>A carregar…</p> : <>
      <p>{total} registo(s).</p>
      <div className="table-wrap"><table><thead><tr><th>Nome</th>{['workers','clients'].includes(resource)&&<><th>Contacto</th>{resource==='workers'&&<th>Avaliação</th>}</>}<th>Estado</th><th>Ações</th></tr></thead><tbody>
        {rows.map(row => <tr key={row.id}><td>{onInspect?<button className="admin-person" onClick={()=>onInspect(row)}>{resource==='workers'&&<WorkerPortrait id={row.id} name={row.name} version={Number(row.photo_version??0)}/>}<strong>{row.name}</strong></button>:row.name}</td>{['workers','clients'].includes(resource)&&<><td>{String(row.phone??'—')}<small>{row.email}</small></td>{resource==='workers'&&<td>{row.performance_avg?`${formatNumber(String(row.performance_avg))}/5`: 'Sem histórico'}<small>{row.evaluation_count??0} avaliações</small></td>}</>}<td>{row.is_blocked ? <strong className="worker-blocked">Bloqueado</strong> : row.is_active ? 'Ativo' : 'Inativo'}</td><td><div className="row-actions">{resource==='workers'&&<button type="button" disabled={busy} onClick={()=>setWorkerHistory(row.id)}>Histórico / bloqueio</button>}<button className="icon-action" title="Editar" aria-label={`Editar ${row.name}`} disabled={busy} onClick={() => start(row)}><Icon name="edit" /></button><button className="icon-action danger" title="Excluir ou desativar" aria-label={`Excluir ou desativar ${row.name}`} disabled={busy} onClick={() => { setRemoving(row); setMessage('') }}><Icon name="trash" /></button></div></td></tr>)}
        {rows.length === 0 && <tr><td colSpan={6}>Nenhum registo encontrado.</td></tr>}
      </tbody></table></div>
      <div className="actions"><button disabled={page === 1 || busy} onClick={() => { setLoading(true); setPage(p => p - 1) }}>Anterior</button><span>Página {page}</span><button disabled={page * 25 >= total || busy} onClick={() => { setLoading(true); setPage(p => p + 1) }}>Seguinte</button></div>
    </>}
  </section>
}
