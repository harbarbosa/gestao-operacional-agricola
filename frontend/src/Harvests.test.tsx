// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot,type Root} from 'react-dom/client'
import {afterEach,beforeEach,expect,it,vi} from 'vitest'
import Harvests from './Harvests'
import WorkLogs from './WorkLogs'
import {api} from './api'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),api:vi.fn()}))
let root:Root;let host:HTMLDivElement
beforeEach(()=>{Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});HTMLDialogElement.prototype.showModal=function(){this.open=true};HTMLDialogElement.prototype.close=function(){this.open=false};host=document.createElement('div');document.body.append(host);root=createRoot(host)})
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.clearAllMocks()})
it('apresenta fluxo e quantidades separadas durante a colheita',async()=>{
 const item={id:1,name:'Laranja',status:'active',starts_on:'2026-11-01',payment_unit_name:'Caixa',billing_unit_name:'kg',activities:[],workers:[],deliveries:[],movements:[],summary:{received_cents:600000,paid_cents:450000,cash_cents:150000,paid_quantity:'500',received_quantity:'1000'}}
 vi.mocked(api).mockImplementation(async(path)=>path==='/harvests/1'?{item}:{items:path==='/harvests'?[item]:[]})
 await act(async()=>root.render(<Harvests admin={true} onSessionExpired={vi.fn()} onBusyChange={vi.fn()} onActivities={vi.fn()}/>))
 await act(async()=>[...host.querySelectorAll('button')].find(b=>b.textContent==='Abrir')!.click())
 expect(host.textContent).toContain('6 000,00 €');expect(host.textContent).toContain('4 500,00 €');expect(host.textContent).toContain('1 500,00 €');expect(host.textContent).toContain('500 Caixa');expect(host.textContent).toContain('1 000 kg')
})
it('controlador não recebe ações de valores ou contratos',async()=>{
 vi.mocked(api).mockResolvedValue({items:[]})
 await act(async()=>root.render(<Harvests admin={false} onSessionExpired={vi.fn()} onBusyChange={vi.fn()} onActivities={vi.fn()}/>))
 expect(host.textContent).not.toContain('Novo contrato');expect(host.textContent).not.toContain('Registar movimento');expect(vi.mocked(api).mock.calls.some(([p])=>p==='/contracts')).toBe(false)
})
it('encerra o dia por envio operacional sem consultar valores',async()=>{
 const item={id:1,harvest_id:2,work_date:'2026-11-02',version:3,status:'draft',client_name:'Cliente',service_name:'Colheita'}
 vi.mocked(api).mockResolvedValue({items:[item],total:1})
 await act(async()=>root.render(<WorkLogs onSessionExpired={vi.fn()} onBusyChange={vi.fn()}/>))
 await act(async()=>host.querySelector<HTMLButtonElement>('[aria-label="Fechar atividade 1"]')!.click())
 expect(vi.mocked(api).mock.calls.some(([p,o])=>p==='/work-logs/1/submit'&&o?.method==='POST')).toBe(true)
 expect(vi.mocked(api).mock.calls.some(([p])=>p.includes('close-preview'))).toBe(false)
})
