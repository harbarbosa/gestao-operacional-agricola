// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot,type Root} from 'react-dom/client'
import {beforeEach,afterEach,expect,it,vi} from 'vitest'
import Finance from './Finance'
import WorkLogs from './WorkLogs'
import {api} from './api'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),api:vi.fn()}))
let root:Root;let host:HTMLDivElement
beforeEach(()=>{Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});HTMLDialogElement.prototype.showModal=function(){this.open=true};HTMLDialogElement.prototype.close=function(){this.open=false};host=document.createElement('div');document.body.append(host);root=createRoot(host)})
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.clearAllMocks()})
it('mostra fechos, saldo exato e registo de pagamentos em modal',async()=>{
 vi.mocked(api).mockResolvedValue({items:[{id:1,worker_id:2,worker_name:'Ana',date_from:'2026-11-01',date_to:'2026-11-15',total_cents:'1001',paid_cents:'100',version:2}],total:1})
 await act(async()=>root.render(<Finance onSessionExpired={vi.fn()} onBusyChange={vi.fn()}/>))
 expect(host.textContent).toContain('10,01 €');expect(host.textContent).toContain('9,01 €')
 await act(async()=>host.querySelector<HTMLButtonElement>('[aria-label="Registar pagamento do fecho 1"]')!.click())
 expect(document.querySelector('dialog')!.open).toBe(true);expect(document.querySelector<HTMLInputElement>('#pay-amount')!.value).toBe('9,01')
})
it('só confirma o fecho da atividade depois de consultar os valores e envia a assinatura conferida',async()=>{
 const item={id:1,version:3,status:'draft',work_date:'2026-11-08',team_name:'Equipa',service_name:'Colheita',client_name:'Cliente'}
 vi.mocked(api).mockImplementation(async(path,options)=>options?.method==='POST'?{item:{...item,status:'closed'}}:path.endsWith('/close-preview')?{item,preview_hash:'a'.repeat(64),allocations:[{worker_id:2,worker_name:'Ana',pool_name:'Grupo A',rate:'1',amount_cents:334}]}:{items:[item],total:1})
 await act(async()=>root.render(<WorkLogs onSessionExpired={vi.fn()} onBusyChange={vi.fn()}/>))
 await act(async()=>host.querySelector<HTMLButtonElement>('[aria-label="Fechar atividade 1"]')!.click())
 expect(document.querySelector('dialog')!.textContent).toContain('3,34 €')
 await act(async()=>[...document.querySelectorAll('dialog button')].find(button=>button.textContent==='Confirmar fecho')!.dispatchEvent(new MouseEvent('click',{bubbles:true})))
 const call=vi.mocked(api).mock.calls.find(([path,options])=>path==='/work-logs/1/close'&&options?.method==='POST')!
 expect(JSON.parse(String(call[1]!.body))).toEqual({version:3,preview_hash:'a'.repeat(64)})
})
