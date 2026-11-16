// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot} from 'react-dom/client'
import {expect,it,vi} from 'vitest'
import PortugalDateInput from './PortugalDateInput'
it('apresenta DD/MM/AAAA e rejeita datas impossíveis antes de atualizar o diário',async()=>{Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});const host=document.createElement('div');document.body.append(host);const root=createRoot(host),change=vi.fn(),valid=vi.fn();await act(async()=>root.render(<PortugalDateInput id="date" value="2026-11-15" disabled={false} onChange={change} onValidityChange={valid}/>));const input=host.querySelector('input')!;expect(input.value).toBe('15/11/2026');async function type(value:string){await act(async()=>{Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,value);input.dispatchEvent(new Event('input',{bubbles:true}))})}await type('31022026');expect(change).not.toHaveBeenCalled();expect(valid).toHaveBeenLastCalledWith(false);await type('29112026');expect(change).toHaveBeenCalledWith('2026-11-29');expect(valid).toHaveBeenLastCalledWith(true);await act(async()=>root.unmount());host.remove()})
