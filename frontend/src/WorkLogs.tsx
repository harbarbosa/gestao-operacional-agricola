import { useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError } from './api'
import Modal from './Modal'
import type {Harvest} from './Harvests'
import ProductionPhotos from './ProductionPhotos'
import { formatDate, formatTime, formatNumber, maskDecimal, formatCents, statusLabel } from './locale'
import { Icon } from './Icons'
import Participants from './Participants'
import { attendeePayload, type AttendeeForm, type WorkLog, type WorkLogList } from './field'
import { canonicalDecimal } from './pricing'

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Lisbon' }).format(new Date())
type Props = { admin?: boolean; onSessionExpired: () => void; onBusyChange: (value: boolean) => void }
type UnitContext = { version: number; payment: string; billing: string }

function DraftForm({ item, onSaved, onCancel, onSessionExpired, onBusyChange }: Props & { item: WorkLog | null; onSaved: () => void; onCancel: () => void }) {
  const [requestId] = useState(() => crypto.randomUUID())
  const [date, setDate] = useState(item?.work_date ?? today)
  const [client, setClient] = useState(String(item?.client_id ?? ''))
  const [site, setSite] = useState(String(item?.site_id ?? ''))
  const [harvest, setHarvest] = useState(String(item?.harvest_id ?? ''))
  const [harvests, setHarvests] = useState<Harvest[]>([])
  const [service, setService] = useState(String(item?.service_id ?? ''))
  const [notes, setNotes] = useState(item?.notes ?? '')
  const [billing, setBilling] = useState(item?.billing_quantity ? maskDecimal(item.billing_quantity) : '')
  const [pools, setPools] = useState<{pool_key:string;name:string;quantity:string}[]>(item?.pools ?? [])
  const [rows, setRows] = useState<AttendeeForm[]>(() => item?.attendees.map(row => ({ key: crypto.randomUUID(), poolKey: row.pool_key ?? '', worker: String(row.worker_id), present: row.present, timeIn: row.time_in ?? '', timeOut: row.time_out ?? '', nextDay: row.exit_next_day, quantity: row.payment_quantity ? maskDecimal(row.payment_quantity) : '', score: String(row.score ?? ''), notes: row.notes ?? '' })) ?? [])
  const [participantNames] = useState<Record<string,string>>(()=>Object.fromEntries(item?.attendees.map(row=>[String(row.worker_id),row.worker_name])??[]))
  const [context, setContext] = useState<UnitContext | null>(item ? { version: 0, payment: item.payment_unit_name, billing: item.billing_unit_name } : null)
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => { if(!item) api<{items:Harvest[]}>('/harvests').then(r=>setHarvests(r.items.filter(h=>h.status==='active'))).catch(()=>setMessage('Não foi possível carregar colheitas.')) },[item])
  async function save(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); onBusyChange(true); setMessage('')
    try {
      if (!context) throw new Error('Selecione e confirme o serviço antes de guardar.')
      const contents = { pools: pools.map(pool => ({...pool, quantity: canonicalDecimal(pool.quantity)})), notes, billing_quantity: billing ? canonicalDecimal(billing) : null, attendees: rows.map(attendeePayload) }
      const payload = item ? { ...contents, version: item.version } : { ...contents, request_id: requestId, work_date: date, client_id: Number(client), site_id: Number(site), harvest_id: Number(harvest), service_id: Number(service), service_version: context.version }
      await api(`/work-logs${item ? `/${item.id}` : ''}`, { method: item ? 'PUT' : 'POST', body: JSON.stringify(payload) })
      onSaved()
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else setMessage(error instanceof TypeError ? 'Não foi possível confirmar a gravação. Consulte a listagem ou repita sem alterar os dados; o identificador deste envio foi preservado.' : error instanceof Error ? error.message : 'Falha ao guardar.')
    } finally { setBusy(false); onBusyChange(false) }
  }
  const hasQuantities = !!billing || rows.some(row => !!row.quantity)
  return <form className="record-form" onSubmit={save}>
    <h3>{item ? `Editar diário #${item.id}` : 'Novo diário em rascunho'}</h3>
    {item ? <p><strong>{formatDate(item.work_date)}</strong> · {item.client_name} · {item.site_name}<br />{item.responsible_name ?? item.team_name} · {item.service_name}</p> : <>
      <label htmlFor="work-date">Data do trabalho</label><input id="work-date" type="date" lang="pt-PT" required disabled={busy} value={date} onChange={e => setDate(e.target.value)} />
      <label htmlFor="work-harvest">Colheita</label><select id="work-harvest" required disabled={busy || hasQuantities || rows.length>0} value={harvest} onChange={async e=>{const value=e.target.value;setHarvest(value);setContext(null);try{const h=(await api<{item:Harvest}>(`/harvests/${value}`)).item;setClient(String((h as unknown as {client_id:number}).client_id));setSite(String((h as unknown as {site_id:number}).site_id));setService(String((h as unknown as {service_id:number}).service_id));setContext({version:1,payment:h.payment_unit_name,billing:h.billing_unit_name})}catch{setMessage('Não foi possível abrir a colheita.')}}}><option value="">Selecionar colheita</option>{harvests.map(h=><option key={h.id} value={h.id}>{h.name} · {h.client_name}</option>)}</select>

    </>}
    <h3>Grupos de colheita</h3>
    {pools.map(pool => <fieldset key={pool.pool_key} disabled={busy}><legend>{pool.name}</legend><label htmlFor={`pool-name-${pool.pool_key}`}>Nome do grupo</label><input id={`pool-name-${pool.pool_key}`} required maxLength={100} value={pool.name} onChange={event => setPools(old => old.map(p => p.pool_key === pool.pool_key ? {...p,name:event.target.value} : p))} /><label htmlFor={`pool-qty-${pool.pool_key}`}>Produção conjunta ({context?.payment ?? 'unidade'})</label><input id={`pool-qty-${pool.pool_key}`} required inputMode="decimal" value={pool.quantity} onChange={event => setPools(old => old.map(p => p.pool_key === pool.pool_key ? {...p,quantity:maskDecimal(event.target.value)} : p))} /><button type="button" onClick={() => { setPools(old=>old.filter(p=>p.pool_key!==pool.pool_key)); setRows(old=>old.map(r=>r.poolKey===pool.pool_key?{...r,poolKey:''}:r)) }}>Remover grupo</button></fieldset>)}
    <button type="button" disabled={busy || pools.length >= 50} onClick={() => setPools(old=>[...old,{pool_key:crypto.randomUUID().replaceAll('-','').slice(0,16),name:`Grupo ${old.length+1}`,quantity:''}])}>Adicionar grupo de divisão igual</button>
    <h3>Participantes</h3>
    <Participants rows={rows} onChange={setRows} pools={pools} unit={context?.payment ?? 'unidade'} disabled={busy} canAdd={!!item || !!harvest} initialNames={participantNames}/>
    <label htmlFor="work-billing">Quantidade total para o cliente ({context?.billing ?? 'unidade a confirmar'})</label><input id="work-billing" inputMode="decimal" maxLength={20} value={billing} disabled={busy || !context} onChange={e => setBilling(maskDecimal(e.target.value))} />
    <label htmlFor="work-notes">Observações do diário</label><textarea id="work-notes" maxLength={2000} disabled={busy} value={notes} onChange={e => setNotes(e.target.value)} />
    <p role="status" aria-live="polite">{message}</p><div className="actions"><button disabled={busy || !context}>{busy ? 'A guardar…' : 'Guardar rascunho'}</button><button type="button" disabled={busy} onClick={onCancel}>Voltar à listagem</button></div>
  </form>
}

function ReadOnlyLog({ item, onBack }: { item: WorkLog; onBack: () => void }) {
  return <section className="record-form"><h3>Atividade {statusLabel(item.status).toLowerCase()} #{item.id}</h3><p>{formatDate(item.work_date)} · {item.responsible_name ?? item.team_name}<br />{item.client_name} · {item.site_name} · {item.service_name}</p>
    {item.cancel_reason && <p><strong>Motivo:</strong> {item.cancel_reason}</p>}<p>{item.notes}</p><p>Quantidade do cliente: {formatNumber(item.billing_quantity)} {item.billing_unit_name}</p>
    <div className="table-wrap"><table><thead><tr><th>Trabalhador</th><th>Presença / horários</th><th>Quantidade</th><th>Avaliação / observações</th></tr></thead><tbody>{item.attendees.map(row => <tr key={row.worker_id}><td>{row.worker_name}</td><td>{row.present ? 'Presente' : 'Ausente'}<br />{formatTime(row.time_in)} a {formatTime(row.time_out)}{row.exit_next_day ? ' (dia seguinte)' : ''}</td><td>{formatNumber(row.payment_quantity)} {item.payment_unit_name}</td><td>{row.score ?? 'Sem avaliação'}<br />{row.notes}</td></tr>)}</tbody></table></div><button onClick={onBack}>Voltar à listagem</button>
  </section>
}

export default function WorkLogs({ admin = false, onSessionExpired, onBusyChange }: Props) {
  const [items, setItems] = useState<WorkLog[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [date, setDate] = useState('')
  const [queryDate, setQueryDate] = useState('')
  const [reload, setReload] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [editor, setEditor] = useState<WorkLog | 'new' | null>(null)
  const [canceling, setCanceling] = useState<WorkLog | null>(null)
  const [reason, setReason] = useState('')
  const [closing, setClosing] = useState<{item:WorkLog;preview_hash:string;allocations:{worker_id:number;worker_name:string;pool_name:string|null;rate:string;amount_cents:number}[]} | null>(null)
  useEffect(() => {
    let current = true
    api<WorkLogList>(`/work-logs?page=${page}${queryDate ? `&date=${queryDate}` : ''}`).then(data => { if (current) { setItems(data.items); setTotal(data.total); setLoading(false) } }).catch(error => {
      if (!current) return
      setItems([]); setLoading(false)
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else setMessage(error instanceof Error ? error.message : 'Falha ao carregar diário.')
    })
    return () => { current = false }
  }, [page, queryDate, reload, onSessionExpired])
  function refresh() { setLoading(true); setReload(value => value + 1) }
  async function open(item: WorkLog) {
    setBusy(true); onBusyChange(true); setMessage('')
    try { setEditor((await api<{ item: WorkLog }>(`/work-logs/${item.id}`)).item); setCanceling(null) }
    catch (error) { if (error instanceof ApiError && error.status === 401) onSessionExpired(); else setMessage('Não foi possível abrir o diário.') }
    finally { setBusy(false); onBusyChange(false) }
  }
  async function previewClose(item: WorkLog) {
    setBusy(true); onBusyChange(true); setMessage('')
    try { if(item.harvest_id) { await api(`/work-logs/${item.id}/submit`,{method:'POST',body:JSON.stringify({version:item.version})});setMessage('Dia encerrado e enviado para validação.');refresh() } else if(admin) setClosing(await api(`/work-logs/${item.id}/close-preview`)) }
    catch (error) { if (error instanceof ApiError && error.status===401) onSessionExpired(); else setMessage(error instanceof Error ? error.message : 'Não foi possível conferir o fecho.') }
    finally { setBusy(false); onBusyChange(false) }
  }
  async function validateDay(item:WorkLog, action:'validate'|'return') {
    setBusy(true);onBusyChange(true);setMessage('');try {await api(`/work-logs/${item.id}/${action}`,{method:'POST',body:JSON.stringify({version:item.version})});refresh()}catch(error){if(error instanceof ApiError && error.status===401)onSessionExpired();else setMessage(error instanceof Error?error.message:'Falha na validação.')}finally{setBusy(false);onBusyChange(false)}
  }
  async function closeActivity() {
    if(!closing) return
    setBusy(true); onBusyChange(true); setMessage('')
    try { await api(`/work-logs/${closing.item.id}/close`, {method:'POST',body:JSON.stringify({version:closing.item.version,preview_hash:closing.preview_hash})}); setClosing(null); setMessage('Atividade fechada.'); refresh() }
    catch(error) { if(error instanceof ApiError && error.status===401) onSessionExpired(); else setMessage(error instanceof Error ? error.message : 'Não foi possível fechar.') }
    finally { setBusy(false); onBusyChange(false) }
  }
  async function cancel(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); if (!canceling) return
    setBusy(true); onBusyChange(true); setMessage('')
    try { await api(`/work-logs/${canceling.id}/cancel`, { method: 'POST', body: JSON.stringify({ version: canceling.version, reason }) }); setCanceling(null); setMessage('Diário cancelado com histórico preservado.'); refresh() }
    catch (error) { if (error instanceof ApiError && error.status === 401) onSessionExpired(); else { setMessage(error instanceof Error ? error.message : 'Falha ao cancelar.'); refresh() } }
    finally { setBusy(false); onBusyChange(false) }
  }
  return <section className="catalog"><div className="section-title"><h2>Diário de campo</h2><button disabled={busy || !!editor} onClick={() => { setEditor('new'); setCanceling(null) }}>Novo diário</button></div>
    {closing && <Modal title={`Fechar atividade #${closing.item.id}`} busy={busy} onClose={()=>setClosing(null)}><p>Confirme os valores de cada trabalhador. Grupos usam a tarifa padrão do serviço e divisão igual.</p><div className="table-wrap"><table><thead><tr><th>Trabalhador</th><th>Grupo</th><th>Valor</th></tr></thead><tbody>{closing.allocations.map(row=><tr key={row.worker_id}><td>{row.worker_name}</td><td>{row.pool_name ?? 'Individual'}</td><td>{formatCents(row.amount_cents)}</td></tr>)}</tbody></table></div><p role="status">{message}</p><div className="actions"><button disabled={busy} onClick={closeActivity}>Confirmar fecho</button><button disabled={busy} onClick={()=>setClosing(null)}>Voltar</button></div></Modal>}
    {editor && <Modal title={editor === 'new' ? 'Novo diário de campo' : `Diário #${editor.id}`} busy={busy} onClose={() => { setEditor(null); refresh() }}>{(editor !== 'new' && editor.status !== 'draft' ? <ReadOnlyLog item={editor} onBack={() => setEditor(null)} /> : <DraftForm key={editor === 'new' ? 'new' : editor.id} item={editor === 'new' ? null : editor} onSessionExpired={onSessionExpired} onBusyChange={value => { setBusy(value); onBusyChange(value) }} onSaved={() => { setEditor(null); setMessage('Rascunho guardado.'); refresh() }} onCancel={() => { setEditor(null); refresh() }} />)}{editor !== 'new' && <ProductionPhotos item={editor} onSessionExpired={onSessionExpired} onBusyChange={value=>{setBusy(value);onBusyChange(value)}}/>}</Modal>}
    <>
      <form className="search" onSubmit={event => { event.preventDefault(); setPage(1); setQueryDate(date); refresh() }}><label htmlFor="diary-filter">Filtrar por data</label><input id="diary-filter" type="date" lang="pt-PT" value={date} disabled={busy} onChange={e => setDate(e.target.value)} /><button disabled={busy}>Filtrar</button></form>
      {loading ? <p>A carregar…</p> : <><div className="table-wrap"><table><thead><tr><th>Data / controlador</th><th>Cliente / local</th><th>Serviço / estado</th><th>Ações</th></tr></thead><tbody>
        {items.map(item => <tr key={item.id}><td>{formatDate(item.work_date)}<br />{item.responsible_name ?? item.team_name}</td><td>{item.client_name}<br />{item.site_name}</td><td>{item.service_name}<br />{statusLabel(item.status)}{item.cancel_reason && <><br />{item.cancel_reason}</>}</td><td><div className="row-actions">{item.status === 'submitted' && admin && <><button disabled={busy} onClick={()=>validateDay(item,'validate')}>Validar</button><button disabled={busy} onClick={()=>validateDay(item,'return')}>Devolver</button></>}{item.status === 'draft' && (!!item.harvest_id || admin) && <button className="icon-action" title="Encerrar dia" aria-label={`Fechar atividade ${item.id}`} disabled={busy} onClick={() => previewClose(item)}><Icon name="end" /></button>}<button className="icon-action" title={item.status === 'draft' ? 'Editar' : 'Consultar'} aria-label={`${item.status === 'draft' ? 'Editar' : 'Consultar'} diário ${item.id}`} disabled={busy} onClick={() => open(item)}><Icon name={item.status === 'draft' ? 'edit' : 'view'} /></button><button className="icon-action danger" title="Excluir (cancelar com histórico)" aria-label={`Cancelar diário ${item.id}`} disabled={busy || item.status !== 'draft'} onClick={() => { setCanceling(item); setReason('') }}><Icon name="trash" /></button></div></td></tr>)}
        {!items.length && <tr><td colSpan={4}>Nenhum diário encontrado.</td></tr>}
      </tbody></table></div><div className="actions"><button disabled={busy || page === 1} onClick={() => { setLoading(true); setPage(value => value - 1) }}>Anterior</button><span>Página {page}</span><button disabled={busy || page * 25 >= total} onClick={() => { setLoading(true); setPage(value => value + 1) }}>Seguinte</button><button disabled={busy} onClick={refresh}>Recarregar</button></div></>}
      {canceling && <Modal title={`Cancelar diário #${canceling.id}`} busy={busy} onClose={() => setCanceling(null)}><p role="status" aria-live="polite">{message}</p><form className="record-form" onSubmit={cancel}><h3>Cancelar diário #{canceling.id}</h3><label htmlFor="diary-reason">Motivo</label><textarea id="diary-reason" required minLength={3} maxLength={500} value={reason} disabled={busy} onChange={e => setReason(e.target.value)} /><div className="actions"><button disabled={busy}>Confirmar cancelamento</button><button disabled={busy} type="button" onClick={() => setCanceling(null)}>Voltar</button></div></form></Modal>}
    </>
    <p role="status" aria-live="polite">{message}</p>
  </section>
}
