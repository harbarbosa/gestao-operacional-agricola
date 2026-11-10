import { useId, useState, type SubmitEvent } from 'react'
import { api, ApiError } from './api'
import { catalogs, payloadFor, type CatalogItem, type Resource } from './catalog'
import Modal from './Modal'
import { maskPhone } from './locale'
import ReferenceField from './ReferenceField'

type Props = {
  resource: Resource; item?: CatalogItem | null; initialValues?: Record<string, string>; quick?: boolean
  onSaved: (item: CatalogItem) => void; onClose: () => void; onSessionExpired: () => void
  onBusyChange?: (busy: boolean) => void; lockedFields?: string[]; endpoint?: string
}
export default function CatalogEditor({ resource, item, initialValues = {}, quick = false, onSaved, onClose, onSessionExpired, onBusyChange, lockedFields = [], endpoint }: Props) {
  const config = catalogs[resource]
  const prefix = useId()
  const [values, setValues] = useState<Record<string, string>>(() => item ? Object.fromEntries(config.fields.map(field => [field.key, field.key === 'phone' ? maskPhone(String(item[field.key] ?? '')) : String(item[field.key] ?? '')])) : initialValues)
  const [active, setActive] = useState(item?.is_active ?? true)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  async function save(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); event.stopPropagation()
    if (busy) return
    setBusy(true); onBusyChange?.(true); setMessage(''); setErrors({})
    try {
      const data = await api<{ item: CatalogItem }>(`${endpoint ?? `/catalog/${resource}`}${item ? `/${item.id}` : ''}`, {
        method: item ? 'PUT' : 'POST', body: JSON.stringify(payloadFor(resource, values, active, item?.version)),
      })
      onSaved(data.item)
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else {
        setMessage(error instanceof ApiError ? error.message : 'Não foi possível confirmar a gravação. Consulte a listagem antes de tentar novamente.')
        if (error instanceof ApiError) setErrors(error.fields)
      }
    } finally { setBusy(false); onBusyChange?.(false) }
  }
  return <Modal title={item ? `Editar: ${item.name}` : `${quick ? 'Cadastro rápido' : 'Novo registo'} · ${config.label}`} busy={busy} onClose={onClose}>
    <form onSubmit={save} className="record-form">
      <p role="status" aria-live="polite">{message}</p>
      {config.fields.map(field => {
        const id = `${prefix}-${field.key}`
        return <div className="form-field" key={field.key}>
          <label htmlFor={id}>{field.label}{field.required ? ' *' : ''}</label>
          {field.type === 'reference' ? <ReferenceField field={{ ...field, key: id, ...(endpoint === '/field/workers' && field.type === 'reference' ? { source: 'field' as const } : {}) }} value={values[field.key] ?? ''} disabled={busy || lockedFields.includes(field.key)} onChange={value => setValues(old => ({ ...old, [field.key]: value }))} />
            : field.type === 'textarea' ? <textarea id={id} value={values[field.key] ?? ''} maxLength={field.max} disabled={busy} onChange={e => setValues(old => ({ ...old, [field.key]: field.key === 'phone' ? maskPhone(e.target.value) : e.target.value }))} />
            : <input id={id} type={field.key === 'phone' ? 'tel' : field.type ?? 'text'} placeholder={field.key === 'phone' ? '+351 912 345 678' : undefined} required={field.required} maxLength={field.max} value={values[field.key] ?? ''} disabled={busy} onChange={e => setValues(old => ({ ...old, [field.key]: field.key === 'phone' ? maskPhone(e.target.value) : e.target.value }))} />}
          {errors[field.key] && <small role="alert">{errors[field.key]}</small>}
        </div>
      })}
      {!quick && <label className="checkbox"><input type="checkbox" checked={active} disabled={busy} onChange={e => setActive(e.target.checked)} />Ativo</label>}
      <div className="actions"><button disabled={busy}>{busy ? 'A guardar…' : quick ? 'Guardar e selecionar' : 'Guardar'}</button><button type="button" disabled={busy} onClick={onClose}>Cancelar</button></div>
    </form>
  </Modal>
}
