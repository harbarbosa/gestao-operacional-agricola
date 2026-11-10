// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot,type Root} from 'react-dom/client'
import {beforeEach,afterEach,expect,it,vi} from 'vitest'
import App from './App'
import {api} from './api'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),api:vi.fn()}))
vi.mock('./Dashboard',()=>({default:()=> <div>Painel</div>}))
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
 expect(buttons).toHaveLength(14)
 for(const label of ['Trabalhadores','Produção e pagamentos','Desempenho','Relatórios','Utilizadores e permissões','Conta e definições']) expect(dialog.textContent).toContain(label)
 await act(async()=>buttons.find(button=>button.textContent==='Relatórios')!.dispatchEvent(new MouseEvent('click',{bubbles:true})))
 expect(document.querySelector('dialog')).toBeNull()
 expect(host.querySelector('h1')!.textContent).toBe('Relatórios')
 expect(host.textContent).toContain('Conteúdo dos relatórios')
})
it('limita o menu às permissões do utilizador e permite fechá-lo',async()=>{
 const dialog=await openMenu(['field.record','performance.view'])
 expect([...dialog.querySelectorAll('nav button')].map(button=>button.textContent)).toEqual(['Visão geral','Trabalhos','Desempenho','Conta e definições'])
 await act(async()=>dialog.querySelector<HTMLButtonElement>('[aria-label="Fechar janela"]')!.click())
 expect(document.querySelector('dialog')).toBeNull()
 expect(host.querySelector('.mobile-nav [aria-expanded="false"]')).not.toBeNull()
})
