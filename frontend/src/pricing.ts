export type PriceKind = 'payment' | 'billing'
export interface Price {
  id: number; service_id: number; kind: PriceKind; worker_id: number | null; client_id: number | null
  worker_name?: string | null; client_name?: string | null
  amount: string; currency: string; unit_id: number; unit_name: string; unit_code: string
  valid_from: string; valid_to: string | null; version: number; canceled_at: string | null; cancel_reason: string | null
}
export interface PriceList { items: Price[]; total: number; page: number; pageSize: number }
export interface PricePreview { price: Price; quantity: string; total: string; provisional: boolean }

// Nunca converter valores monetários para Number: o servidor calcula com decimais exatos.
export function canonicalDecimal(value: string): string {
  const text = value.trim().replace(',', '.')
  if (!/^[0-9]{1,8}(\.[0-9]{1,4})?$/.test(text)) throw new Error('Use até 8 algarismos inteiros e 4 casas decimais, sem separador de milhares.')
  const [integer, fraction] = text.split('.')
  return integer.replace(/^0+(?=\d)/, '') + (fraction === undefined ? '' : `.${fraction}`)
}
export function scopeFor(kind: PriceKind, target: string) {
  return { kind, worker_id: kind === 'payment' && target ? Number(target) : null, client_id: kind === 'billing' && target ? Number(target) : null }
}
