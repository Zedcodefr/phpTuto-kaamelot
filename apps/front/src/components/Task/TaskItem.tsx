import type { JSX } from "react";
import { STATUS_CONFIG } from "./TaskConstants";
import styles from "./TaskItem.module.css";
import type { Task } from "./types";

type Props = Readonly<{ task: Task }>;

export function TaskItem({ task }: Props): JSX.Element {
  const status = STATUS_CONFIG[task.status];

  return (
    <div className={styles.item}>
      <span className={styles.title}>{task.title}</span>
      {task.description && <span>{task.description}</span>}
      <span>{new Date(task.createdAt).toLocaleString("fr-FR")}</span>
      <span className={styles[status.className]}>{status.label}</span>
    </div>
  );
}
