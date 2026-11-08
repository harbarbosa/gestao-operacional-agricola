import { useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError } from './api'
import { ReferenceField } from './Catalog'
import type { CatalogItem } from './catalog'
import { canonicalDecimal, scopeFor, type Price, type PriceKind, type PriceList, type PricePreview } from './pricing'

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Europe/Lisbon' }).format(new Date())
const kindLabel = (kind: PriceKind) => kind === 'payment' ? 'Pagamento' : 'Cobrança'
const scopeLabel = (price: Price) => price.worker_id ? `Trabalhador: ${price.worker_name ?? `#${price.worker_id}`}` : price.client_id ? `Cliente: ${price.client_name ?? `#${price.client_id}`}` : 'Padrão do serviço'

function PriceWorkspace({ serviceId, onSessionExpired, onBusyChange }: { serviceId: number; onSessionExpired: () => void; onBusyChange: (value: boolean) => void }) {
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
  const [configuration, setConfiguration] = useState<{ active: boolean; units: Record<PriceKind, { id: number; name: string; active: boolean }> } | null>(null)
  useEffect(() => {
    let current = true
    api<{ item: CatalogItem }>(`/catalog/services/${serviceId}`).then(async ({ item: service }) => {
      const [payment, billing] = await Promise.all([api<{ item: CatalogItem }>(`/catalog/units/${service.payment_unit_id}`), api<{ item: CatalogItem }>(`/catalog/units/${service.billing_unit_id}`)])
      if (current) setConfiguration({ active: service.is_active, units: { payment: { id: payment.item.id, name: payment.item.name, active: payment.item.is_active }, billing: { id: billing.item.id, name: billing.item.name, active: billing.item.is_active } } })
    }).catch(() => { if (current) { setConfiguration(null); setMessage('Não foi possível confirmar as unidades do serviço. Recarregue antes de criar uma tarifa.') } })
    return () => { current = false }
  }, [serviceId, reload])
  useEffect(() => {
    let current = true
    api<PriceList>(`/prices?service_id=${serviceId}&page=${page}`).then(data => {
      if (current) { setItems(data.items); setTotal(data.total); setLoading(false) }
    }).catch(error => {
      if (!current) return
      setItems([]); setLoading(false)
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else setMessage(error instanceof Error ? error.message : 'Falha ao carregar tarifas.')
    })
    return () => { current = false }
  }, [serviceId, page, reload, onSessionExpired])
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
      setAmount(''); setMessage('Tarifa criada.'); refresh()
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
  return <>
    <p role="status" aria-live="polite">{message}</p>
    {Object.keys(fields).length > 0 && <ul role="alert">{Object.entries(fields).map(([field, text]) => <li key={field}>{text}</li>)}</ul>}
    <form onSubmit={create} className="record-form">
      <h3>Nova tarifa</h3><p>Valores em EUR por unidade. As unidades são copiadas da configuração do serviço e preservadas nesta tarifa.</p>
      <label htmlFor="rate-kind">Finalidade</label><select id="rate-kind" value={kind} disabled={busy} onChange={e => { setKind(e.target.value as PriceKind); setTarget('') }}><option value="payment">Pagar ao trabalhador</option><option value="billing">Cobrar ao cliente</option></select>
      <label htmlFor="rate-target">{kind === 'payment' ? 'Trabalhador específico (opcional)' : 'Cliente específico (opcional)'}</label>
      <ReferenceField key={kind} field={{ key: 'rate-target', label: 'Âmbito da tarifa', resource: kind === 'payment' ? 'workers' : 'clients', type: 'reference' }} value={target} onChange={setTarget} disabled={busy} emptyLabel="Padrão do serviço" />
      <label htmlFor="rate-amount">Valor por {configuration?.units[kind].name ?? 'unidade'}</label><input id="rate-amount" inputMode="decimal" required maxLength={13} placeholder="Ex.: 12,5000" value={amount} disabled={busy} onChange={e => setAmount(e.target.value)} />
      <label htmlFor="rate-from">Primeiro dia de vigência</label><input id="rate-from" type="date" required value={from} disabled={busy} onChange={e => setFrom(e.target.value)} />
      <label htmlFor="rate-to">Último dia de vigência (opcional)</label><input id="rate-to" type="date" min={from} value={to} disabled={busy} onChange={e => setTo(e.target.value)} />
      <small>Datas inclusivas. Sem fim definido, a tarifa continua vigente. Tarifas do mesmo âmbito não podem sobrepor períodos.</small><button disabled={busy || !configuration?.active || !configuration.units[kind].active}>Criar tarifa</button>{configuration && (!configuration.active || !configuration.units[kind].active) && <p>O serviço ou a unidade estão inativos. Consulte o histórico ou ajuste o cadastro antes de criar uma tarifa.</p>}
    </form>
    <h3>Histórico de tarifas</h3>
    {loading ? <p>A carregar…</p> : <><div className="table-wrap"><table><thead><tr><th>Finalidade / âmbito</th><th>Valor</th><th>Vigência</th><th>Ações</th></tr></thead><tbody>
      {items.map(price => <tr key={price.id}><td>{kindLabel(price.kind)}<br />{scopeLabel(price)}{price.canceled_at && <><br /><strong>Anulada</strong><br />{price.cancel_reason}</>}</td><td>{price.amount.replace('.', ',')} EUR / {price.unit_name}</td><td>{price.valid_from}<br />até {price.valid_to ?? 'sem fim definido'}</td><td><div className="actions"><button disabled={busy || !!price.canceled_at} onClick={() => { setCommand({ price, action: 'end' }); setCommandDate(price.valid_to ?? price.valid_from) }}>Encerrar</button><button disabled={busy || !!price.canceled_at} onClick={() => { setCommand({ price, action: 'cancel' }); setReason('') }}>Anular</button></div></td></tr>)}
      {!items.length && <tr><td colSpan={4}>Nenhuma tarifa cadastrada para este serviço.</td></tr>}
    </tbody></table></div><div className="actions"><button disabled={busy || page === 1} onClick={() => { setLoading(true); setPage(value => value - 1) }}>Anterior</button><span>Página {page}</span><button disabled={busy || page * 25 >= total} onClick={() => { setLoading(true); setPage(value => value + 1) }}>Seguinte</button><button disabled={busy} onClick={refresh}>Recarregar</button></div></>}
    {command && <form onSubmit={change} className="record-form"><h3>{command.action === 'end' ? 'Encerrar vigência' : 'Anular tarifa'} #{command.price.id}</h3>
      {command.action === 'end' ? <><label htmlFor="end-date">Último dia válido</label><input id="end-date" type="date" required min={command.price.valid_from} max={command.price.valid_to ?? undefined} value={commandDate} disabled={busy} onChange={e => setCommandDate(e.target.value)} /></> : <><label htmlFor="cancel-reason">Motivo da anulação</label><textarea id="cancel-reason" required minLength={3} maxLength={500} value={reason} disabled={busy} onChange={e => setReason(e.target.value)} /><p>A tarifa permanecerá no histórico e deixará de ser selecionada nas simulações.</p></>}
      <div className="actions"><button disabled={busy}>Confirmar alteração</button><button type="button" disabled={busy} onClick={() => setCommand(null)}>Voltar</button></div></form>}
    <form onSubmit={preview} className="record-form"><h3>Simular valor</h3><p>Consulta a tarifa aplicável à data. Não gera pagamento, cobrança ou lançamento financeiro.</p>
      <label htmlFor="preview-kind">Finalidade</label><select id="preview-kind" value={previewKind} disabled={busy} onChange={e => { setPreviewKind(e.target.value as PriceKind); setPreviewTarget(''); setResult(null) }}><option value="payment">Pagamento</option><option value="billing">Cobrança</option></select>
      <label htmlFor="preview-target">{previewKind === 'payment' ? 'Trabalhador (opcional)' : 'Cliente (opcional)'}</label>
      <ReferenceField key={previewKind} field={{ key: 'preview-target', label: 'Destinatário da simulação', resource: previewKind === 'payment' ? 'workers' : 'clients', type: 'reference' }} value={previewTarget} onChange={value => { setPreviewTarget(value); setResult(null) }} disabled={busy} includeInactive emptyLabel="Consultar padrão do serviço" />
      <label htmlFor="preview-date">Data do trabalho</label><input id="preview-date" type="date" required value={previewDate} disabled={busy} onChange={e => { setPreviewDate(e.target.value); setResult(null) }} />
      <label htmlFor="preview-quantity">Quantidade na unidade da tarifa</label><input id="preview-quantity" required inputMode="decimal" maxLength={13} value={quantity} disabled={busy} onChange={e => { setQuantity(e.target.value); setResult(null) }} /><button disabled={busy}>Simular</button>
      {result && <output aria-live="polite">{result.quantity.replace('.', ',')} {result.price.unit_name} × {result.price.amount.replace('.', ',')} EUR = <strong>{result.total.replace('.', ',')} EUR</strong><br />Tarifa #{result.price.id} · {scopeLabel(result.price)} · Simulação provisória</output>}
    </form>
  </>
}

export default function Prices({ onSessionExpired, onBusyChange }: { onSessionExpired: () => void; onBusyChange: (value: boolean) => void }) {
  const [service, setService] = useState('')
  const [busy, setBusy] = useState(false)
  function busyChanged(value: boolean) { setBusy(value); onBusyChange(value) }
  return <section className="catalog"><h2>Preços e vigências</h2><label htmlFor="pricing-service">Serviço</label>
    <ReferenceField field={{ key: 'pricing-service', label: 'Serviço', resource: 'services', type: 'reference', required: true }} value={service} onChange={setService} disabled={busy} includeInactive />
    {service ? <PriceWorkspace key={service} serviceId={Number(service)} onSessionExpired={onSessionExpired} onBusyChange={busyChanged} /> : <p>Selecione um serviço para configurar os preços.</p>}
  </section>
}
