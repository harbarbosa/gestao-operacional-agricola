// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot} from 'react-dom/client'
import {expect,it,vi} from 'vitest'
import Account from './Account'
import {api} from './api'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),api:vi.fn().mockResolvedValue({status:'ok'})}))
it('altera apenas a palavra-passe da conta atual e limpa os campos',async()=>{
 Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});HTMLDialogElement.prototype.showModal=function(){this.open=true};HTMLDialogElement.prototype.close=function(){this.open=false};const host=document.createElement('div');document.body.append(host);const root=createRoot(host);
 await act(async()=>root.render(<Account user={{id:1,username:'Henrique',groups:['admin'],permissions:[]}} parentBusy={false} onLogout={vi.fn()} onSessionExpired={vi.fn()} onBusyChange={vi.fn()}/>));await act(async()=>host.querySelector('button')!.click());
 for(const [id,value] of [['account-current','Atual123!'],['account-new','Nova12345!'],['account-confirm','Nova12345!']])await act(async()=>{const input=document.getElementById(id)!;Object.getOwnPropertyDescriptor(HTMLInputElement.prototype,'value')!.set!.call(input,value);input.dispatchEvent(new Event('input',{bubbles:true}))});
 await act(async()=>document.querySelector('dialog form')!.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})));expect(api).toHaveBeenCalledWith('/auth/password',{method:'POST',body:JSON.stringify({current_password:'Atual123!',new_password:'Nova12345!',confirmation:'Nova12345!'})});expect(document.querySelector('dialog')).toBeNull();expect(host.textContent).toContain('Palavra-passe alterada');await act(async()=>root.unmount());host.remove();
})
