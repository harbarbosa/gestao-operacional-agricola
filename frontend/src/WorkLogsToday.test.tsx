// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot,type Root} from 'react-dom/client'
import {afterEach,beforeEach,expect,it,vi} from 'vitest'
import WorkLogs from './WorkLogs'
import {api} from './api'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),api:vi.fn()}))
let root:Root;let host:HTMLDivElement
const item={id:1,harvest_id:1,work_date:'2026-11-11',version:1,status:'draft',client_id:1,site_id:1,service_id:1,payment_unit_name:'Caixa',billing_unit_name:'kg',pools:[],attendees:[{worker_id:1,worker_name:'Ana',present:true,time_in:null,time_out:null,exit_next_day:false,payment_quantity:'10',score:4,notes:null}]}
beforeEach(()=>{Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});host=document.createElement('div');document.body.append(host);root=createRoot(host);vi.mocked(api).mockImplementation(async(path,options)=>path.includes('workers/1')?{item:{id:1,name:'Ana',is_blocked:false,evaluation_count:0}}:options?.method==='PUT'?{item:{...item,version:2}}:path==='/work-logs/1'?{item}:{items:[item],total:1})})
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.clearAllMocks()})
it('abre o rascunho de hoje na própria tela e guarda antes de encerrar',async()=>{
 await act(async()=>root.render(<WorkLogs todayMode onSessionExpired={vi.fn()} onBusyChange={vi.fn()}/>))
 expect(document.querySelector('dialog')).toBeNull();expect(host.querySelector('.field-workspace')).not.toBeNull();expect(host.querySelector('[aria-label="Produção de Ana"]')).not.toBeNull()
 await act(async()=>[...host.querySelectorAll('button')].find(b=>b.textContent==='Encerrar dia')!.click())
 const calls=vi.mocked(api).mock.calls;const saved=calls.findIndex(([p,o])=>p==='/work-logs/1'&&o?.method==='PUT');const submitted=calls.findIndex(([p,o])=>p==='/work-logs/1/submit'&&o?.method==='POST');expect(saved).toBeGreaterThan(-1);expect(submitted).toBeGreaterThan(saved)
 expect(JSON.parse(String(calls[submitted][1]?.body))).toEqual({version:2})
})
