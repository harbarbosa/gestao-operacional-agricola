import { useCallback, useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError, type CurrentUser } from './api'
import './App.css'
import Catalog from './Catalog'
import Prices from './Prices'
import { catalogs, type Resource } from './catalog'

const roleNames: Record<string, string> = { admin: 'Administrador', supervisor: 'Chefe de equipa', worker: 'Trabalhador' }

export default function App() {
  const [user, setUser] = useState<CurrentUser | null>(null)
  const [checking, setChecking] = useState(true)
  const [busy, setBusy] = useState(false)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [message, setMessage] = useState('')
  const [users, setUsers] = useState<CurrentUser[]>([])
  const [catalogBusy, setCatalogBusy] = useState(false)
  const [resource, setResource] = useState<Resource | 'prices'>('workers')
  const sessionExpired = useCallback(() => { setUser(null); setUsers([]); setMessage('Sessão expirada. Inicie sessão novamente.') }, [])
  useEffect(() => {
    let active = true
    api<{ user: CurrentUser }>('/auth/me').then(data => { if (active) setUser(data.user) }).catch(error => {
      if (active && (!(error instanceof ApiError) || error.status !== 401)) setMessage('Sem ligação ao servidor. Inicie sessão com internet para continuar.')
    }).finally(() => { if (active) setChecking(false) })
    return () => { active = false }
  }, [])
  async function login(event: SubmitEvent<HTMLFormElement>) {
    event.preventDefault()
    setBusy(true); setMessage('')
    try {
      const data = await api<{ user: CurrentUser }>('/auth/login', { method: 'POST', body: JSON.stringify({ email, password }) })
      setUser(data.user)
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Falha ao iniciar sessão.') }
    finally { setPassword(''); setBusy(false) }
  }
  async function logout() {
    setBusy(true)
    try {
      await api('/auth/logout', { method: 'POST', body: '{}' })
      setUser(null); setUsers([]); setMessage('Sessão terminada.')
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) { setUser(null); setUsers([]) }
      setMessage('Não foi possível confirmar o fim da sessão. Verifique a ligação e tente novamente.')
    } finally { setBusy(false) }
  }
  async function loadUsers() {
    setBusy(true); setMessage('')
    try { setUsers((await api<{ users: CurrentUser[] }>('/users')).users) }
    catch (error) {
      if (error instanceof ApiError && error.status === 401) { setUser(null); setUsers([]) }
      setMessage(error instanceof Error ? error.message : 'Falha ao consultar.')
    } finally { setBusy(false) }
  }
  return <main>
    <header><span className="brand">GESTÃO AGRÍCOLA</span><span className="badge">{user ? 'Sessão iniciada' : 'Acesso à empresa'}</span></header>
    <section className="intro"><p>GESTÃO OPERACIONAL</p><h1>A operação começa no campo.</h1><p>Equipas, trabalho e produção numa única plataforma.</p></section>
    <section className="card">
      {checking ? <p role="status">A verificar sessão…</p> : user ? <>
        <h2>Olá, {user.username}</h2><p>{user.groups.map(role => roleNames[role] ?? role).join(' · ')}</p>
        {user.permissions.includes('catalog.manage') && <>
          <nav aria-label="Cadastros" className="catalog-nav">{(Object.keys(catalogs) as Resource[]).map(key => <button key={key} aria-pressed={resource === key} disabled={busy || catalogBusy} onClick={() => setResource(key)}>{catalogs[key].label}</button>)}{user.permissions.includes('prices.manage') && <button aria-pressed={resource === 'prices'} disabled={busy || catalogBusy} onClick={() => setResource('prices')}>Preços</button>}</nav>
          {resource === 'prices' ? <Prices key={`${user.id}-prices`} onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} /> : <Catalog key={`${user.id}-${resource}`} resource={resource} onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} />}
        </>}
        {user.permissions.includes('users.view') && <><button disabled={busy || catalogBusy} onClick={loadUsers}>Consultar utilizadores</button>
          <ul>{users.map(item => <li key={item.id}>{item.username} — {item.groups.map(role => roleNames[role] ?? role).join(', ')}</li>)}</ul></>}
        <button disabled={busy || catalogBusy} onClick={logout}>Terminar sessão</button>
      </> : <><h2>Iniciar sessão</h2><form onSubmit={login}>
        <label htmlFor="email">Email</label><input id="email" type="email" autoComplete="username" required maxLength={254} value={email} onChange={e => setEmail(e.target.value)} disabled={busy || catalogBusy} />
        <label htmlFor="password">Palavra-passe</label><input id="password" type="password" autoComplete="current-password" required value={password} onChange={e => setPassword(e.target.value)} disabled={busy || catalogBusy} />
        <button disabled={busy || catalogBusy}>{busy ? 'A iniciar…' : 'Entrar'}</button>
      </form><p>As contas são criadas pelo responsável do sistema.</p></>}
      <p role="status" aria-live="polite">{message}</p>
    </section>
    <footer>Versão de desenvolvimento. Login requer internet. A operação offline autenticada será integrada na etapa de sincronização.</footer>
  </main>
}
