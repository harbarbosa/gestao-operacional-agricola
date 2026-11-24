import type {Harvest} from './Harvests'
import {formatDate,formatNumber,maskDecimal} from './locale'
import {quantityUnits,unitsText} from './fieldView'

export function deliveryAvailable(item:Harvest,id:number){
 const produced=item.workers.filter(w=>Number(w.work_log_id)===id).reduce((n,w)=>n+quantityUnits(w.quantity),0n)
 const delivered=(item.delivery_items??[]).filter(w=>Number(w.work_log_id)===id).reduce((n,w)=>n+quantityUnits(w.quantity),0n)
 return produced>delivered?produced-delivered:0n
}
export default function DeliveryProduction({item,date,rows,onChange,disabled}:{item:Harvest;date:string;rows:Record<string,string>;onChange:(v:Record<string,string>)=>void;disabled:boolean}){
 const validated=item.activities.filter(a=>a.status==='closed')
 const available=validated.filter(a=>deliveryAvailable(item,Number(a.id))>0n)
 const total=Object.values(rows).reduce((n,q)=>n+quantityUnits(q),0n)
 return <fieldset><legend>Associar produção à entrega</legend><p>Selecione os trabalhos e a quantidade de {item.payment_unit_name} incluída nesta entrega.</p>
 {!validated.length?<p role="status">Não existem trabalhos validados neste contrato. Valide o trabalho em Trabalhos e validações antes de registar a entrega.</p>:!available.length?<p role="status">Toda a produção validada já foi associada a entregas.</p>:available.map(a=>{const id=Number(a.id),balance=deliveryAvailable(item,id),selected=Object.hasOwn(rows,id),future=a.work_date>date;return <div key={id} className="admin-panel"><label><input type="checkbox" checked={selected} disabled={disabled||future} onChange={e=>{const next={...rows};if(e.target.checked)next[id]=unitsText(balance);else delete next[id];onChange(next)}}/> {formatDate(a.work_date)} · Trabalho #{id}</label><p>Disponível: <strong>{formatNumber(unitsText(balance))} {item.payment_unit_name}</strong>{future?' · A data da entrega deve ser igual ou posterior à data do trabalho.':''}</p>{selected&&<label>Quantidade entregue ({item.payment_unit_name})<input required inputMode="decimal" maxLength={20} disabled={disabled} value={rows[id]} onChange={e=>onChange({...rows,[id]:maskDecimal(e.target.value)})}/></label>}</div>})}
 <p><strong>Total associado: {formatNumber(unitsText(total))} {item.payment_unit_name}</strong></p></fieldset>
}
