import {useCallback,useEffect,useState} from 'react'
import {api,ApiError,type CurrentUser} from './api'
import {CatalogAccess} from './CatalogAccess'
import {Brand,Icon} from './Icons'
import {Period,Tabs} from './AdminShared'
import {adminToday,type Portfolio} from './adminTypes'
import AdminContractReport from './AdminContractReport'
import AdminDashboard from './AdminDashboard'
import AdminContracts from './AdminContracts'
import AdminContract from './AdminContract'
import AdminPayments from './AdminPayments'
import AdminFinance from './AdminFinance'
import AdminValidation from './AdminValidation'
import AdminPeople from './AdminPeople'
import Reports from './Reports'
import Performance from './Performance'
import Users from './Users'
import Account from './Account'
import Finance from './Finance'
import WorkLogs from './WorkLogs'
import Catalog from './Catalog'
import Prices from './Prices'
export default function AdminApplication({user,onLogout,onSessionExpired}:{user:CurrentUser;onLogout:()=>void;onSessionExpired:()=>void}){
 const [view,setView]=useState('overview'),[contract,setContract]=useState<number|undefined>(),[context,setContext]=useState<number|undefined>(),[from,setFrom]=useState(adminToday().slice(0,8)+'01'),[to,setTo]=useState(adminToday()),[data,setData]=useState<Portfolio|null>(null),[reload,setReload]=useState(0),[error,setError]=useState(''),[busy,setBusy]=useState(false),[loading,setLoading]=useState(true),[account,setAccount]=useState(false),[menu,setMenu]=useState(false),[report,setReport]=useState('production'),[setting,setSetting]=useState('users')
 const refresh=useCallback(()=>setReload(n=>n+1),[])
 useEffect(()=>{let alive=true;setLoading(true);setError('');api<Portfolio>(`/admin/portfolio?date_from=${from}&date_to=${to}`).then(r=>{if(alive){setData(r);setLoading(false)}}).catch(e=>{if(alive){setLoading(false);if(e instanceof ApiError&&e.status===401)onSessionExpired();else setError(e.message)}});return()=>{alive=false}},[from,to,reload,onSessionExpired])
 function navigate(key:string){setView(key);setContract(undefined);setContext(undefined);setMenu(false);setAccount(false)}
 function openContract(id:number){setView('contracts');setContract(id);setMenu(false)}
 function payments(id?:number){setView('payments');setContract(undefined);setContext(id);setMenu(false)}
 const items=[{key:'overview',label:'Visão geral',icon:'home',allowed:true},{key:'contracts',label:'Contratos',icon:'services',allowed:true},{key:'validations',label:'Trabalhos e validações',icon:'clipboard',allowed:user.permissions.includes('field.manage')},{key:'payments',label:'Pagamentos aos trabalhadores',icon:'prices',allowed:true},{key:'finance',label:'Financeiro',icon:'prices',allowed:true},{key:'workers',label:'Trabalhadores',icon:'workers',allowed:user.permissions.includes('catalog.manage')},{key:'clients',label:'Clientes',icon:'clients',allowed:user.permissions.includes('catalog.manage')},{key:'reports',label:'Relatórios',icon:'chart',allowed:user.permissions.includes('reports.view')},{key:'settings',label:'Configurações',icon:'settings',allowed:user.permissions.includes('users.view')||user.permissions.includes('catalog.manage')||user.permissions.includes('prices.manage')}].filter(i=>i.allowed)
 const label=view==='account'?'A minha conta':view==='legacy'?'Fechos anteriores aos contratos':view==='legacy-work'?'Registo de trabalho':items.find(i=>i.key===view)?.label??'Administração'
 return <CatalogAccess.Provider value={{canCreate:user.permissions.includes('catalog.manage'),canCreateWorkers:user.permissions.includes('field.record'),onSessionExpired}}><div className={`app-shell admin-shell ${menu?'admin-menu-open':''}`}><aside className="sidebar"><Brand/><nav aria-label="Menu principal">{items.map(i=><button key={i.key} disabled={busy} aria-current={view===i.key?'page':undefined} onClick={()=>navigate(i.key)}><Icon name={i.icon}/><span>{i.label}</span>{i.key==='validations'&&!!data?.waiting&&<b className="admin-count">{data.waiting}</b>}</button>)}</nav></aside><div className="workspace"><header className="admin-topbar"><button className="admin-menu-toggle" onClick={()=>setMenu(!menu)} aria-label="Abrir navegação">☰</button><span>Administração</span><div className="admin-user"><button onClick={()=>setAccount(!account)} aria-expanded={account}>{user.username} ⌄</button>{account&&<div className="admin-user-menu"><button onClick={()=>navigate('account')}>A minha conta / palavra-passe</button><button disabled={busy} onClick={onLogout}>Terminar sessão</button></div>}</div></header><main className="page-content"><div className="admin-page-head"><span>{contract?data?.contracts.find(c=>c.id===contract)?.name:label}</span>{!['settings','account','legacy','legacy-work'].includes(view)&&<Period from={from} to={to} onApply={(a,b)=>{setFrom(a);setTo(b)}}/>}</div>{error&&<div className="notice" role="alert">{error}<button onClick={refresh}>Tentar novamente</button></div>}{loading&&<p role="status">A atualizar dados…</p>}{data&&<>
 {view==='overview'&&<AdminDashboard data={data} onContract={openContract} onNavigate={navigate}/>}
 {view==='contracts'&&(contract?<AdminContract key={contract} id={contract} portfolio={data} onBack={()=>setContract(undefined)} onPayments={payments} onChanged={refresh} onSessionExpired={onSessionExpired} onLegacy={()=>navigate('legacy-work')}/>:<AdminContracts portfolio={data} onOpen={openContract} onChanged={refresh}/>)}
 {view==='validations'&&<AdminValidation portfolio={data} onChanged={refresh} onSessionExpired={onSessionExpired} onLegacy={()=>navigate('legacy-work')}/>}
 {view==='payments'&&<AdminPayments key={context??'all'} portfolio={data} contractId={context} onChanged={refresh} onLegacy={()=>navigate('legacy')}/>}
 {view==='finance'&&<AdminFinance portfolio={data} onPayments={payments} onChanged={refresh}/>}
 {(view==='workers'||view==='clients')&&<AdminPeople key={view} resource={view} portfolio={data} onContract={openContract} onPayments={payments} onSessionExpired={onSessionExpired} onBusyChange={setBusy}/>}
 {view==='reports'&&<><h2>Relatórios</h2><Tabs items={[{key:'production',label:'Produção'},{key:'contracts',label:'Contratos'},{key:'workers',label:'Trabalhadores'},{key:'finance',label:'Financeiro'},...(user.permissions.includes('performance.view')?[{key:'performance',label:'Desempenho'}]:[])]} value={report} onChange={setReport}/>{report==='finance'?<AdminFinance portfolio={data} onPayments={payments} onChanged={refresh}/>:report==='contracts'?<AdminContractReport portfolio={data} onOpen={openContract}/>:report==='performance'?<Performance onSessionExpired={onSessionExpired} onBusyChange={setBusy}/>:<Reports key={report} onSessionExpired={onSessionExpired} onBusyChange={setBusy}/>}</>}
 {view==='settings'&&<><h2>Configurações</h2><Tabs items={[...(user.permissions.includes('users.view')?[{key:'users',label:'Utilizadores e permissões'}]:[]),...(user.permissions.includes('catalog.manage')?[{key:'units',label:'Unidades'},{key:'services',label:'Serviços'},{key:'sites',label:'Locais'}]:[]),...(user.permissions.includes('prices.manage')?[{key:'prices',label:'Tarifas anteriores'}]:[])]} value={setting} onChange={setSetting}/>{setting==='prices'&&user.permissions.includes('prices.manage')?<Prices onSessionExpired={onSessionExpired} onBusyChange={setBusy}/>:setting==='users'&&user.permissions.includes('users.view')?<Users currentUser={user} onSessionExpired={onSessionExpired} onBusyChange={setBusy}/>:['units','services','sites'].includes(setting)&&user.permissions.includes('catalog.manage')?<Catalog key={setting} resource={setting as 'units'|'services'|'sites'} onSessionExpired={onSessionExpired} onBusyChange={setBusy}/>:<p>Selecione uma configuração disponível.</p>}</>}
 {view==='account'&&<Account user={user} parentBusy={busy} onLogout={onLogout} onSessionExpired={onSessionExpired} onBusyChange={setBusy}/>}
 {view==='legacy'&&<><button className="admin-link" onClick={()=>payments()}>‹ Pagamentos aos trabalhadores</button><Finance onSessionExpired={onSessionExpired} onBusyChange={setBusy}/></>}
 {view==='legacy-work'&&<><button className="admin-link" onClick={()=>navigate('validations')}>‹ Trabalhos e validações</button><WorkLogs admin onSessionExpired={onSessionExpired} onBusyChange={setBusy}/></>}
 </>}</main></div></div></CatalogAccess.Provider>
}
