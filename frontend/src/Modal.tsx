import { useEffect, useId, useRef, type ReactNode } from 'react'
import { createPortal } from 'react-dom'

let modalCount = 0
let originalBodyOverflow = ''

type Props = { title: string; children: ReactNode; busy?: boolean; onClose: () => void }

export default function Modal({ title, children, busy = false, onClose }: Props) {
  const dialog = useRef<HTMLDialogElement>(null)
  const headingId = useId()
  useEffect(() => {
    const element = dialog.current!
    const previousFocus = document.activeElement as HTMLElement | null
    if (modalCount === 0) originalBodyOverflow = document.body.style.overflow
    modalCount += 1
    document.body.style.overflow = 'hidden'
    element.showModal()
    return () => {
      element.close()
      modalCount -= 1
      if (modalCount === 0) document.body.style.overflow = originalBodyOverflow
      if (previousFocus?.isConnected) previousFocus.focus()
    }
  }, [])
  return createPortal(<dialog ref={dialog} className="editor-modal" aria-labelledby={headingId} aria-busy={busy} onCancel={event => { event.preventDefault(); event.stopPropagation(); if (!busy) onClose() }}>
    <header className="modal-header"><h2 id={headingId}>{title}</h2><button type="button" className="modal-close" aria-label="Fechar janela" disabled={busy} onClick={onClose}>×</button></header>
    <div className="modal-body">{children}</div>
  </dialog>, document.body)
}
