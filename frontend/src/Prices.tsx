import { useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError } from './api'
import Modal from './Modal'
import { formatCurrency, formatDate, formatNumber, maskDecimal } from './locale'
import { Icon } from './Icons'
import { ReferenceField } from './Catalog'
import type { CatalogItem } from './catalog'
import { canonicalDecimal, scopeFor, type Price, type PriceKind, type PriceList, type PricePreview } from './pricing'

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Lisbon' }).format(new Date())
const kindLabel = (kind: PriceKind) => kind === 'payment' ? 'Pagamento' : 'Cobrança'
const scopeLabel = (price: Price) => price.worker_id ? `Trabalhador: ${price.worker_name ?? `#${price.worker_id}`}` : price.client_id ? `Cliente: ${price.client_name ?? `#${price.client_id}`}` : 'Padrão do serviço'

export default function Prices({ onSessionExpired, onBusyChange }: { onSessionExpired: () => void; onBusyChange: (value: boolean) => void }) {
  const [service, setService] = useState('')
  const serviceId = Number(service) || null
  const blankFilters = { q: '', service: '', kind: '', status: '' }
  const [filters, setFilters] = useState(blankFilters)
  const [applied, setApplied] = useState(blankFilters)
  const [simulating, setSimulating] = useState(false)
  const [creating, setCreating] = useState(false)
  const [items, setItems] = useState<Price[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [reload, setReload] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [fields, setFields] = useState<Record<string, string>>({})
  const [kind, setKind] = useState<PriceKind>('payment')
  const [target, setTarget] = useState('')
  const [amount, setAmount] = useState('')
  const [from, setFrom] = useState(today)
  const [to, setTo] = useState('')
  const [command, setCommand] = useState<{ price: Price; action: 'end' | 'cancel' } | null>(null)
  const [commandDate, setCommandDate] = useState('')
  const [reason, setReason] = useState('')
  const [previewKind, setPreviewKind] = useState<PriceKind>('payment')
  const [previewTarget, setPreviewTarget] = useState('')
  const [previewDate, setPreviewDate] = useState(today)
  const [quantity, setQuantity] = useState('1')
  const [result, setResult] = useState<PricePreview | null>(null)
  const [configuration, setConfiguration] = useState<{ serviceId: number; active: boolean; units: Record<PriceKind, { id: number; name: string; active: boolean }> } | null>(null)
  useEffect(() => {
    setConfiguration(null)
    if (!serviceId) return
    let current = true
    api<{ item: CatalogItem }>(`/catalog/services/${serviceId}`).then(async ({ item: service }) => {
      const [payment, billing] = await Promise.all([api<{ item: CatalogItem }>(`/catalog/units/${service.payment_unit_id}`), api<{ item: CatalogItem }>(`/catalog/units/${service.billing_unit_id}`)])
      if (current) setConfiguration({ serviceId, active: service.is_active, units: { payment: { id: payment.item.id, name: payment.item.name, active: payment.item.is_active }, billing: { id: billing.item.id, name: billing.item.name, active: billing.item.is_active } } })
    }).catch(() => { if (current) { setConfiguration(null); setMessage('Não foi possível confirmar as unidades do serviço. Recarregue antes de criar uma tarifa.') } })
    return () => { current = false }
  }, [serviceId, reload])
  useEffect(() => {
    let current = true
    const query = new URLSearchParams({ page: String(page) })
    if (applied.service) query.set('service_id', applied.service)
    if (applied.q) query.set('q', applied.q)
    if (applied.kind) query.set('kind', applied.kind)
    if (applied.status) query.set('status', applied.status)
    api<PriceList>(`/prices?${query}`).then(data => {
      if (current) { setItems(data.items); setTotal(data.total); setLoading(false) }
    }).catch(error => {
      if (!current) return
      setItems([]); setLoading(false)
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else setMessage(error instanceof Error ? error.message : 'Falha ao carregar tarifas.')
    })
    return () => { current = false }
  }, [applied, page, reload, onSessionExpired])
  async function execute(operation: () => Promise<void>) {
    setBusy(true); onBusyChange(true); setMessage(''); setFields({}); setResult(null)
    try { await operation() }
    catch (error) {
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else {
        setMessage(error instanceof Error ? error.message : 'Não foi possível confirmar a operação. Recarregue a listagem antes de tentar novamente.')
        if (error instanceof ApiError) { setFields(error.fields); if (error.status === 409) { setAmount(''); setLoading(true); setReload(value => value + 1) } }
      }
    } finally { setBusy(false); onBusyChange(false) }
  }
  function refresh() { setResult(null); setLoading(true); setReload(value => value + 1) }
  async function create(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    await execute(async () => {
      await api('/prices', { method: 'POST', body: JSON.stringify({ service_id: serviceId, unit_id: configuration?.units[kind].id, ...scopeFor(kind, target), amount: canonicalDecimal(amount), valid_from: from, valid_to: to || null }) })
      setCreating(false); setAmount(''); setMessage('Tarifa criada.'); refresh()
    })
  }
  async function change(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    if (!command) return
    await execute(async () => {
      await api(`/prices/${command.price.id}/${command.action}`, { method: 'POST', body: JSON.stringify({ version: command.price.version, ...(command.action === 'end' ? { valid_to: commandDate } : { reason }) }) })
      setCommand(null); setReason(''); setMessage('Vigência atualizada com histórico preservado.'); refresh()
    })
  }
  async function preview(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    await execute(async () => {
      const data = await api<PricePreview>('/prices/preview', { method: 'POST', body: JSON.stringify({ service_id: serviceId, ...scopeFor(previewKind, previewTarget), date: previewDate, quantity: canonicalDecimal(quantity) }) })
      setResult(data)
    })
  }
  function applyFilters(event: SubmitEvent<HTMLFormElement>) { event.preventDefault(); setPage(1); setApplied({ ...filters }); setLoading(true); setMessage('') }
  function clearFilters() { setFilters(blankFilters); setApplied(blankFilters); setPage(1); setLoading(true); setMessage('') }
  function openCreate() { setService(applied.service); setCreating(true); setMessage(''); setFields({}); setKind('payment'); setTarget(''); setAmount(''); setFrom(today()); setTo('') }
  return <section className="catalog">
    <div className="section-title"><h2>Serviços e preços</h2><div className="actions"><button disabled={busy} onClick={openCreate}>＋ Nova tarifa</button><button disabled={busy} onClick={() => { setService(applied.service); setSimulating(true); setMessage(''); setFields({}); setResult(null) }}>Simular valor</button></div></div>
    <form className="price-filters" onSubmit={applyFilters}>
      <div className="form-field"><label htmlFor="price-search">Pesquisar</label><input id="price-search" placeholder="Serviço, cliente, trabalhador ou unidade" maxLength={160} disabled={busy} value={filters.q} onChange={event => setFilters(old => ({ ...old, q: event.target.value }))} /></div>
      <div className="form-field"><label htmlFor="price-service-filter">Serviço</label><ReferenceField field={{ key: 'price-service-filter', label: 'Filtrar por serviço', resource: 'services' }} value={filters.service} onChange={value => setFilters(old => ({ ...old, service: value }))} disabled={busy} includeInactive emptyLabel="Todos os serviços" /></div>
      <div className="form-field"><label htmlFor="price-kind-filter">Finalidade</label><select id="price-kind-filter" value={filters.kind} disabled={busy} onChange={event => setFilters(old => ({ ...old, kind: event.target.value }))}><option value="">Todas</option><option value="payment">Pagamento</option><option value="billing">Cobrança</option></select></div>
      <div className="form-field"><label htmlFor="price-status-filter">Estado</label><select id="price-status-filter" value={filters.status} disabled={busy} onChange={event => setFilters(old => ({ ...old, status: event.target.value }))}><option value="">Todos</option><option value="available">Não anuladas</option><option value="canceled">Anuladas</option></select></div>
      <div className="actions"><button disabled={busy}>Filtrar</button><button type="button" disabled={busy} onClick={clearFilters}>Limpar filtros</button></div>
    </form>
    <p role="status" aria-live="polite">{message}</p>
    {Object.keys(fields).length > 0 && <ul role="alert">{Object.entries(fields).map(([field, text]) => <li key={field}>{text}</li>)}</ul>}
    {creating && <Modal title="Nova tarifa" busy={busy} onClose={() => setCreating(false)}><p role="status" aria-live="polite">{message}</p>{Object.keys(fields).length > 0 && <ul role="alert">{Object.entries(fields).map(([field, text]) => <li key={field}>{text}</li>)}</ul>}<form onSubmit={create} className="record-form">
      <label htmlFor="rate-service">Serviço</label><ReferenceField field={{ key: 'rate-service', label: 'Serviço da tarifa', resource: 'services', required: true }} value={service} onChange={value => { setService(value); setConfiguration(null) }} disabled={busy} />

      <label htmlFor="rate-kind">Finalidade</label><select id="rate-kind" value={kind} disabled={busy} onChange={e => { setKind(e.target.value as PriceKind); setTarget('') }}><option value="payment">Pagar ao trabalhador</option><option value="billing">Cobrar ao cliente</option></select>
      <label htmlFor="rate-target">{kind === 'payment' ? 'Trabalhador específico (opcional)' : 'Cliente específico (opcional)'}</label>
      <ReferenceField key={kind} field={{ key: 'rate-target', label: 'Âmbito da tarifa', resource: kind === 'payment' ? 'workers' : 'clients', type: 'reference' }} value={target} onChange={setTarget} disabled={busy} emptyLabel="Padrão do serviço" />
      <label htmlFor="rate-amount">Valor por {configuration?.units[kind].name ?? 'unidade'}</label><input id="rate-amount" inputMode="decimal" required maxLength={20} placeholder="Ex.: 12,5000" value={amount} disabled={busy} onChange={e => setAmount(maskDecimal(e.target.value))} />
      <label htmlFor="rate-from">Primeiro dia de vigência</label><input id="rate-from" type="date" lang="pt-PT" required value={from} disabled={busy} onChange={e => setFrom(e.target.value)} />
      <label htmlFor="rate-to">Último dia de vigência (opcional)</label><input id="rate-to" type="date" lang="pt-PT" min={from} value={to} disabled={busy} onChange={e => setTo(e.target.value)} />
      <button disabled={busy || !serviceId || configuration?.serviceId !== serviceId || !configuration?.active || !configuration.units[kind].active}>Criar tarifa</button>{configuration && (!configuration.active || !configuration.units[kind].active) && <p>O serviço ou a unidade estão inativos. Consulte o histórico ou ajuste o cadastro antes de criar uma tarifa.</p>}
      <button type="button" disabled={busy} onClick={() => setCreating(false)}>Cancelar</button>
    </form></Modal>}
    <p className="muted">{loading ? 'A carregar tarifas…' : `${total} tarifa(s)`}</p>
    {loading ? <p>A carregar…</p> : <><div className="table-wrap"><table><thead><tr><th>Serviço</th><th>Finalidade / âmbito</th><th>Valor</th><th>Vigência</th><th>Ações</th></tr></thead><tbody>
      {items.map(price => <tr key={price.id}><td><strong>{price.service_name ?? `Serviço #${price.service_id}`}</strong></td><td>{kindLabel(price.kind)}<br />{scopeLabel(price)}{price.canceled_at && <><br /><strong>Anulada</strong><br />{price.cancel_reason}</>}</td><td>{formatCurrency(price.amount)} / {price.unit_name}</td><td>{formatDate(price.valid_from)}<br />até {price.valid_to ? formatDate(price.valid_to) : 'sem fim definido'}</td><td><div className="row-actions"><button className="icon-action" title="Encerrar vigência" aria-label={`Encerrar tarifa ${price.id}`} disabled={busy || !!price.canceled_at} onClick={() => { setCommand({ price, action: 'end' }); setCommandDate(price.valid_to ?? price.valid_from) }}><Icon name="end" /></button><button className="icon-action danger" title="Excluir (anular com histórico)" aria-label={`Anular tarifa ${price.id}`} disabled={busy || !!price.canceled_at} onClick={() => { setCommand({ price, action: 'cancel' }); setReason('') }}><Icon name="trash" /></button></div></td></tr>)}
      {!items.length && <tr><td colSpan={5}>Nenhuma tarifa encontrada para os filtros aplicados.</td></tr>}
    </tbody></table></div><div className="actions"><button disabled={busy || page === 1} onClick={() => { setLoading(true); setPage(value => value - 1) }}>Anterior</button><span>Página {page}</span><button disabled={busy || page * 25 >= total} onClick={() => { setLoading(true); setPage(value => value + 1) }}>Seguinte</button><button disabled={busy} onClick={refresh}>Recarregar</button></div></>}
    {command && <Modal title={command.action === 'end' ? 'Encerrar vigência' : 'Anular tarifa'} busy={busy} onClose={() => setCommand(null)}><p role="status" aria-live="polite">{message}</p>{Object.keys(fields).length > 0 && <ul role="alert">{Object.entries(fields).map(([field, text]) => <li key={field}>{text}</li>)}</ul>}<form onSubmit={change} className="record-form"><h3>{command.action === 'end' ? 'Encerrar vigência' : 'Anular tarifa'} #{command.price.id}</h3>
      {command.action === 'end' ? <><label htmlFor="end-date">Último dia válido</label><input id="end-date" type="date" lang="pt-PT" required min={command.price.valid_from} max={command.price.valid_to ?? undefined} value={commandDate} disabled={busy} onChange={e => setCommandDate(e.target.value)} /></> : <><label htmlFor="cancel-reason">Motivo da anulação</label><textarea id="cancel-reason" required minLength={3} maxLength={500} value={reason} disabled={busy} onChange={e => setReason(e.target.value)} /></>}
      <div className="actions"><button disabled={busy}>Confirmar alteração</button><button type="button" disabled={busy} onClick={() => setCommand(null)}>Voltar</button></div></form></Modal>}
    {simulating && <Modal title="Simular valor" busy={busy} onClose={() => setSimulating(false)}><p role="status" aria-live="polite">{message}</p><form onSubmit={preview} className="record-form"><label htmlFor="preview-service">Serviço</label><ReferenceField field={{ key: 'preview-service', label: 'Serviço da simulação', resource: 'services', required: true }} value={service} onChange={value => { setService(value); setResult(null) }} disabled={busy} includeInactive />
      <label htmlFor="preview-kind">Finalidade</label><select id="preview-kind" value={previewKind} disabled={busy} onChange={e => { setPreviewKind(e.target.value as PriceKind); setPreviewTarget(''); setResult(null) }}><option value="payment">Pagamento</option><option value="billing">Cobrança</option></select>
      <label htmlFor="preview-target">{previewKind === 'payment' ? 'Trabalhador (opcional)' : 'Cliente (opcional)'}</label>
      <ReferenceField key={previewKind} field={{ key: 'preview-target', label: 'Destinatário da simulação', resource: previewKind === 'payment' ? 'workers' : 'clients', type: 'reference' }} value={previewTarget} onChange={value => { setPreviewTarget(value); setResult(null) }} disabled={busy} includeInactive emptyLabel="Consultar padrão do serviço" />
      <label htmlFor="preview-date">Data do trabalho</label><input id="preview-date" type="date" lang="pt-PT" required value={previewDate} disabled={busy} onChange={e => { setPreviewDate(e.target.value); setResult(null) }} />
      <label htmlFor="preview-quantity">Quantidade na unidade da tarifa</label><input id="preview-quantity" required inputMode="decimal" maxLength={20} value={quantity} disabled={busy} onChange={e => { setQuantity(maskDecimal(e.target.value)); setResult(null) }} /><button disabled={busy || !serviceId}>Simular</button>
      {result && <output aria-live="polite">{formatNumber(result.quantity)} {result.price.unit_name} × {formatCurrency(result.price.amount)} = <strong>{formatCurrency(result.total)}</strong><br />Tarifa #{result.price.id} · {scopeLabel(result.price)}</output>}
    </form></Modal>}
  </section>
}
