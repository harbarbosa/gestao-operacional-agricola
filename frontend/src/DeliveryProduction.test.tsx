// @vitest-environment happy-dom
import {act} from 'react'
import {createRoot} from 'react-dom/client'
import {expect,it,vi} from 'vitest'
import DeliveryProduction,{deliveryAvailable} from './DeliveryProduction'
import type {Harvest} from './Harvests'
const item={payment_unit_name:'caixas',workers:[{work_log_id:9,quantity:'26'},{work_log_id:9,quantity:'20'}],delivery_items:[{work_log_id:9,quantity:'10'}],activities:[{id:9,status:'closed',work_date:'2026-11-15'}]} as Harvest
it('desconta entregas anteriores e seleciona apenas o saldo disponível do trabalho',async()=>{
 Object.assign(globalThis,{IS_REACT_ACT_ENVIRONMENT:true});expect(deliveryAvailable(item,9)).toBe(360000n)
 const host=document.createElement('div'),root=createRoot(host),change=vi.fn();await act(async()=>root.render(<DeliveryProduction item={item} date="2026-11-15" rows={{}} onChange={change} disabled={false}/>));expect(host.textContent).toContain('36 caixas');await act(async()=>host.querySelector<HTMLInputElement>('input[type=checkbox]')!.click());expect(change).toHaveBeenCalledWith({'9':'36'});await act(async()=>root.unmount())
})
it('explica porque não há produção para selecionar quando falta validação',async()=>{
 const host=document.createElement('div'),root=createRoot(host);await act(async()=>root.render(<DeliveryProduction item={{...item,activities:[]}} date="2026-11-15" rows={{}} onChange={vi.fn()} disabled={false}/>));expect(host.textContent).toContain('Valide o trabalho');expect(host.querySelector('input')).toBeNull();await act(async()=>root.unmount())
})
