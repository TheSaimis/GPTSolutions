"use client";

import { useEffect, useState } from "react";
import { WorkersApi } from "@/lib/api/workers";
import type { Worker } from "@/lib/types/Worker";
import InputFieldText from "@/components/inputFields/inputFieldText";
import styles from "./page.module.scss";

export default function PareigosPage() {
  const [workers, setWorkers] = useState<Worker[]>([]);
  const [newWorkerName, setNewWorkerName] = useState("");
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    WorkersApi.getAll()
      .then(setWorkers)
      .finally(() => setLoading(false));
  }, []);

  async function createWorker() {
    const name = newWorkerName.trim();
    if (!name) return;
    const created = await WorkersApi.create({ name });
    setWorkers((prev) => [...prev, created]);
    setNewWorkerName("");
  }

  async function updateWorker(id: number, name: string) {
    const trimmed = name.trim();
    if (!trimmed) return;
    const updated = await WorkersApi.update(id, { name: trimmed });
    setWorkers((prev) => prev.map((worker) => (worker.id === id ? updated : worker)));
  }

  async function deleteWorker(id: number) {
    await WorkersApi.delete(id);
    setWorkers((prev) => prev.filter((worker) => worker.id !== id));
  }

  if (loading) {
    return <div className={styles.page}>Kraunama...</div>;
  }

  return (
    <div className={styles.page}>
      <div className={styles.card}>
        <h1 className={styles.title}>Darbuotojų tipai</h1>

        <div className={styles.createRow}>
          <InputFieldText
            value={newWorkerName}
            onChange={setNewWorkerName}
            placeholder="Naujas darbuotojo tipas"
          />
          <button
            type="button"
            className={styles.primaryButton}
            onClick={createWorker}
            disabled={newWorkerName.trim() === ""}
          >
            Pridėti
          </button>
        </div>

        <div className={styles.list}>
          {workers.map((worker) => (
            <WorkerRow
              key={worker.id}
              worker={worker}
              onSave={updateWorker}
              onDelete={deleteWorker}
            />
          ))}
        </div>
      </div>
    </div>
  );
}

function WorkerRow({
  worker,
  onSave,
  onDelete,
}: {
  worker: Worker;
  onSave: (id: number, name: string) => Promise<void>;
  onDelete: (id: number) => Promise<void>;
}) {
  const [name, setName] = useState(worker.name);

  return (
    <div className={styles.workerRow}>
      <InputFieldText value={name} onChange={setName} placeholder="Pavadinimas" />
      <button
        type="button"
        className={styles.secondaryButton}
        onClick={() => onSave(worker.id, name)}
        disabled={name.trim() === ""}
      >
        Išsaugoti
      </button>
      <button type="button" className={styles.dangerButton} onClick={() => onDelete(worker.id)}>
        Šalinti
      </button>
    </div>
  );
}
