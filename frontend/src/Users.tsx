import { useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError, type CurrentUser } from './api'
import Modal from './Modal'
import { Icon } from './Icons'

export interface ManagedUser { id: number; username: string; email: string; active: boolean; groups: string[]; role: string; extra_permissions: string[]; permissions: string[]; version: number }
export interface AccessMetadata { groups: Record<string, { title: string; description: string }>; permissions: Record<string, string>; matrix: Record<string, string[]> }
type Props = { currentUser: CurrentUser; onSessionExpired: () => void; onBusyChange: (busy: boolean) => void }
function UserEditor({ item, metadata, currentUser, onClose, onSaved, onSessionExpired, onBusyChange }: Props & { item: ManagedUser | null; metadata: AccessMetadata; onClose: () => void; onSaved: (user: ManagedUser) => void }) {
  const manage = currentUser.permissions.includes('users.manage')
  const self = currentUser.id === item?.id
  const [name, setName] = useState(item?.username ?? '')
  const [email, setEmail] = useState(item?.email ?? '')
  const [password, setPassword] = useState('')
  const [role, setRole] = useState(item?.role ?? 'worker')
  const [active, setActive] = useState(item?.active ?? true)
  const [extra, setExtra] = useState<string[]>(item?.extra_permissions ?? [])
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [errors, setErrors] = useState<Record<string, string>>({})
  const inherited = metadata.matrix[role] ?? []
  async function save(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); if (!manage || busy) return
    setBusy(true); onBusyChange(true); setErrors({}); setMessage('')
    try {
      const data = await api<{ user: ManagedUser; reauthenticate: boolean }>(`/users${item ? `/${item.id}` : ''}`, {
        method: item ? 'PUT' : 'POST', body: JSON.stringify({ username: name, email, password, role, active, permissions: extra, ...(item ? { version: item.version } : {}) }),
      })
      if (data.reauthenticate) onSessionExpired(); else onSaved(data.user)
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else { setMessage(error instanceof Error ? error.message : 'Não foi possível guardar.'); if (error instanceof ApiError) setErrors(error.fields) }
    } finally { setPassword(''); setBusy(false); onBusyChange(false) }
  }
  function toggle(permission: string, checked: boolean) {
    setExtra(old => {
      const next = new Set(old)
      if (checked) next.add(permission); else next.delete(permission)
      if (checked && permission === 'users.manage') next.add('users.view')
      if (!checked && permission === 'users.view') next.delete('users.manage')
      return [...next]
    })
  }
  return <Modal title={!manage ? 'Consultar utilizador' : item ? `Editar: ${item.username}` : 'Novo utilizador'} busy={busy} onClose={onClose}>
    <form className="record-form" onSubmit={save}>
      <p role="status" aria-live="polite">{message}</p>
      <div className="form-field"><label htmlFor="user-name">Nome de utilizador *</label><input id="user-name" required maxLength={30} autoComplete="off" value={name} disabled={busy || !manage} onChange={event => setName(event.target.value)} />{errors.username && <small role="alert">{errors.username}</small>}</div>
      <div className="form-field"><label htmlFor="user-email">Email de acesso *</label><input id="user-email" type="email" required maxLength={254} autoComplete="off" value={email} disabled={busy || !manage} onChange={event => setEmail(event.target.value)} />{errors.email && <small role="alert">{errors.email}</small>}</div>
      {manage && <div className="form-field"><label htmlFor="user-password">{item ? 'Nova palavra-passe (opcional)' : 'Palavra-passe *'}</label><input id="user-password" type="password" required={!item} minLength={8} maxLength={72} autoComplete="new-password" value={password} disabled={busy} onChange={event => setPassword(event.target.value)} />{errors.password && <small role="alert">{errors.password}</small>}</div>}
      <div className="form-field"><label htmlFor="user-role">Perfil *</label><select id="user-role" value={role} disabled={busy || !manage || self} onChange={event => setRole(event.target.value)}>{Object.entries(metadata.groups).map(([key, group]) => <option key={key} value={key}>{group.title}</option>)}</select>{errors.role && <small role="alert">{errors.role}</small>}</div>
      <fieldset className="user-permissions" disabled={busy || !manage || self}><legend>Permissões</legend>
        {Object.entries(metadata.permissions).map(([key, description]) => <label className="permission-option" key={key}><input type="checkbox" checked={inherited.includes(key) || extra.includes(key)} disabled={inherited.includes(key)} onChange={event => toggle(key, event.target.checked)} /><span>{description}<small>{inherited.includes(key) ? 'Incluída no perfil' : 'Permissão adicional'}</small></span></label>)}
        {errors.permissions && <small role="alert">{errors.permissions}</small>}
      </fieldset>
      <label className="checkbox"><input type="checkbox" checked={active} disabled={busy || !manage || self} onChange={event => setActive(event.target.checked)} />Conta ativa</label>

      <div className="actions">{manage && <button disabled={busy}>{busy ? 'A guardar…' : 'Guardar utilizador'}</button>}<button type="button" disabled={busy} onClick={onClose}>{manage ? 'Cancelar' : 'Fechar'}</button></div>
    </form>
  </Modal>
}

export default function Users({ currentUser, onSessionExpired, onBusyChange }: Props) {
  const [metadata, setMetadata] = useState<AccessMetadata | null>(null)
  const [users, setUsers] = useState<ManagedUser[]>([])
  const [total, setTotal] = useState(0)
  const [page, setPage] = useState(1)
  const [filters, setFilters] = useState({ q: '', role: '', active: '' })
  const [applied, setApplied] = useState(filters)
  const [reload, setReload] = useState(0)
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState('')
  const [editor, setEditor] = useState<ManagedUser | 'new' | null>(null)
  const [deactivating, setDeactivating] = useState<ManagedUser | null>(null)
  const manage = currentUser.permissions.includes('users.manage')
  useEffect(() => {
    let active = true
    const query = new URLSearchParams({ page: String(page), ...applied })
    Promise.all([api<{ users: ManagedUser[]; total: number }>(`/users?${query}`), api<AccessMetadata>('/users/metadata')]).then(([data, config]) => {
      if (active) { setUsers(data.users); setTotal(data.total); setMetadata(config); setLoading(false) }
    }).catch(error => {
      if (!active) return
      setUsers([]); setLoading(false)
      if (error instanceof ApiError && error.status === 401) onSessionExpired()
      else setMessage(error instanceof Error ? error.message : 'Não foi possível carregar utilizadores.')
    })
    return () => { active = false }
  }, [page, applied, reload, onSessionExpired])
  function refresh() { setLoading(true); setReload(value => value + 1) }
  function busyChanged(value: boolean) { setBusy(value); onBusyChange(value) }
  async function open(item: ManagedUser) {
    busyChanged(true); setMessage('')
    try { setEditor((await api<{ user: ManagedUser }>(`/users/${item.id}`)).user) }
    catch (error) { if (error instanceof ApiError && error.status === 401) onSessionExpired(); else setMessage('Não foi possível abrir o utilizador.'); }
    finally { busyChanged(false) }
  }
  async function deactivate(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault(); if (!deactivating) return
    busyChanged(true); setMessage('')
    try { await api(`/users/${deactivating.id}`, { method: 'DELETE', body: JSON.stringify({ version: deactivating.version }) }); setDeactivating(null); setMessage('Conta desativada. Histórico preservado.'); refresh() }
    catch (error) { if (error instanceof ApiError && error.status === 401) onSessionExpired(); else setMessage(error instanceof Error ? error.message : 'Não foi possível desativar.'); }
    finally { busyChanged(false) }
  }
  return <section className="catalog">
    <div className="section-title"><h2>Utilizadores e permissões</h2>{manage && <button disabled={busy || !metadata} onClick={() => { setEditor('new'); setMessage('') }}>＋ Novo utilizador</button>}</div>
    <form className="user-filters" onSubmit={event => { event.preventDefault(); setPage(1); setApplied({ ...filters }); setLoading(true); setMessage('') }}>
      <div className="form-field"><label htmlFor="users-search">Pesquisar por nome ou email</label><input id="users-search" maxLength={160} disabled={busy} value={filters.q} onChange={event => setFilters(old => ({ ...old, q: event.target.value }))} /></div>
      <div className="form-field"><label htmlFor="users-role">Perfil</label><select id="users-role" disabled={busy} value={filters.role} onChange={event => setFilters(old => ({ ...old, role: event.target.value }))}><option value="">Todos</option>{Object.entries(metadata?.groups ?? {}).map(([key, group]) => <option key={key} value={key}>{group.title}</option>)}</select></div>
      <div className="form-field"><label htmlFor="users-active">Estado</label><select id="users-active" disabled={busy} value={filters.active} onChange={event => setFilters(old => ({ ...old, active: event.target.value }))}><option value="">Todos</option><option value="1">Ativos</option><option value="0">Inativos</option></select></div>
      <div className="actions"><button disabled={busy}>Filtrar</button><button type="button" disabled={busy} onClick={() => { const blank = { q: '', role: '', active: '' }; setFilters(blank); setApplied(blank); setPage(1); setLoading(true) }}>Limpar filtros</button></div>
    </form>
    <p role="status" aria-live="polite">{message}</p>
    {loading ? <p>A carregar…</p> : <><p>{total} utilizador(es)</p><div className="table-wrap"><table><thead><tr><th>Utilizador</th><th>Email</th><th>Perfil</th><th>Estado</th><th>Ações</th></tr></thead><tbody>{users.map(user => <tr key={user.id}><td>{user.username}{user.id === currentUser.id && <small> · A sua conta</small>}</td><td>{user.email}</td><td>{metadata?.groups[user.role]?.title ?? user.role}<small className="permissions-count">{user.permissions.length} permissões</small></td><td><span className="status-pill">{user.active ? 'Ativo' : 'Inativo'}</span></td><td><div className="row-actions"><button className="icon-action" disabled={busy} title={manage ? 'Editar dados e permissões' : 'Consultar'} aria-label={`${manage ? 'Editar' : 'Consultar'} ${user.username}`} onClick={() => open(user)}><Icon name={manage ? 'edit' : 'view'} /></button>{manage && <button className="icon-action danger" disabled={busy || !user.active || user.id === currentUser.id} title="Desativar conta e preservar histórico" aria-label={`Desativar ${user.username}`} onClick={() => { setDeactivating(user); setMessage('') }}><Icon name="trash" /></button>}</div></td></tr>)}{!users.length && <tr><td colSpan={5}>Nenhum utilizador encontrado.</td></tr>}</tbody></table></div><div className="actions"><button disabled={busy || page === 1} onClick={() => { setPage(value => value - 1); setLoading(true) }}>Anterior</button><span>Página {page}</span><button disabled={busy || page * 25 >= total} onClick={() => { setPage(value => value + 1); setLoading(true) }}>Seguinte</button><button disabled={busy} onClick={refresh}>Recarregar</button></div></>}
    {editor && metadata && <UserEditor key={editor === 'new' ? 'new' : editor.id} item={editor === 'new' ? null : editor} metadata={metadata} currentUser={currentUser} onSessionExpired={onSessionExpired} onBusyChange={busyChanged} onClose={() => { setEditor(null); refresh() }} onSaved={() => { setEditor(null); setMessage('Utilizador guardado.'); refresh() }} />}
    {deactivating && <Modal title="Desativar utilizador" busy={busy} onClose={() => setDeactivating(null)}><form onSubmit={deactivate}><p>Desativar a conta de <strong>{deactivating.username}</strong>?</p><p role="status" aria-live="polite">{message}</p><div className="actions"><button disabled={busy}>Confirmar desativação</button><button type="button" disabled={busy} onClick={() => setDeactivating(null)}>Cancelar</button></div></form></Modal>}
  </section>
}
