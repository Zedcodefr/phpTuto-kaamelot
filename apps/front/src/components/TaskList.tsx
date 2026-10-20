import { useEffect, useState } from 'react'
import type { Task } from '../types'

/**
 * Affiche la liste des tâches renvoyée par GET /api/tasks.
 *
 * Un composant React = une fonction qui reçoit des props et renvoie du JSX.
 * Elle est ré-exécutée à chaque changement d'état (useState) : on décrit
 * "à quoi ressemble l'écran pour cet état", React se charge de mettre le DOM à jour.
 */
export function TaskList() {
  const [tasks, setTasks] = useState<Task[]>([])
  const [error, setError] = useState<string | null>(null)

  // useEffect(..., []) : exécuté une fois après le premier affichage (≈ OnInitializedAsync en Blazor)
  useEffect(() => {
    fetch('/api/tasks')
      .then((response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`)
        }
        return response.json() as Promise<Task[]>
      })
      .then(setTasks)
      .catch((e: Error) => setError(`Impossible de charger les tâches (${e.message})`))
  }, [])

  if (error) {
    return <p className="error">{error}</p>
  }

  return (
    <ul className="task-list">
      {tasks.map((task) => (
        // "key" permet à React d'identifier chaque élément d'une liste entre deux rendus
        <li key={task.id}>
          <strong>{task.title}</strong> — {task.status}
        </li>
      ))}
    </ul>
  )
}
