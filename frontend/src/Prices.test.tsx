// @vitest-environment happy-dom
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { act } from 'react'
import { createRoot, type Root } from 'react-dom/client'
import Prices from './Prices'
import { api } from './api'
vi.mock('./api', async importOriginal => ({ ...await importOriginal<typeof import('./api')>(), api: vi.fn() }))
let root: Root
let host: HTMLDivElement
beforeEach(() => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true })
  HTMLDialogElement.prototype.showModal = function () { this.open = true }
  HTMLDialogElement.prototype.close = function () { this.open = false }
  vi.mocked(api).mockResolvedValue({ items: [
    { id: 1, service_id: 3, service_name: 'Colheita', kind: 'payment', amount: '10.0000', unit_name: 'Hora', valid_from: '2026-11-01', version: 1 },
    { id: 2, service_id: 4, service_name: 'Poda', kind: 'billing', amount: '20.0000', unit_name: 'Árvore', valid_from: '2026-11-01', version: 1 },
  ], total: 2, page: 1, pageSize: 25 })
  host = document.createElement('div'); document.body.append(host); root = createRoot(host)
})
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.clearAllMocks() })
async function render() { await act(async () => root.render(<Prices onSessionExpired={vi.fn()} onBusyChange={vi.fn()} />)) }
it('lista tarifas de todos os serviços ao abrir a tela', async () => {
  await render()
  expect(api).toHaveBeenCalledWith('/prices?page=1')
  expect(host.querySelector('tbody')!.textContent).toContain('Colheita')
  expect(host.querySelector('tbody')!.textContent).toContain('Poda')
  expect(host.querySelector('.price-filters')).not.toBeNull()
})
it('aplica os filtros no servidor e permite voltar à listagem geral', async () => {
  await render()
  await act(async () => {
    const input = host.querySelector<HTMLInputElement>('#price-search')!
    Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value')!.set!.call(input, 'Colheita')
    input.dispatchEvent(new Event('input', { bubbles: true }))
    const select = host.querySelector<HTMLSelectElement>('#price-kind-filter')!
    select.value = 'payment'; select.dispatchEvent(new Event('change', { bubbles: true }))
  })
  await act(async () => host.querySelector('form.price-filters')!.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true })))
  expect(api).toHaveBeenLastCalledWith('/prices?page=1&q=Colheita&kind=payment')
  await act(async () => [...host.querySelectorAll('button')].find(button => button.textContent === 'Limpar filtros')!.click())
  expect(api).toHaveBeenLastCalledWith('/prices?page=1')
  expect(host.querySelector<HTMLInputElement>('#price-search')!.value).toBe('')
})
it('permite iniciar uma nova tarifa sem ter de filtrar a listagem', async () => {
  await render()
  await act(async () => [...host.querySelectorAll('button')].find(button => button.textContent?.includes('Nova tarifa'))!.click())
  const dialog = document.querySelector('dialog')!
  expect(dialog.open).toBe(true)
  expect(dialog.querySelector('[aria-label="Serviço da tarifa"]')).not.toBeNull()
  expect([...dialog.querySelectorAll('button')].find(button => button.textContent === 'Criar tarifa')!.disabled).toBe(true)
  expect(host.querySelector('tbody')!.textContent).toContain('Poda')
})
