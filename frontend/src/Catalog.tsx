import { useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError } from './api'
import { catalogs, payloadFor, type CatalogItem, type CatalogList, type Field, type Resource } from './catalog'

export function ReferenceField({ field, value, onChange, disabled, emptyLabel = 'Sem equipa', includeInactive = false }: { field: Field; value: string; onChange: (value: string) => void; disabled: boolean; emptyLabel?: string; includeInactive?: boolean }) {
  const [search, setSearch] = useState('')
  const [items, setItems] = useState<CatalogItem[]>([])
  const [selected, setSelected] = useState<CatalogItem | null>(null)
  const [error, setError] = useState('')
  useEffect(() => {
    let active = true
    const timer = setTimeout(() => {
      api<CatalogList>(`/catalog/${field.resource}?q=${encodeURIComponent(search)}${field.query ? `&${field.query}` : ''}`)
        .then(data => { if (active) { setItems(data.items.filter(item => includeInactive || item.is_active)); setError('') } })
        .catch(() => { if (active) setError('Não foi possível carregar as opções. Verifique a sessão e a ligação.') })
    }, 250)
    return () => { active = false; clearTimeout(timer) }
  }, [search, field.resource, field.query, includeInactive])
  useEffect(() => {
    let active = true
    if (value) api<{ item: CatalogItem }>(`/catalog/${field.resource}/${value}`)
      .then(data => { if (active) setSelected(data.item) })
      .catch(() => { if (active) setError('Não foi possível confirmar o registo selecionado.') })
    return () => { active = false }
  }, [value, field.resource])
  const choices = [...items]
  if (selected && String(selected.id) === value && !choices.some(item => item.id === selected.id)) choices.unshift(selected)
  return <>
    <input aria-label={`Pesquisar opções: ${field.label}`} placeholder="Pesquisar opções por nome" value={search} onChange={e => setSearch(e.target.value)} disabled={disabled} />
    <select id={field.key} required={field.required} value={value} onChange={e => onChange(e.target.value)} disabled={disabled}>
      <option value="">{field.required ? 'Selecione…' : emptyLabel}</option>
      {choices.map(item => <option key={item.id} value={item.id}>{item.name}{item.is_active ? '' : ' (inativo)'}</option>)}
    </select>
    <small>Mostra até 25 resultados. Pesquise pelo nome para encontrar outros registos.</small>
    {error && <p role="alert">{error}</p>}
  </>
}

export default function Catalog({ resource, onSessionExpired, onBusyChange }: { resource: Resource; onSessionExpired: () => void; onBusyChange: (value: boolean) => void }) {
  const [rows, setRows] = useState<CatalogItem[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [search, setSearch] = useState('')
  const [query, setQuery] = useState('')
  const [reload, setReload] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [editing, setEditing] = useState<CatalogItem | null>(null)
  const [open, setOpen] = useState(false)
  const [values, setValues] = useState<Record<string, string>>({})
  const [active, setActive] = useState(true)
  const [message, setMessage] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const config = catalogs[resource]
  useEffect(() => {
    let current = true
    api<CatalogList>(`/catalog/${resource}?page=${page}&q=${encodeURIComponent(query)}`)
      .then(data => { if (current) { setRows(data.items); setTotal(data.total); setLoading(false) } })
      .catch(error => {
        if (!current) return
        setRows([]); setLoading(false)
        if (error instanceof ApiError && error.status === 401) onSessionExpired()
        else setMessage(error instanceof Error ? error.message : 'Falha ao carregar cadastros.')
      })
    return () => { current = false }
  }, [resource, page, query, reload, onSessionExpired])
  function start(item: CatalogItem | null) {
    setEditing(item); setValues(item ? Object.fromEntries(config.fields.map(field => [field.key, String(item[field.key] ?? '')])) : {})
    setActive(item?.is_active ?? true); setErrors({}); setMessage(''); setOpen(true)
  }
  async function save(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); setBusy(true); onBusyChange(true); setErrors({}); setMessage('')
    try {
      await api(`/catalog/${resource}${editing ? `/${editing.id}` : ''}`, {
        method: editing ? 'PUT' : 'POST', body: JSON.stringify(payloadFor(resource, values, active, editing?.version)),
      })
      setOpen(false); setEditing(null); setValues({}); setMessage('Registo guardado.'); setLoading(true); setReload(count => count + 1)
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else {
        setMessage(error instanceof ApiError ? error.message : 'Não foi possível confirmar a gravação. Consulte a listagem antes de tentar novamente.')
        if (error instanceof ApiError) {
          setErrors(error.fields)
          if (error.status === 409) { setLoading(true); setReload(count => count + 1) }
        }
      }
    } finally { setBusy(false); onBusyChange(false) }
  }
  function filter(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); setLoading(true); setPage(1); setQuery(search); setReload(count => count + 1)
  }
  return <section className="catalog">
    <div className="section-title"><h2>{config.label}</h2><button onClick={() => start(null)} disabled={busy}>Novo registo</button></div>
    <form onSubmit={filter} className="search"><label htmlFor="catalog-search">Pesquisar por nome</label><input id="catalog-search" maxLength={160} value={search} onChange={e => setSearch(e.target.value)} /><button disabled={busy}>Pesquisar</button></form>
    <p role="status" aria-live="polite">{message}</p>
    {open && <form onSubmit={save} className="record-form">
      <h3>{editing ? `Editar: ${editing.name}` : 'Novo registo'}</h3>
      {config.fields.map(field => <div className="form-field" key={field.key}>
        <label htmlFor={field.key}>{field.label}{field.required ? ' *' : ''}</label>
        {field.type === 'reference' ? <ReferenceField field={field} value={values[field.key] ?? ''} disabled={busy} onChange={value => setValues(old => ({ ...old, [field.key]: value }))} />
          : field.type === 'textarea' ? <textarea id={field.key} value={values[field.key] ?? ''} maxLength={field.max} disabled={busy} onChange={e => setValues(old => ({ ...old, [field.key]: e.target.value }))} />
          : <input id={field.key} type={field.type ?? 'text'} required={field.required} maxLength={field.max} value={values[field.key] ?? ''} disabled={busy} onChange={e => setValues(old => ({ ...old, [field.key]: e.target.value }))} />}
        {errors[field.key] && <small role="alert">{errors[field.key]}</small>}
      </div>)}
      <label className="checkbox"><input type="checkbox" checked={active} disabled={busy} onChange={e => setActive(e.target.checked)} />Ativo</label>
      <div className="actions"><button disabled={busy}>{busy ? 'A guardar…' : 'Guardar'}</button><button type="button" disabled={busy} onClick={() => setOpen(false)}>Cancelar</button></div>
      {editing && <small>Em caso de conflito, cancele e abra novamente o registo na listagem atualizada.</small>}
    </form>}
    {loading ? <p>A carregar…</p> : <>
      <p>{total} registo(s). Inclui ativos e inativos.</p>
      <div className="table-wrap"><table><thead><tr><th>Nome</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
        {rows.map(row => <tr key={row.id}><td>{row.name}</td><td>{row.is_active ? 'Ativo' : 'Inativo'}</td><td><button disabled={busy} onClick={() => start(row)}>Editar</button></td></tr>)}
        {rows.length === 0 && <tr><td colSpan={3}>Nenhum registo encontrado.</td></tr>}
      </tbody></table></div>
      <div className="actions"><button disabled={page === 1 || busy} onClick={() => { setLoading(true); setPage(p => p - 1) }}>Anterior</button><span>Página {page}</span><button disabled={page * 25 >= total || busy} onClick={() => { setLoading(true); setPage(p => p + 1) }}>Seguinte</button></div>
    </>}
  </section>
}
