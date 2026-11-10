export type Resource = 'workers' | 'teams' | 'clients' | 'units' | 'services' | 'sites'
export type CatalogItem = {
  id: number; version: number; name: string; is_active: boolean
  [key: string]: string | number | boolean | null
}
export interface CatalogList { items: CatalogItem[]; page: number; pageSize: number; total: number }
export interface Field { key: string; label: string; type?: 'email' | 'textarea' | 'reference'; required?: boolean; max?: number; resource?: Resource; query?: string; source?: 'reports' | 'field' | 'finance'; lookup?: 'managers' }
export const catalogs: Record<Resource, { label: string; fields: Field[] }> = {
  sites: { label: 'Locais', fields: [
    { key: 'name', label: 'Nome do local / exploração', required: true, max: 160 },
    { key: 'client_id', label: 'Cliente', type: 'reference', resource: 'clients', required: true },
    { key: 'locality', label: 'Localidade', max: 160 },
    { key: 'notes', label: 'Observações', type: 'textarea', max: 2000 },
  ] },
  workers: { label: 'Trabalhadores', fields: [
    { key: 'name', label: 'Nome', required: true, max: 160 },
    { key: 'email', label: 'Email', type: 'email', max: 254 },
    { key: 'phone', label: 'Telefone', max: 40 },
    { key: 'team_id', label: 'Equipa', type: 'reference', resource: 'teams' },
    { key: 'notes', label: 'Observações', type: 'textarea', max: 2000 },
  ] },
  teams: { label: 'Equipas', fields: [
    { key: 'manager_user_id', label: 'Responsável (utilizador)', type: 'reference', resource: 'teams', source: 'field', lookup: 'managers' },
    { key: 'name', label: 'Nome', required: true, max: 160 },
    { key: 'responsible_name', label: 'Nome do responsável', max: 120 },
    { key: 'notes', label: 'Observações', type: 'textarea', max: 2000 },
  ] },
  clients: { label: 'Clientes', fields: [
    { key: 'name', label: 'Nome', required: true, max: 160 },
    { key: 'contact_name', label: 'Pessoa de contacto', max: 120 },
    { key: 'email', label: 'Email', type: 'email', max: 254 },
    { key: 'phone', label: 'Telefone', max: 40 },
    { key: 'notes', label: 'Observações', type: 'textarea', max: 2000 },
  ] },
  units: { label: 'Unidades', fields: [
    { key: 'name', label: 'Nome (por exemplo, Hora)', required: true, max: 160 },
    { key: 'code', label: 'Código (por exemplo, hora)', required: true, max: 32 },
  ] },
  services: { label: 'Serviços', fields: [
    { key: 'name', label: 'Nome', required: true, max: 160 },
    { key: 'payment_unit_id', label: 'Unidade para pagar ao trabalhador', type: 'reference', resource: 'units', required: true },
    { key: 'billing_unit_id', label: 'Unidade para cobrar ao cliente', type: 'reference', resource: 'units', required: true },
    { key: 'notes', label: 'Observações', type: 'textarea', max: 2000 },
  ] },
}
export function payloadFor(resource: Resource, values: Record<string, string>, active: boolean, version?: number) {
  const payload: Record<string, string | number | boolean | null> = { is_active: active }
  for (const field of catalogs[resource].fields) {
    const value = values[field.key] ?? ''
    payload[field.key] = field.type === 'reference' ? (value ? Number(value) : null) : value.trim()
  }
  if (version !== undefined) payload.version = version
  return payload
}
