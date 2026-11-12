// @vitest-environment happy-dom
import 'fake-indexeddb/auto'
import {act} from 'react'
import {createRoot,type Root} from 'react-dom/client'
import {beforeEach,afterEach,expect,it,vi} from 'vitest'
import OfflineField from './OfflineField'
import {networkApi} from './api'
import {offlineDb,rememberOfflineUser} from './offlineStore'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),networkApi:vi.fn()}))
vi.mock('./Participants',()=>({default:({rows,onChange}:{rows:{worker:string;quantity:string}[];onChange:(r:unknown[])=>void})=><section>{rows.map(r=><p key={r.worker}>Ana · {r.quantity} caixas</p>)}<button onClick={()=>onChange([...rows,{key:'a',worker:'1',present:true,timeIn:'',timeOut:'',nextDay:false,quantity:'10',score:'4',notes:''}])}>Adicionar Ana</button></section>}))
const user={id:3,username:'Campo',groups:['supervisor'],permissions:['field.record']}
let host:HTMLDivElement;let root:Root
async function settle(){await act(async()=>{await new Promise(resolve=>setTimeout(resolve,20))})}
beforeEach(async()=>{Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});vi.stubGlobal('navigator',{onLine:false});localStorage.clear();await Promise.all([offlineDb.profiles.clear(),offlineDb.snapshots.clear(),offlineDb.drafts.clear(),offlineDb.workers.clear(),offlineDb.photos.clear(),offlineDb.portraits.clear()]);await rememberOfflineUser(user);await offlineDb.snapshots.put({owner:3,prepared_at:'2026-11-12T08:00:00Z',contracts:[{id:1,name:'Laranja',status:'active',starts_on:'2026-11-01',client_id:1,client_name:'Quinta',site_id:1,site_name:'Pomar',service_id:1,service_name:'Colheita',payment_unit_id:1,payment_unit_name:'Caixa',billing_unit_id:2,billing_unit_name:'kg'}],workers:[],logs:[]});host=document.createElement('div');document.body.append(host);root=createRoot(host)})
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.unstubAllGlobals()})
it('cria e reabre o trabalho sem rede com gravação automática dos participantes',async()=>{
 await act(async()=>root.render(<OfflineField user={user} onSessionExpired={vi.fn()}/>));await settle()
 expect(host.textContent).toContain('Sem internet');await act(async()=>[...host.querySelectorAll('button')].find(b=>b.textContent==='Novo trabalho')!.click())
 await act(async()=>{const select=host.querySelector<HTMLSelectElement>('#offline-contract')!;select.value='1';select.dispatchEvent(new Event('change',{bubbles:true}))});await settle()
 await act(async()=>[...host.querySelectorAll('button')].find(b=>b.textContent==='Adicionar Ana')!.click());await settle()
 expect((await offlineDb.drafts.where('owner').equals(3).first())?.rows[0].quantity).toBe('10')
 await act(async()=>root.unmount());root=createRoot(host);await act(async()=>root.render(<OfflineField user={user} onSessionExpired={vi.fn()}/>));await settle()
 expect(host.textContent).toContain('Ana · 10 caixas');expect(host.textContent).toContain('Guardado no dispositivo')
})

it('carrega os dados automaticamente na primeira abertura sem botões de preparação',async()=>{
 const snapshot=await offlineDb.snapshots.get(3);await offlineDb.snapshots.clear();vi.stubGlobal('navigator',{onLine:true});vi.mocked(networkApi).mockImplementation(async path=>path==='/auth/me'?{user}:snapshot);
 await act(async()=>root.render(<OfflineField user={user} onSessionExpired={vi.fn()}/>));await settle();await settle();
 expect(networkApi).toHaveBeenCalledWith('/field/offline');expect(await offlineDb.snapshots.get(3)).toBeDefined();expect(host.textContent).toContain('Novo trabalho');expect(host.textContent).not.toContain('Preparar para');expect([...host.querySelectorAll('button')].some(b=>b.textContent==='Sincronizar')).toBe(false)
})
it('recuperar a ligação atualiza os dados automaticamente',async()=>{
 const snapshot=await offlineDb.snapshots.get(3);vi.mocked(networkApi).mockReset();vi.mocked(networkApi).mockImplementation(async path=>path==='/auth/me'?{user}:snapshot);
 await act(async()=>root.render(<OfflineField user={user} onSessionExpired={vi.fn()}/>));await settle();expect(networkApi).not.toHaveBeenCalled();vi.stubGlobal('navigator',{onLine:true});await act(async()=>window.dispatchEvent(new Event('online')));await settle();await settle();expect(networkApi).toHaveBeenCalledWith('/field/offline');expect(host.textContent).toContain('Com ligação')
})
