"use client";

import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { EquipmentApi } from "@/lib/api/equipment";
import { useMemo, useState } from "react";
import { useEquipment } from "../../equipmentContext";
import styles from "../../page.module.scss";

export default function WorkerEquipmentController() {
    const { workers, equipment, workerItems, setWorkerItems } = useEquipment();
    const [workerId, setWorkerId] = useState<string>("");
    const [equipmentId, setEquipmentId] = useState<string>("");
    const [assigning, setAssigning] = useState(false);

    const workerOptions = useMemo(
        () => workers.map((w) => ({ value: String(w.id), label: w.name })),
        [workers],
    );
    const equipmentOptions = useMemo(
        () => equipment.map((e) => ({ value: String(e.id), label: e.name })),
        [equipment],
    );

    const selectedWorker =
        workerOptions.find((o) => o.value === workerId)?.label ?? "";
    const selectedEquipment =
        equipmentOptions.find((o) => o.value === equipmentId)?.label ?? "";

    async function assign() {
        const w = Number(workerId);
        const e = Number(equipmentId);
        if (!w || !e) return;
        setAssigning(true);
        try {
            const created = await EquipmentApi.createWorkerItem({ workerId: w, equipmentId: e });
            const exists = workerItems.some(
                (item) => item.worker?.id === created.worker?.id && item.equipment?.id === created.equipment?.id,
            );
            if (!exists) {
                setWorkerItems([...workerItems, created]);
            }
        } finally {
            setAssigning(false);
        }
    }

    async function removeItem(id: number) {
        await EquipmentApi.deleteWorkerItem(id);
        setWorkerItems(workerItems.filter((item) => item.id !== id));
    }

    return (
        <div className={styles.card}>
            <div className={styles.row}>
                <InputFieldSelect
                    label="Darbuotojas"
                    options={workerOptions}
                    selected={selectedWorker}
                    placeholder="Pasirinkite darbuotoją"
                    onChange={setWorkerId}
                    search
                />
                <InputFieldSelect
                    label="Priemonė"
                    options={equipmentOptions}
                    selected={selectedEquipment}
                    placeholder="Pasirinkite priemonę"
                    onChange={setEquipmentId}
                    search
                />
                <button
                    type="button"
                    className={styles.button}
                    onClick={assign}
                    disabled={!workerId || !equipmentId || assigning}
                >
                    {assigning ? "Priskiriama..." : "Priskirti"}
                </button>
            </div>
            <div className={styles.list}>
                {workerItems.map((item) => (
                    <div key={item.id} className={styles.item}>
                        <p className={styles.itemText}>
                            {item.worker?.name ?? "-"} - {item.equipment?.name ?? "-"}
                        </p>
                        <button
                            type="button"
                            className={`${styles.button} ${styles.buttonDanger}`}
                            onClick={() => removeItem(item.id)}
                        >
                            Pašalinti
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}