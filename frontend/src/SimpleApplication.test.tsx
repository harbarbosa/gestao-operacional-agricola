// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot} from 'react-dom/client'
import {expect,it,vi} from 'vitest'
import SimpleApplication from './SimpleApplication'
import {api} from './api'
import {attendeePayload} from './field'
vi.mock('./api',async original=>({...await original<typeof import('./api')>(),api:vi.fn()}))
it('mostra apenas produção, trabalhadores e pagamentos sem módulos desativados',async()=>{
 Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});vi.mocked(api).mockResolvedValue({worker:null,summary:{opening_cents:0,earned_cents:11000,paid_cents:2000,balance_cents:9000},production:{'Caixa de 200 kg':'10'},items:[],payments:[]});const host=document.createElement('div'),root=createRoot(host);await act(async()=>root.render(<SimpleApplication user={{id:1,username:'Admin',groups:['admin'],permissions:['finance.manage','catalog.manage','field.manage','users.view'],simple_mode:true}} onLogout={vi.fn()} onSessionExpired={vi.fn()}/>));expect([...host.querySelectorAll('nav button')].map(b=>b.textContent)).toEqual(['Visão geral','Trabalhadores','Produção','Pagamentos','Relatórios','Configurações']);expect(host.textContent).toContain('90,00 €');expect(host.textContent).toContain('Caixa de 200 kg');expect(host.textContent).not.toContain('Contrato');await act(async()=>root.unmount())
})
it('preserva o preço próprio de cada trabalhador no envio, incluindo participantes de grupo',()=>{
 const row={key:'1',worker:'1',present:true,timeIn:'',timeOut:'',nextDay:false,quantity:'',score:'4',notes:'',poolKey:'g',paymentRate:'12,50'};expect(attendeePayload(row)).toMatchObject({worker_id:1,payment_rate:'12.50',pool_key:'g',payment_quantity:null});expect(attendeePayload({...row,worker:'2',paymentRate:'10'}).payment_rate).toBe('10')
})
