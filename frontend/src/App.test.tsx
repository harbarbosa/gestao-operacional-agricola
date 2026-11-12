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
async function openMenu(permissions:string[]){
 vi.mocked(api).mockResolvedValue({user:{id:1,username:'Henrique',groups:['admin'],permissions}})
 await act(async()=>root.render(<App/>))
 await act(async()=>host.querySelector<HTMLButtonElement>('.mobile-nav [aria-label="Abrir menu completo"]')!.click())
 return document.querySelector('dialog')!
}
it('disponibiliza todas as áreas autorizadas e fecha o menu ao navegar',async()=>{
 const dialog=await openMenu(['catalog.manage','field.manage','prices.manage','finance.manage','performance.view','reports.view','users.view'])
 expect(dialog.open).toBe(true)
 const buttons=[...dialog.querySelectorAll('nav button')]
 expect(buttons).toHaveLength(10)
 for(const label of ['Trabalhadores','Fechos anteriores','Desempenho','Relatórios','Utilizadores e permissões','Conta e definições']) expect(dialog.textContent).toContain(label)
 await act(async()=>buttons.find(button=>button.textContent==='Relatórios')!.dispatchEvent(new MouseEvent('click',{bubbles:true})))
 expect(document.querySelector('dialog')).toBeNull()
 expect(host.querySelector('h1')!.textContent).toBe('Relatórios')
 expect(host.textContent).toContain('Conteúdo dos relatórios')
})
it('limita o controlador a hoje, histórico e conta',async()=>{
 vi.mocked(api).mockResolvedValue({user:{id:1,username:'Controlador',groups:['supervisor'],permissions:['field.record','performance.view']}})
 await act(async()=>root.render(<App/>))
 expect([...host.querySelectorAll('.mobile-nav button')].map(b=>b.textContent)).toEqual(['Hoje','Histórico','Conta'])
 expect(host.textContent).toContain('Trabalho de hoje')
 expect(host.querySelector('.mobile-nav [aria-label="Abrir menu completo"]')).toBeNull()
})
