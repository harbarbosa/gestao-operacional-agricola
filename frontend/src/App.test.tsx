// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot,type Root} from 'react-dom/client'
import {beforeEach,afterEach,expect,it,vi} from 'vitest'
import App from './App'
import {api} from './api'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),api:vi.fn()}))
vi.mock('./Dashboard',()=>({default:()=> <div>Painel</div>}))
vi.mock('./OfflineField',()=>({default:()=> <div>Trabalho de hoje</div>}))
vi.mock('./WorkLogs',()=>({default:()=> <div>Trabalho de hoje</div>}))
vi.mock('./Reports',()=>({default:()=> <div>Conteúdo dos relatórios</div>}))
let root:Root;let host:HTMLDivElement
beforeEach(()=>{Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});HTMLDialogElement.prototype.showModal=function(){this.open=true};HTMLDialogElement.prototype.close=function(){this.open=false};host=document.createElement('div');document.body.append(host);root=createRoot(host)})
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.clearAllMocks()})
it('organiza a administração por contrato, com navegação autorizada e menu que fecha ao navegar',async()=>{
 const permissions=['catalog.manage','field.manage','prices.manage','finance.manage','performance.view','reports.view','users.view'];
 const portfolio={contracts:[],totals:{},movements:[],expenses:[],settlements:[],pending:[],flow:{},waiting:0,settlements_with_balance:0,period:{from:'2026-11-01',to:'2026-11-15'}};
 vi.mocked(api).mockImplementation(async path=>path==='/auth/me'?{user:{id:1,username:'Henrique',groups:['admin'],permissions}}:portfolio);
 await act(async()=>root.render(<App/>));await act(async()=>host.querySelector<HTMLButtonElement>('.admin-menu-toggle')!.click());expect(host.querySelector('.admin-menu-open')).not.toBeNull();
 const nav=host.querySelector('.sidebar nav')!,buttons=[...nav.querySelectorAll<HTMLButtonElement>('button')];expect(buttons.map(b=>b.textContent)).toEqual(['Visão geral','Contratos','Trabalhos e validações','Pagamentos aos trabalhadores','Financeiro','Trabalhadores','Clientes','Relatórios','Configurações']);
 await act(async()=>buttons.find(b=>b.textContent==='Relatórios')!.click());expect(host.querySelector('.admin-menu-open')).toBeNull();expect(host.querySelector('.admin-page-head')!.textContent).toContain('Relatórios');expect(host.textContent).toContain('Conteúdo dos relatórios')
})
it('limita o controlador a hoje, histórico e conta',async()=>{
 vi.mocked(api).mockResolvedValue({user:{id:1,username:'Controlador',groups:['supervisor'],permissions:['field.record','performance.view']}})
 await act(async()=>root.render(<App/>))
 expect([...host.querySelectorAll('.mobile-nav button')].map(b=>b.textContent)).toEqual(['Hoje','Histórico','Conta'])
 expect(host.textContent).toContain('Trabalho de hoje')
 expect(host.querySelector('.mobile-nav [aria-label="Abrir menu completo"]')).toBeNull()
})
