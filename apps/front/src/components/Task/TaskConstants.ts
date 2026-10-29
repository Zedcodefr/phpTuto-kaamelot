import type { TaskStatus } from "./types";

export const STATUS_CONFIG = {
  todo: { label: "À faire", className: "grey" },
  in_progress: { label: "En cours", className: "orange" },
  done: { label: "Terminé", className: "green" },
} satisfies Record<TaskStatus, { label: string; className: string }>;
