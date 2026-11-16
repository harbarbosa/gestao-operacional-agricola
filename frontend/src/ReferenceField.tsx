import { useContext, useEffect, useId, useRef, useState } from 'react'
import { api } from './api'
import WorkerHistory from './WorkerHistory'
import CatalogEditor from './CatalogEditor'
import { CatalogAccess } from './CatalogAccess'
import { hasExactName, quickCreateValues } from './quickCreate'
import type { CatalogItem, CatalogList, Field } from './catalog'

type Props = { field: Field; value: string; onChange: (value: string) => void; disabled: boolean; emptyLabel?: string; includeInactive?: boolean }
export default function ReferenceField({ field, value, onChange, disabled, emptyLabel = 'Sem equipa', includeInactive = false }: Props) {
  const access = useContext(CatalogAccess)
  const basePath = field.source === 'field' ? '/field/options' : field.source === 'finance' ? '/settlements/options' : field.source === 'reports' ? '/reports/options' : '/catalog'
  const lookupResource = field.lookup ?? field.resource
  const [history,setHistory]=useState<number|null>(null)
  const [quickName, setQuickName] = useState<string | null>(null)
  const suppressFocus = useRef(false)
  const [search, setSearch] = useState('')
  const [open, setOpen] = useState(false)
  const [items, setItems] = useState<CatalogItem[]>([])
  const [selected, setSelected] = useState<CatalogItem | null>(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState('')
  const [highlight, setHighlight] = useState(0)
  const input = useRef<HTMLInputElement>(null)
  const listId = useId()
  const visible = open && !disabled && quickName === null
  useEffect(() => {
    if (!visible) return
    let active = true
    const timer = setTimeout(() => {
      api<CatalogList>(`${basePath}/${lookupResource}?q=${encodeURIComponent(search)}${field.query ? `&${field.query}` : ''}`)
        .then(data => { if (active) { setItems(data.items.filter(item => includeInactive || item.is_active || (field.resource==='workers' && !!item.is_blocked))); setLoading(false); setError('') } })
        .catch(() => { if (active) { setLoading(false); setError('Não foi possível carregar as opções. Feche e abra para tentar novamente.') } })
    }, 250)
    return () => { active = false; clearTimeout(timer) }
  }, [search, field.resource, field.query, includeInactive, visible, basePath, lookupResource])
  useEffect(() => {
    let active = true
    if (!value) return
    api<{ item: CatalogItem }>(`${basePath}/${lookupResource}/${value}`)
      .then(data => { if (active) setSelected(data.item) })
      .catch(() => { if (active) setError('Não foi possível confirmar a opção selecionada.') })
    return () => { active = false }
  }, [value, field.resource, basePath, lookupResource])
  useEffect(() => {
    input.current?.setCustomValidity(field.required && !value ? 'Selecione uma opção da lista.' : '')
  }, [field.required, value])
  const choices = [...(!field.required ? [{ id: '', name: emptyLabel, item: null }] : []), ...items.map(item => ({ id: String(item.id), name: `${item.name}${item.is_active ? '' : ' (inativo)'}${item.is_blocked ? ` · BLOQUEADO: ${item.block_reason??''}` : item.low_performance ? ' · Atenção: baixo desempenho' : ''}${item.evaluation_count===0 && field.resource==='workers' ? ' · Novo — sem histórico' : ''}${item.performance_avg ? ` · ${Number(item.performance_avg).toFixed(1).replace('.', ',')}/5 (${item.evaluation_count})` : ''}`, item }))]
  const activeIndex = Math.min(highlight, Math.max(0, choices.length - 1))
  const selectedLabel = value && selected && String(selected.id) === value ? `${selected.name}${selected.is_active ? '' : ' (inativo)'}` : value ? 'A carregar seleção…' : ''
  function begin() { if (suppressFocus.current || quickName !== null) return; if (!visible) { setOpen(true); setSearch(''); setItems([]); setLoading(true); setError(''); setHighlight(0) } }
  function choose(index: number) {
    const choice = choices[index]
    if (!choice || (field.source==='field' && field.resource==='workers' && !!choice.item?.is_blocked)) return
    setSelected(choice.item); onChange(choice.id); setOpen(false); setSearch('')
  }
  const canCreate = !field.lookup && (access.canCreate || (access.canCreateWorkers && field.source === 'field' && field.resource === 'workers')) && !!field.resource && !!search.trim() && !loading && !error && !hasExactName(items.map(item => item.name), search)
  function closeQuick(item?: CatalogItem) {
    suppressFocus.current = true
    if (item) { setSelected(item); onChange(String(item.id)) }
    setQuickName(null); setOpen(false); setSearch('')
    requestAnimationFrame(() => { input.current?.focus(); suppressFocus.current = false })
  }
  return <div className="search-select" onBlur={event => { if (!event.currentTarget.contains(event.relatedTarget as Node | null)) { setOpen(false); setSearch('') } }}>
    <div className="search-select-control">
      <input ref={input} id={field.key} role="combobox" aria-label={field.label} aria-autocomplete="list" aria-expanded={visible} aria-controls={listId} aria-activedescendant={visible && !loading && !error && choices.length ? `${listId}-${activeIndex}` : undefined} aria-required={field.required} autoComplete="off" disabled={disabled} placeholder={visible ? 'Digite para pesquisar…' : field.required ? 'Selecione…' : emptyLabel} value={visible ? search : selectedLabel} onFocus={begin} onClick={begin} onChange={event => { setSearch(event.target.value); setItems([]); setLoading(true); setError(''); setHighlight(0); setOpen(true) }} onKeyDown={event => {
        if (event.key === 'Escape' && visible) { event.preventDefault(); event.stopPropagation(); setOpen(false); setSearch('') }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); if (!visible) begin(); else setHighlight(Math.max(0, Math.min(choices.length - 1, activeIndex + (event.key === 'ArrowDown' ? 1 : -1)))) }
        if (event.key === 'Enter' && visible) { event.preventDefault(); if (!loading && !error) choose(activeIndex) }
      }} />
      <span className="select-chevron" aria-hidden="true">⌄</span>
    </div>
    {visible && <div className="search-select-menu"><div id={listId} role="listbox" aria-label={field.label} aria-busy={loading}>
      {loading ? <div className="select-feedback" role="status">A pesquisar…</div> : error ? <div className="select-feedback" role="alert">{error}</div> : <>{choices.map((choice, index) => <div key={choice.id} id={`${listId}-${index}`} role="option" aria-disabled={field.source==='field' && field.resource==='workers' && !!choice.item?.is_blocked} aria-selected={choice.id === value} className={`select-option ${activeIndex === index ? 'highlighted' : ''}`} onPointerDown={event => event.preventDefault()} onClick={() => choose(index)} onPointerMove={() => setHighlight(index)}>{choice.name}{field.source==='field' && field.resource==='workers' && choice.id && <button type="button" className="worker-history-link" aria-label={`Ver histórico de ${choice.item?.name}`} onPointerDown={event=>event.preventDefault()} onClick={event=>{event.stopPropagation();setHistory(Number(choice.id))}}>Histórico</button>}{choice.id === value && <span aria-hidden="true">✓</span>}</div>)}{!items.length && <div className="select-feedback" role="status">Nenhum resultado encontrado.</div>}</>}
    </div>{canCreate && <button type="button" className="select-create" onPointerDown={event => event.preventDefault()} onClick={() => { setQuickName(search.trim()); setOpen(false) }}>＋ Cadastrar novo: <strong>{search.trim()}</strong></button>}</div>}
    {quickName !== null && field.resource && <CatalogEditor resource={field.resource} endpoint={field.source === 'field' && field.resource === 'workers' ? '/field/workers' : undefined} initialValues={quickCreateValues(field, quickName)} lockedFields={Object.keys(quickCreateValues(field, quickName)).filter(key => key !== 'name')} quick onSessionExpired={access.onSessionExpired} onClose={() => closeQuick()} onSaved={closeQuick} />}
    {history!==null && <WorkerHistory id={history} onClose={()=>setHistory(null)}/>}
    {!visible && error && <small role="alert">{error}</small>}
  </div>
}
