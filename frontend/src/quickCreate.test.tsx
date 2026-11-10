// @vitest-environment happy-dom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, useState } from 'react'
import { createRoot, type Root } from 'react-dom/client'
import ReferenceField from './ReferenceField'
import Modal from './Modal'
import { CatalogAccess } from './CatalogAccess'
import { api, ApiError } from './api'
import { quickCreateValues } from './quickCreate'
import type { Resource } from './catalog'

vi.mock('./api', async importOriginal => ({ ...await importOriginal<typeof import('./api')>(), api: vi.fn() }))
const saved = { id: 71, name: 'Cliente Novo', version: 1, is_active: true }
let root: Root
let host: HTMLDivElement
let parentSubmits = vi.fn<() => void>()
function Parent({ canCreate = true, resource = 'clients' }: { canCreate?: boolean; resource?: Resource }) {
  const [value, setValue] = useState('')
  const [notes, setNotes] = useState('Notas já preenchidas')
  return <CatalogAccess.Provider value={{ canCreate, onSessionExpired: vi.fn() }}><Modal title="Trabalho original" onClose={vi.fn()}><form onSubmit={event => { event.preventDefault(); parentSubmits() }}>
    <textarea aria-label="Notas" value={notes} onChange={event => setNotes(event.target.value)} />
    <ReferenceField field={{ key: 'client', label: 'Cliente', resource, required: true }} value={value} onChange={setValue} disabled={false} />
    <output data-testid="selected-id">{value}</output>
  </form></Modal></CatalogAccess.Provider>
}
async function render(canCreate = true) { await act(async () => root.render(<Parent canCreate={canCreate} />)) }
async function type(input: HTMLInputElement, value: string) {
  await act(async () => {
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(input, value)
    input.dispatchEvent(new Event('input', { bubbles: true }))
  })
}
async function search() {
  const input = document.querySelector<HTMLInputElement>('[role=combobox]')!
  await act(async () => input.focus())
  await type(input, 'Cliente Novo')
  await act(async () => { await vi.advanceTimersByTimeAsync(300) })
}
async function openQuick() {
  await search()
  const create = document.querySelector<HTMLButtonElement>('.select-create')!
  expect(create).not.toBeNull()
  await act(async () => create.click())
}
beforeEach(() => {
  vi.useFakeTimers()
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true })
  // Exercita ciclo dos diálogos e eventos sem depender do top layer visual do navegador.
  HTMLDialogElement.prototype.showModal = function () { this.open = true }
  HTMLDialogElement.prototype.close = function () { this.open = false }
  vi.mocked(api).mockImplementation(async (path, options) => {
    if (options?.method === 'POST') return { item: saved }
    if (path === '/catalog/clients/71') return { item: saved }
    return { items: [], total: 0, page: 1, pageSize: 25 }
  })
  parentSubmits = vi.fn()
  host = document.createElement('div'); document.body.append(host); root = createRoot(host)
})
afterEach(async () => { await act(async () => root.unmount()); expect(document.body.style.overflow).toBe(''); host.remove(); vi.useRealTimers(); vi.clearAllMocks() })

describe('Cadastro rápido nos seletores', () => {
  it('guarda e seleciona o novo registo sem submeter ou perder o formulário anterior', async () => {
    await render(); await openQuick()
    expect(document.querySelectorAll('dialog[open]')).toHaveLength(2)
    const child = [...document.querySelectorAll<HTMLDialogElement>('dialog')].at(-1)!
    expect(child.querySelector<HTMLInputElement>('input')!.value).toBe('Cliente Novo')
    await act(async () => child.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })))
    await act(async () => { await vi.advanceTimersByTimeAsync(30) })
    expect(document.querySelectorAll('dialog[open]')).toHaveLength(1)
    expect(document.querySelector('[data-testid=selected-id]')!.textContent).toBe('71')
    expect(document.querySelector<HTMLInputElement>('[role=combobox]')!.value).toBe('Cliente Novo')
    expect(document.querySelector<HTMLTextAreaElement>('textarea')!.value).toBe('Notas já preenchidas')
    expect(parentSubmits).not.toHaveBeenCalled()
    expect(vi.mocked(api).mock.calls.filter(([, options]) => options?.method === 'POST')).toHaveLength(1)
  })
  it('cancelar o cadastro rápido mantém o modal anterior e a seleção original', async () => {
    await render(); await openQuick()
    const child = [...document.querySelectorAll<HTMLDialogElement>('dialog')].at(-1)!
    await act(async () => child.dispatchEvent(new Event('cancel', { bubbles: true, cancelable: true })))
    expect(document.querySelectorAll('dialog[open]')).toHaveLength(1)
    expect(document.querySelector('[data-testid=selected-id]')!.textContent).toBe('')
    expect(document.querySelector<HTMLTextAreaElement>('textarea')!.value).toBe('Notas já preenchidas')
    expect(parentSubmits).not.toHaveBeenCalled()
  })
  it('erros de validação mantêm o cadastro aberto sem selecionar um registo inexistente', async () => {
    await render(); await openQuick()
    vi.mocked(api).mockRejectedValueOnce(new ApiError(422, 'Verifique o nome.', { name: 'Nome inválido' }))
    const child = [...document.querySelectorAll<HTMLDialogElement>('dialog')].at(-1)!
    await act(async () => child.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })))
    expect(document.querySelectorAll('dialog[open]')).toHaveLength(2)
    expect(child.textContent).toContain('Nome inválido')
    expect(document.querySelector('[data-testid=selected-id]')!.textContent).toBe('')
  })
  it('não oferece criação a quem não tem permissão', async () => {
    await render(false); await search()
    expect(document.querySelector('.select-create')).toBeNull()
  })
  it('não oferece duplicar um nome já encontrado', async () => {
    vi.mocked(api).mockResolvedValue({ items: [saved], total: 1, page: 1, pageSize: 25 })
    await render(); await search()
    expect(document.querySelector('.select-create')).toBeNull()
  })
  it('permite cadastrar uma unidade dentro do cadastro rápido de serviço sem fechar os modais anteriores', async () => {
    await act(async () => root.render(<Parent resource="services" />))
    await openQuick()
    const serviceDialog = [...document.querySelectorAll<HTMLDialogElement>('dialog')].at(-1)!
    const unitInput = serviceDialog.querySelector<HTMLInputElement>('[role=combobox]')!
    await act(async () => unitInput.focus())
    await type(unitInput, 'Caixa especial')
    await act(async () => { await vi.advanceTimersByTimeAsync(300) })
    await act(async () => serviceDialog.querySelector<HTMLButtonElement>('.select-create')!.click())
    expect(document.querySelectorAll('dialog[open]')).toHaveLength(3)
    const unitDialog = [...document.querySelectorAll<HTMLDialogElement>('dialog')].at(-1)!
    const fields = unitDialog.querySelectorAll<HTMLInputElement>('input')
    expect(fields[0].value).toBe('Caixa especial')
    await type(fields[1], 'caixa_especial')
    vi.mocked(api).mockImplementation(async (path, options) => {
      if (options?.method === 'POST' || path === '/catalog/units/91') return { item: { id: 91, version: 1, is_active: true, name: 'Caixa especial' } }
      return { items: [], total: 0, page: 1, pageSize: 25 }
    })
    await act(async () => unitDialog.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })))
    await act(async () => { await vi.advanceTimersByTimeAsync(30) })
    expect(document.querySelectorAll('dialog[open]')).toHaveLength(2)
    expect(unitInput.value).toBe('Caixa especial')
    expect(serviceDialog.querySelector<HTMLInputElement>('input')!.value).toBe('Cliente Novo')
    const ids = [...document.querySelectorAll('[id]')].map(node => node.id)
    expect(new Set(ids).size).toBe(ids.length)
    expect(parentSubmits).not.toHaveBeenCalled()
  })
  it('bloqueia o fecho do cadastro rápido enquanto guarda', async () => {
    await render(); await openQuick()
    let finish!: (value: unknown) => void
    vi.mocked(api).mockImplementationOnce(() => new Promise(resolve => { finish = resolve }))
    const child = [...document.querySelectorAll<HTMLDialogElement>('dialog')].at(-1)!
    await act(async () => child.querySelector('form')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })))
    expect(child.getAttribute('aria-busy')).toBe('true')
    await act(async () => child.dispatchEvent(new Event('cancel', { bubbles: true, cancelable: true })))
    expect(document.querySelectorAll('dialog[open]')).toHaveLength(2)
    await act(async () => finish({ item: saved }))
    expect(document.querySelectorAll('dialog[open]')).toHaveLength(1)
  })
  it('herda apenas o vínculo permitido pelo contexto do seletor', () => {
    expect(quickCreateValues({ key: 'site', label: 'Local', resource: 'sites', query: 'client_id=8&admin=true' }, ' Quinta ')).toEqual({ name: 'Quinta', client_id: '8' })
    expect(quickCreateValues({ key: 'worker', label: 'Trabalhador', resource: 'workers', query: 'team_id=3' }, 'Ana')).toEqual({ name: 'Ana', team_id: '3' })
  })
})
