import { useCallback, useEffect, useState, type SubmitEvent } from 'react'
import { api, ApiError, type CurrentUser } from './api'
import './App.css'
import Catalog from './Catalog'
import Prices from './Prices'
import WorkLogs from './WorkLogs'
import Users from './Users'
import Reports from './Reports'
import Finance from './Finance'
import Harvests from './Harvests'
import Performance from './Performance'
import { catalogs, type Resource } from './catalog'
import Dashboard from './Dashboard'
import { CatalogAccess } from './CatalogAccess'
import { Icon, Brand } from './Icons'
import Modal from './Modal'

const roleNames: Record<string, string> = { admin: 'Administrador', supervisor: 'Controlador de campo', worker: 'Trabalhador' }

export default function App() {
  const [user, setUser] = useState<CurrentUser | null>(null)
  const [checking, setChecking] = useState(true)
  const [busy, setBusy] = useState(false)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [message, setMessage] = useState('')
  const [catalogBusy, setCatalogBusy] = useState(false)
  const [mobileMenu, setMobileMenu] = useState(false)
  const [resource, setResource] = useState<Resource | 'prices' | 'work-logs' | 'overview' | 'account' | 'users' | 'reports' | 'finance' | 'performance' | 'harvests'>('overview')
  const sessionExpired = useCallback(() => { setMobileMenu(false); setUser(null); setResource('overview'); setMessage('Sessão expirada. Inicie sessão novamente.') }, [])
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
      setUser(data.user); setResource('overview')
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Falha ao iniciar sessão.') }
    finally { setPassword(''); setBusy(false) }
  }
  async function logout() {
    setBusy(true)
    try {
      await api('/auth/logout', { method: 'POST', body: '{}' })
      setMobileMenu(false); setUser(null); setResource('overview'); setMessage('Sessão terminada.')
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) { setMobileMenu(false); setUser(null); setResource('overview') }
      setMessage('Não foi possível confirmar o fim da sessão. Verifique a ligação e tente novamente.')
    } finally { setBusy(false) }
  }
  const navigation = [
    { key: 'overview', label: user?.permissions.includes('finance.manage')?'Visão geral':'Hoje', icon: 'home', allowed: true },
    { key: 'work-logs', label: 'Histórico', icon: 'clipboard', allowed: (user?.permissions.includes('field.manage') || user?.permissions.includes('field.record')) },
    ...(['workers', 'clients'] as Resource[]).map(key => ({ key, label: catalogs[key].label, icon: key, allowed: user?.permissions.includes('catalog.manage') })),
    { key: 'harvests', label: 'Contratos', icon: 'services', allowed: user?.permissions.includes('finance.manage') },
    { key: 'prices', label: 'Serviços e preços', icon: 'prices', allowed: false },
    { key: 'finance', label: 'Fechos anteriores', icon: 'prices', allowed: user?.permissions.includes('finance.manage') },
    { key: 'performance', label: 'Desempenho', icon: 'chart', allowed: user?.permissions.includes('performance.view') && user?.permissions.includes('finance.manage') },
    { key: 'reports', label: 'Relatórios', icon: 'chart', allowed: user?.permissions.includes('reports.view') },
    { key: 'users', label: 'Utilizadores e permissões', icon: 'teams', allowed: user?.permissions.includes('users.view') },
    { key: 'account', label: 'Conta e definições', icon: 'settings', allowed: true },
  ].filter(item => item.allowed)
  const title = navigation.find(item => item.key === resource)?.label ?? 'Visão geral'
  function navigate(key: string) { setMobileMenu(false); setResource(key as typeof resource); setMessage('') }
  if (checking) return <main className="login-page"><Brand /><p role="status">A verificar sessão…</p></main>
  if (!user) return <main className="login-page"><div className="login-intro"><Brand /><p className="eyebrow">GESTÃO WEB · OPERAÇÃO NO CAMPO</p><h1>Toda a operação.<br />Uma só plataforma.</h1><p>Equipas, trabalho e produção, do campo à gestão.</p><div className="leaf-decoration" aria-hidden="true">✦</div></div><section className="card login-card"><span className="eyebrow">BEM-VINDO</span><h2>Iniciar sessão</h2><p>Aceda à sua empresa para continuar.</p><form onSubmit={login}>
    <label htmlFor="email">Email</label><input id="email" type="email" autoComplete="username" required maxLength={254} value={email} onChange={e => setEmail(e.target.value)} disabled={busy} />
    <label htmlFor="password">Palavra-passe</label><input id="password" type="password" autoComplete="current-password" required value={password} onChange={e => setPassword(e.target.value)} disabled={busy} />
    <button disabled={busy}>{busy ? 'A iniciar…' : 'Entrar na plataforma'}</button></form><p role="status" aria-live="polite">{message}</p></section></main>
  return <CatalogAccess.Provider value={{ canCreate: user.permissions.includes('catalog.manage'), canCreateWorkers: user.permissions.includes('field.record'), onSessionExpired: sessionExpired }}><div className="app-shell">
    <aside className="sidebar"><Brand /><nav aria-label="Menu principal">{navigation.map(item => <button key={item.key} disabled={busy || catalogBusy} aria-current={resource === item.key ? 'page' : undefined} onClick={() => navigate(item.key)}><Icon name={item.icon} /><span>{item.label}</span></button>)}</nav><div className="sidebar-bottom"><span className="connection-dot" /> Gestão operacional agrícola<small>Portugal · Área de gestão</small></div></aside>
    <div className="workspace"><header className="topbar"><button className="mobile-menu-toggle" aria-label="Abrir menu completo" aria-haspopup="dialog" aria-expanded={mobileMenu} disabled={busy || catalogBusy} onClick={() => setMobileMenu(true)}><Icon name="menu" /></button><div><span className="eyebrow">AGROPERI</span><h1>{title}</h1></div><div className="topbar-user"><span className="current-date"><Icon name="calendar" />{new Intl.DateTimeFormat('pt-PT', { dateStyle: 'long', timeZone: 'Europe/Lisbon' }).format(new Date())}</span><button className="avatar" aria-label="Abrir conta" disabled={busy || catalogBusy} onClick={() => navigate('account')}>{user.username.slice(0, 2).toUpperCase()}</button></div></header>
    <main className="page-content">
      {resource === 'overview' && user.permissions.includes('field.record') && !user.permissions.includes('finance.manage') ? <WorkLogs todayMode onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy}/> : resource === 'overview' ? <Dashboard user={user} onNavigate={navigate} onSessionExpired={sessionExpired} /> : resource === 'account' ? <section className="card"><h2>Olá, {user.username}</h2><p>{user.groups.map(role => roleNames[role] ?? role).join(' · ')}</p><button disabled={busy || catalogBusy} onClick={logout}>Terminar sessão</button></section> : <section className="card module-card">{resource === 'harvests' ? <Harvests admin={user.permissions.includes('finance.manage')} onActivities={()=>navigate('work-logs')} onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy}/> : resource === 'finance' ? <Finance onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} /> : resource === 'performance' ? <Performance onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} /> : resource === 'reports' ? <Reports onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} /> : resource === 'users' ? <Users currentUser={user} onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} /> : resource === 'work-logs' ? <WorkLogs admin={user.permissions.includes('finance.manage')} key={`${user.id}-work-logs`} onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} /> : resource === 'prices' ? <Prices key={`${user.id}-prices`} onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} /> : <Catalog key={`${user.id}-${resource}`} resource={resource} onSessionExpired={sessionExpired} onBusyChange={setCatalogBusy} />}</section>}
      <p role="status" aria-live="polite">{message}</p><footer>Agroperi</footer>
    </main></div><nav className="mobile-nav" aria-label="Navegação móvel">{navigation.filter(item => ['overview', 'work-logs', 'harvests', 'account'].includes(item.key)).map(item => <button key={item.key} disabled={busy || catalogBusy} aria-current={resource === item.key ? 'page' : undefined} onClick={() => navigate(item.key)}><Icon name={item.icon} /><span>{item.key === 'overview' ? 'Hoje' : item.key === 'account' ? 'Conta' : item.key === 'harvests' ? 'Contratos' : item.label}</span></button>)}{user.permissions.includes('finance.manage')&&<button disabled={busy || catalogBusy} aria-label="Abrir menu completo" aria-haspopup="dialog" aria-expanded={mobileMenu} onClick={() => setMobileMenu(true)}><Icon name="menu" /><span>Menu</span></button>}</nav>
    {mobileMenu && <Modal title="Menu" busy={busy || catalogBusy} onClose={() => setMobileMenu(false)}><nav className="mobile-full-menu" aria-label="Todas as telas">{navigation.map(item => <button key={item.key} disabled={busy || catalogBusy} aria-current={resource === item.key ? 'page' : undefined} onClick={() => navigate(item.key)}><Icon name={item.icon} /><span>{item.label}</span><Icon name="arrow" /></button>)}</nav></Modal>}
  </div></CatalogAccess.Provider>
}
