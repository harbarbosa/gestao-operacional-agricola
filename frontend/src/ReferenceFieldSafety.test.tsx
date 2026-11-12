// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot,type Root} from 'react-dom/client'
import {afterEach,beforeEach,expect,it,vi} from 'vitest'
import ReferenceField from './ReferenceField'
import {api} from './api'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),api:vi.fn()}))
let root:Root;let host:HTMLDivElement
beforeEach(()=>{Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});vi.useFakeTimers();host=document.createElement('div');document.body.append(host);root=createRoot(host)})
afterEach(async()=>{await act(async()=>root.unmount());host.remove();vi.useRealTimers();vi.clearAllMocks()})
it('mostra bloqueio e alerta no select e impede seleção bloqueada por clique e teclado',async()=>{
 vi.mocked(api).mockResolvedValue({items:[{id:1,name:'Ana',version:1,is_active:true,is_blocked:true,block_reason:'Ocorrência confirmada',evaluation_count:3,performance_avg:2,low_performance:true},{id:2,name:'Pedro',version:1,is_active:true,is_blocked:false,evaluation_count:2,performance_avg:2.5,low_performance:true}]})
 const change=vi.fn();await act(async()=>root.render(<ReferenceField field={{key:'worker',label:'Trabalhador',resource:'workers',source:'field',required:true}} value="" onChange={change} disabled={false}/>))
 await act(async()=>host.querySelector<HTMLInputElement>('input')!.focus());await act(async()=>vi.advanceTimersByTimeAsync(300))
 expect(host.textContent).toContain('BLOQUEADO');expect(host.textContent).toContain('baixo desempenho');expect(host.querySelectorAll('[aria-disabled="true"]')).toHaveLength(1)
 await act(async()=>host.querySelector('[role="option"]')!.dispatchEvent(new MouseEvent('click',{bubbles:true})))
 await act(async()=>host.querySelector('input')!.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true})))
 expect(change).not.toHaveBeenCalled();expect(host.querySelector('[aria-label="Ver histórico de Ana"]')).not.toBeNull()
})
