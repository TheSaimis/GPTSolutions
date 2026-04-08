"use client";

import { EquipmentApi } from "@/lib/api/equipment";
import { useEquipment } from "../../equipmentContext";
import { useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import styles from "../../page.module.scss";

export default function EquipmentController() {
    const { equipment, setEquipment } = useEquipment();
    const [name, setName] = useState("");
    const [expirationDate, setExpirationDate] = useState("");
    const [creating, setCreating] = useState(false);

    async function createEquipment() {
        if (!name.trim() || !expirationDate.trim()) return;
        setCreating(true);
        try {
            const created = await EquipmentApi.createEquipment({
                name: name.trim(),
                expirationDate: expirationDate.trim(),
            });
            setEquipment([...equipment, created]);
            setName("");
            setExpirationDate("");
        } finally {
            setCreating(false);
        }
    }

    async function deleteEquipment(id: number) {
        await EquipmentApi.deleteEquipment(id);
        setEquipment(equipment.filter((item) => item.id !== id));
    }

    return (
        <div className={styles.card}>
            <div className={styles.row}>
                <InputFieldText
                    value={name}
                    onChange={setName}
                    placeholder="Priemonės pavadinimas"
                />
                <InputFieldText
                    value={expirationDate}
                    onChange={setExpirationDate}
                    placeholder="Tinkamumo periodas (pvz. 12 mėn.)"
                />
                <button
                    type="button"
                    className={styles.button}
                    onClick={createEquipment}
                    disabled={creating || !name.trim() || !expirationDate.trim()}
                >
                    {creating ? "Kuriama..." : "Pridėti priemonę"}
                </button>
            </div>
            <div className={styles.list}>
                {equipment.map((item) => (
                    <div key={item.id} className={styles.item}>
                        <p className={styles.itemText}>
                            {item.name} - {item.expirationDate}
                        </p>
                        <button
                            type="button"
                            className={`${styles.button} ${styles.buttonDanger}`}
                            onClick={() => deleteEquipment(item.id)}
                        >
                            Ištrinti
                        </button>
                    </div>
                ))}
            </div>
        </div>
    );
}