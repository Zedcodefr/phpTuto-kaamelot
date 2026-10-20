// Les types reflètent le contrat d'API : docs/api-contract.md
// Un "type" TypeScript n'existe qu'à la compilation : aucune vérification au runtime.

export type TaskStatus = 'todo' | 'in_progress' | 'done'

export type Task = {
  id: number
  title: string
  description: string | null
  status: TaskStatus
  createdAt: string // date ISO 8601
}
