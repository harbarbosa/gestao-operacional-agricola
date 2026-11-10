// @vitest-environment happy-dom
import { act } from 'react'
import { createRoot, type Root } from 'react-dom/client'
import { beforeEach, afterEach, expect, it, vi } from 'vitest'
import Users from './Users'
import { api } from './api'
vi.mock('./api', async importOriginal => ({ ...await importOriginal<typeof import('./api')>(), api: vi.fn() }))
let root: Root; let host: HTMLDivElement
const admin = { id: 1, username: 'admin', groups: ['admin'], permissions: ['users.view', 'users.manage'] }
const metadata = { groups: { admin: {title: 'Administrador', description: 'Administração'}, worker: {title: 'Trabalhador', description: 'Consulta própria'} }, permissions: { 'users.view': 'Consultar utilizadores', 'users.manage': 'Gerir contas', 'reports.view': 'Relatórios' }, matrix: { admin: ['users.view', 'users.manage', 'reports.view'], worker: [] } }
beforeEach(() => {
  Object.assign(globalThis, { IS_REACT_ACT_ENVIRONMENT: true })
  HTMLDialogElement.prototype.showModal = function () { this.open = true }; HTMLDialogElement.prototype.close = function () { this.open = false }
  vi.mocked(api).mockImplementation(async path => path === '/users/metadata' ? metadata : { users: [], total: 0 })
  host = document.createElement('div'); document.body.append(host); root = createRoot(host)
})
afterEach(async () => { await act(async () => root.unmount()); host.remove(); vi.clearAllMocks() })
it('abre cadastro em modal com senha obrigatória e permissões individuais', async () => {
  await act(async () => root.render(<Users currentUser={admin} onSessionExpired={vi.fn()} onBusyChange={vi.fn()} />))
  await act(async () => [...host.querySelectorAll('button')].find(button => button.textContent?.includes('Novo utilizador'))!.click())
  expect(document.querySelector('dialog')!.open).toBe(true)
  expect(document.querySelector<HTMLInputElement>('#user-password')!.required).toBe(true)
  expect(document.querySelectorAll('.permission-option')).toHaveLength(3)
  const role = document.querySelector<HTMLSelectElement>('#user-role')!
  await act(async () => { role.value = 'admin'; role.dispatchEvent(new Event('change', {bubbles:true})) })
  expect([...document.querySelectorAll<HTMLInputElement>('.permission-option input')].every(input => input.checked && input.disabled)).toBe(true)
})
it('consulta sem permissão de gestão não oferece criação de contas', async () => {
  await act(async () => root.render(<Users currentUser={{...admin, permissions: ['users.view']}} onSessionExpired={vi.fn()} onBusyChange={vi.fn()} />))
  expect(host.textContent).not.toContain('Novo utilizador')
})
