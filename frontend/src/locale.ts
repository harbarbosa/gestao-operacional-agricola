export const portugalTimeZone = 'Europe/Lisbon'
export function formatDate(value?: string | null) {
  if (!value) return '—'
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value)
  return match ? `${match[3]}/${match[2]}/${match[1]}` : value
}
export function formatDateTime(value?: string | null) {
  if (!value) return '—'
  const parsed = new Date(/[Zz]|[+-]\d\d:\d\d$/.test(value) ? value : `${value.replace(' ', 'T')}Z`)
  return Number.isNaN(parsed.getTime()) ? '—' : new Intl.DateTimeFormat('pt-PT', { timeZone: portugalTimeZone, day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).format(parsed)
}
export function formatTime(value?: string | null) { return value ? value.slice(0, 5) : '—' }
export function formatNumber(value?: string | number | null, minDecimals = 0) {
  if (value === null || value === undefined || value === '') return '—'
  const normalized = String(value).replace(/[\s\u00a0\u202f]/g, '').replace(',', '.')
  if (!/^\d+(\.\d+)?$/.test(normalized)) return '—'
  let [integer, fraction = ''] = normalized.split('.')
  integer = integer.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
  fraction = fraction.replace(/0+$/, '').padEnd(minDecimals, '0')
  return integer + (fraction ? `,${fraction}` : '')
}
export function formatCurrency(value?: string | number | null) { const number = formatNumber(value, 2); return number === '—' ? number : `${number} €` }
export function maskDecimal(value: string) {
  const text = value.replace(/[\s\u00a0\u202f]/g, '').replace(/[^\d.,]/g, '')
  if (!text) return ''
  const separator = text.includes(',') ? text.indexOf(',') : text.indexOf('.')
  const integer = (separator < 0 ? text : text.slice(0, separator)).replace(/\D/g, '').slice(0, 8) || '0'
  const fraction = separator < 0 ? null : text.slice(separator + 1).replace(/\D/g, '').slice(0, 4)
  return integer.replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + (fraction === null ? '' : `,${fraction}`)
}
export function maskPhone(value: string) {
  const digits = value.replace(/\D/g, '')
  if (!digits) return ''
  if (value.startsWith('+') && !digits.startsWith('351')) return `+${digits.slice(0, 15).replace(/(\d{3})(?=\d)/g, '$1 ').trim()}`
  const local = (digits.startsWith('351') ? digits.slice(3) : digits).slice(0, 9)
  return `+351 ${local.replace(/(\d{3})(?=\d)/g, '$1 ').trim()}`.trimEnd()
}

export function formatCents(value: string | number) { const negative = String(value).startsWith('-'); const text = String(value).replace('-', '').padStart(3, '0'); return formatCurrency((negative ? '-' : '') + text.slice(0, -2) + '.' + text.slice(-2)) }
export function statusLabel(status: string) { return ({draft:'Rascunho',canceled:'Cancelado',closed:'Validado',submitted:'Por validar'} as Record<string,string>)[status] ?? status }

export function maskWorkerPhone(value:string){
 let digits=value.replace(/^\s*(?:\+351|00351)\s*/,'').replace(/\D/g,'')
 if(digits.length===12&&digits.startsWith('351'))digits=digits.slice(3)
 return digits.slice(0,15).replace(/(\d{3})(?=\d)/g,'$1 ').trim()
}
