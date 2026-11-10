import { createContext } from 'react'

export const CatalogAccess = createContext<{ canCreate: boolean; canCreateWorkers?: boolean; onSessionExpired: () => void }>({ canCreate: false, canCreateWorkers: false, onSessionExpired: () => {} })
