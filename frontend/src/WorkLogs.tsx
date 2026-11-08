import { useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError } from './api'
import { ReferenceField } from './Catalog'
import type { CatalogItem } from './catalog'
import { attendeePayload, type AttendeeForm, type WorkLog, type WorkLogList } from './field'
import { canonicalDecimal } from './pricing'

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Lisbon' }).format(new Date())
type Props = { onSessionExpired: () => void; onBusyChange: (value: boolean) => void }
type UnitContext = { version: number; payment: string; billing: string }

function DraftForm({ item, onSaved, onCancel, onSessionExpired, onBusyChange }: Props & { item: WorkLog | null; onSaved: () => void; onCancel: () => void }) {
  const [requestId] = useState(() => crypto.randomUUID())
  const [date, setDate] = useState(item?.work_date ?? today)
  const [client, setClient] = useState(String(item?.client_id ?? ''))
  const [site, setSite] = useState(String(item?.site_id ?? ''))
  const [team, setTeam] = useState(String(item?.team_id ?? ''))
  const [service, setService] = useState(String(item?.service_id ?? ''))
  const [notes, setNotes] = useState(item?.notes ?? '')
  const [billing, setBilling] = useState(item?.billing_quantity ?? '')
  const [rows, setRows] = useState<AttendeeForm[]>(() => item?.attendees.map(row => ({ key: crypto.randomUUID(), worker: String(row.worker_id), present: row.present, timeIn: row.time_in ?? '', timeOut: row.time_out ?? '', nextDay: row.exit_next_day, quantity: row.payment_quantity ?? '', score: String(row.score ?? ''), notes: row.notes ?? '' })) ?? [])
  const [context, setContext] = useState<UnitContext | null>(item ? { version: 0, payment: item.payment_unit_name, billing: item.billing_unit_name } : null)
  const [review, setReview] = useState(0)
  const [message, setMessage] = useState('')
  const [busy, setBusy] = useState(false)
  useEffect(() => {
    if (item || !service) return
    let current = true
    api<{ item: CatalogItem }>(`/catalog/services/${service}`).then(async ({ item: selected }) => {
      const [pay, bill] = await Promise.all([api<{ item: CatalogItem }>(`/catalog/units/${selected.payment_unit_id}`), api<{ item: CatalogItem }>(`/catalog/units/${selected.billing_unit_id}`)])
      if (current) setContext({ version: selected.version, payment: pay.item.name, billing: bill.item.name })
    }).catch(error => {
      if (!current) return
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else setMessage('Não foi possível confirmar o serviço e as unidades.')
    })
    return () => { current = false }
  }, [item, service, review, onSessionExpired])
  function updateRow(key: string, changes: Partial<AttendeeForm>) { setRows(old => old.map(row => row.key === key ? { ...row, ...changes } : row)) }
  function addRow() { setRows(old => [...old, { key: crypto.randomUUID(), worker: '', present: true, timeIn: '', timeOut: '', nextDay: false, quantity: '', score: '', notes: '' }]) }
  async function save(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); onBusyChange(true); setMessage('')
    try {
      if (!context) throw new Error('Selecione e confirme o serviço antes de guardar.')
      const contents = { notes, billing_quantity: billing ? canonicalDecimal(billing) : null, attendees: rows.map(attendeePayload) }
      const payload = item ? { ...contents, version: item.version } : { ...contents, request_id: requestId, work_date: date, client_id: Number(client), site_id: Number(site), team_id: Number(team), service_id: Number(service), service_version: context.version }
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
    <p>Guarde presenças, horários e quantidades. Este rascunho não gera pagamentos nem cobranças.</p>
    {item ? <p><strong>{item.work_date}</strong> · {item.client_name} · {item.site_name}<br />{item.team_name} · {item.service_name}</p> : <>
      <label htmlFor="work-date">Data do trabalho</label><input id="work-date" type="date" required disabled={busy} value={date} onChange={e => setDate(e.target.value)} />
      <label htmlFor="work-client">Cliente</label><ReferenceField field={{ key: 'work-client', label: 'Cliente', resource: 'clients', required: true }} value={client} onChange={value => { setClient(value); setSite('') }} disabled={busy} />
      <label htmlFor="work-site">Local / exploração agrícola</label><ReferenceField key={client} field={{ key: 'work-site', label: 'Local', resource: 'sites', required: true, query: client ? `client_id=${client}` : undefined }} value={site} onChange={setSite} disabled={busy || !client} />
      <label htmlFor="work-team">Equipa</label><ReferenceField field={{ key: 'work-team', label: 'Equipa', resource: 'teams', required: true }} value={team} onChange={setTeam} disabled={busy || rows.length > 0} />
      {rows.length > 0 && <small>Remova os participantes antes de trocar de equipa.</small>}
      <label htmlFor="work-service">Serviço</label><ReferenceField field={{ key: 'work-service', label: 'Serviço', resource: 'services', required: true }} value={service} onChange={value => { setService(value); setContext(null) }} disabled={busy || hasQuantities} />
      {service && <button type="button" disabled={busy} onClick={() => { setBilling(''); setRows(old => old.map(row => ({ ...row, quantity: '' }))); setContext(null); setReview(value => value + 1) }}>Rever unidades e limpar quantidades</button>}
    </>}
    <h3>Participantes</h3>
    <p>Selecione trabalhadores da equipa. A quantidade individual é indicada em {context?.payment ?? 'unidades a confirmar'}; não é calculada automaticamente a partir dos horários.</p>
    {rows.map((row, index) => <fieldset key={row.key} disabled={busy}><legend>Participante {index + 1}</legend>
      <label htmlFor={`worker-${row.key}`}>Trabalhador</label><ReferenceField field={{ key: `worker-${row.key}`, label: 'Trabalhador da equipa', resource: 'workers', required: true, query: `team_id=${team}` }} value={row.worker} onChange={value => updateRow(row.key, { worker: value })} disabled={busy} />
      <label className="checkbox"><input type="checkbox" checked={row.present} onChange={e => updateRow(row.key, { present: e.target.checked, ...(!e.target.checked ? { timeIn: '', timeOut: '', nextDay: false, quantity: '', score: '' } : {}) })} />Presente</label>
      <div className="field-grid"><div><label htmlFor={`in-${row.key}`}>Entrada</label><input id={`in-${row.key}`} type="time" value={row.timeIn} disabled={!row.present} onChange={e => updateRow(row.key, { timeIn: e.target.value })} /></div><div><label htmlFor={`out-${row.key}`}>Saída</label><input id={`out-${row.key}`} type="time" value={row.timeOut} disabled={!row.present} onChange={e => updateRow(row.key, { timeOut: e.target.value })} /></div></div>
      <label className="checkbox"><input type="checkbox" checked={row.nextDay} disabled={!row.present} onChange={e => updateRow(row.key, { nextDay: e.target.checked })} />Saída no dia seguinte</label>
      <label htmlFor={`quantity-${row.key}`}>Quantidade ({context?.payment ?? 'unidade a confirmar'})</label><input id={`quantity-${row.key}`} inputMode="decimal" maxLength={13} value={row.quantity} disabled={!row.present || !context} onChange={e => updateRow(row.key, { quantity: e.target.value })} />
      <label htmlFor={`score-${row.key}`}>Avaliação (opcional)</label><select id={`score-${row.key}`} value={row.score} disabled={!row.present} onChange={e => updateRow(row.key, { score: e.target.value })}><option value="">Sem avaliação</option>{[1, 2, 3, 4, 5].map(score => <option key={score} value={score}>{score}</option>)}</select>
      <label htmlFor={`notes-${row.key}`}>Observações individuais</label><textarea id={`notes-${row.key}`} maxLength={500} value={row.notes} onChange={e => updateRow(row.key, { notes: e.target.value })} /><button type="button" onClick={() => setRows(old => old.filter(item => item.key !== row.key))}>Remover participante</button>
    </fieldset>)}
    <button type="button" disabled={busy || !team || rows.length >= 100} onClick={addRow}>Adicionar participante</button>
    <label htmlFor="work-billing">Quantidade total para o cliente ({context?.billing ?? 'unidade a confirmar'})</label><input id="work-billing" inputMode="decimal" maxLength={13} value={billing} disabled={busy || !context} onChange={e => setBilling(e.target.value)} />
    <small>É independente das quantidades individuais; não é somada automaticamente.</small>
    <label htmlFor="work-notes">Observações do diário</label><textarea id="work-notes" maxLength={2000} disabled={busy} value={notes} onChange={e => setNotes(e.target.value)} />
    <p role="status" aria-live="polite">{message}</p><div className="actions"><button disabled={busy || !context}>{busy ? 'A guardar…' : 'Guardar rascunho'}</button><button type="button" disabled={busy} onClick={onCancel}>Voltar à listagem</button></div>
    <small>{item ? 'Para corrigir data, equipa, local ou serviço, cancele este rascunho e crie outro. Em caso de conflito, reabra a versão atualizada na listagem.' : 'Reenvios com o mesmo identificador e dados são reconhecidos. Esta versão ainda não guarda rascunhos de formulário offline.'}</small>
  </form>
}

function ReadOnlyLog({ item, onBack }: { item: WorkLog; onBack: () => void }) {
  return <section className="record-form"><h3>Diário cancelado #{item.id}</h3><p>{item.work_date} · {item.team_name}<br />{item.client_name} · {item.site_name} · {item.service_name}</p>
    <p><strong>Motivo:</strong> {item.cancel_reason}</p><p>{item.notes}</p><p>Quantidade do cliente: {item.billing_quantity ?? 'Não indicada'} {item.billing_unit_name}</p>
    <div className="table-wrap"><table><thead><tr><th>Trabalhador</th><th>Presença / horários</th><th>Quantidade</th><th>Avaliação / observações</th></tr></thead><tbody>{item.attendees.map(row => <tr key={row.worker_id}><td>{row.worker_name}</td><td>{row.present ? 'Presente' : 'Ausente'}<br />{row.time_in ?? '—'} a {row.time_out ?? '—'}{row.exit_next_day ? ' (dia seguinte)' : ''}</td><td>{row.payment_quantity ?? '—'} {item.payment_unit_name}</td><td>{row.score ?? 'Sem avaliação'}<br />{row.notes}</td></tr>)}</tbody></table></div><button onClick={onBack}>Voltar à listagem</button>
  </section>
}

export default function WorkLogs({ onSessionExpired, onBusyChange }: Props) {
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
  async function cancel(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); if (!canceling) return
    setBusy(true); onBusyChange(true); setMessage('')
    try { await api(`/work-logs/${canceling.id}/cancel`, { method: 'POST', body: JSON.stringify({ version: canceling.version, reason }) }); setCanceling(null); setMessage('Diário cancelado com histórico preservado.'); refresh() }
    catch (error) { if (error instanceof ApiError && error.status === 401) onSessionExpired(); else { setMessage(error instanceof Error ? error.message : 'Falha ao cancelar.'); refresh() } }
    finally { setBusy(false); onBusyChange(false) }
  }
  return <section className="catalog"><div className="section-title"><h2>Diário de campo</h2><button disabled={busy || !!editor} onClick={() => { setEditor('new'); setCanceling(null) }}>Novo diário</button></div>
    <p>Acesso inicial do administrador. Os registos ficam em rascunho, sem fechamento financeiro.</p>
    {editor ? (editor !== 'new' && editor.status === 'canceled' ? <ReadOnlyLog item={editor} onBack={() => setEditor(null)} /> : <DraftForm key={editor === 'new' ? 'new' : editor.id} item={editor === 'new' ? null : editor} onSessionExpired={onSessionExpired} onBusyChange={value => { setBusy(value); onBusyChange(value) }} onSaved={() => { setEditor(null); setMessage('Rascunho guardado.'); refresh() }} onCancel={() => { setEditor(null); refresh() }} />) : <>
      <form className="search" onSubmit={event => { event.preventDefault(); setPage(1); setQueryDate(date); refresh() }}><label htmlFor="diary-filter">Filtrar por data</label><input id="diary-filter" type="date" value={date} disabled={busy} onChange={e => setDate(e.target.value)} /><button disabled={busy}>Filtrar</button></form>
      {loading ? <p>A carregar…</p> : <><div className="table-wrap"><table><thead><tr><th>Data / equipa</th><th>Cliente / local</th><th>Serviço / estado</th><th>Ações</th></tr></thead><tbody>
        {items.map(item => <tr key={item.id}><td>{item.work_date}<br />{item.team_name}</td><td>{item.client_name}<br />{item.site_name}</td><td>{item.service_name}<br />{item.status === 'draft' ? 'Rascunho' : 'Cancelado'}{item.cancel_reason && <><br />{item.cancel_reason}</>}</td><td><div className="actions"><button disabled={busy} onClick={() => open(item)}>{item.status === 'draft' ? 'Editar' : 'Consultar'}</button><button disabled={busy || item.status !== 'draft'} onClick={() => { setCanceling(item); setReason('') }}>Cancelar diário</button></div></td></tr>)}
        {!items.length && <tr><td colSpan={4}>Nenhum diário encontrado.</td></tr>}
      </tbody></table></div><div className="actions"><button disabled={busy || page === 1} onClick={() => { setLoading(true); setPage(value => value - 1) }}>Anterior</button><span>Página {page}</span><button disabled={busy || page * 25 >= total} onClick={() => { setLoading(true); setPage(value => value + 1) }}>Seguinte</button><button disabled={busy} onClick={refresh}>Recarregar</button></div></>}
      {canceling && <form className="record-form" onSubmit={cancel}><h3>Cancelar diário #{canceling.id}</h3><label htmlFor="diary-reason">Motivo</label><textarea id="diary-reason" required minLength={3} maxLength={500} value={reason} disabled={busy} onChange={e => setReason(e.target.value)} /><div className="actions"><button disabled={busy}>Confirmar cancelamento</button><button disabled={busy} type="button" onClick={() => setCanceling(null)}>Voltar</button></div></form>}
    </>}
    <p role="status" aria-live="polite">{message}</p>
  </section>
}
