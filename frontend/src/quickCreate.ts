import type { Field, Resource } from './catalog'

export function quickCreateValues(field: Field, name: string): Record<string, string> {
  const values: Record<string, string> = { name: name.trim() }
  const relation: Partial<Record<Resource, string>> = { workers: 'team_id', sites: 'client_id' }
  const key = field.resource ? relation[field.resource] : undefined
  const value = key ? new URLSearchParams(field.query).get(key) : null
  if (key && value && /^[1-9][0-9]*$/.test(value)) values[key] = value
  return values
}
export function hasExactName(names: string[], search: string) {
  const normalize = (value: string) => value.trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-PT')
  return names.some(name => normalize(name) === normalize(search))
}
