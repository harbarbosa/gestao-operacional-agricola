import {useEffect,useState} from 'react'
import {formatDate} from './locale'
export default function PortugalDateInput({id,value,disabled,onChange,onValidityChange,required=true}:{id:string;value:string;disabled:boolean;onChange:(value:string)=>void;onValidityChange:(valid:boolean)=>void;required?:boolean}){
 const [text,setText]=useState(value?formatDate(value):'')
 useEffect(()=>setText(value?formatDate(value):''),[value])
 return <input id={id} type="text" inputMode="numeric" autoComplete="off" required={required} placeholder="DD/MM/AAAA" maxLength={10} disabled={disabled} value={text} onChange={e=>{const digits=e.target.value.replace(/\D/g,'').slice(0,8),masked=digits.slice(0,2)+(digits.length>2?'/'+digits.slice(2,4):'')+(digits.length>4?'/'+digits.slice(4):'');setText(masked);if(!digits.length&&!required){e.target.setCustomValidity('');onValidityChange(true);onChange('');return}const [day,month,year]=masked.split('/');const iso=`${year}-${month}-${day}`;const date=new Date(`${iso}T12:00:00Z`);const valid=digits.length===8&&/^\d{4}-\d{2}-\d{2}$/.test(iso)&&!Number.isNaN(date.getTime())&&date.toISOString().slice(0,10)===iso;e.target.setCustomValidity(valid?'':'Use uma data válida no formato DD/MM/AAAA.');onValidityChange(valid);if(valid)onChange(iso)}}/>
}
