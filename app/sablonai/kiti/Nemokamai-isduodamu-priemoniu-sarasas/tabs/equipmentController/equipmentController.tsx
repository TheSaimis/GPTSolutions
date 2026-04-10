"use client";

import { EquipmentApi } from "@/lib/api/equipment";
import { useEquipment } from "../../equipmentContext";
import { useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import styles from "../../page.module.scss";
import { EQUIPMENT_UNIT_OPTIONS, equipmentUnitLabel } from "./equipmentUnits";

export default function EquipmentController() {
    const { equipment, setEquipment } = useEquipment();
    const [name, setName] = useState("");
    const [expirationDate, setExpirationDate] = useState("");
    const [unitOfMeasurement, setUnitOfMeasurement] = useState("vnt");
    const [creating, setCreating] = useState(false);
    const [updatingId, setUpdatingId] = useState<number | null>(null);

    async function createEquipment() {
        if (!name.trim() || !expirationDate.trim()) return;
        setCreating(true);
        try {
            const created = await EquipmentApi.createEquipment({
                name: name.trim(),
                expirationDate: expirationDate.trim(),
                unitOfMeasurement,
            });
            setEquipment([...equipment, created]);
            setName("");
            setExpirationDate("");
            setUnitOfMeasurement("vnt");
        } finally {
            setCreating(false);
        }
    }

    async function deleteEquipment(id: number) {
        await EquipmentApi.deleteEquipment(id);
        setEquipment(equipment.filter((item) => item.id !== id));
    }

    async function changeUnit(id: number, next: string) {
        setUpdatingId(id);
        try {
            const updated = await EquipmentApi.updateEquipment(id, { unitOfMeasurement: next });
            setEquipment((prev) => prev.map((x) => (x.id === id ? updated : x)));
        } finally {
            setUpdatingId(null);
        }
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
                <InputFieldSelect
                    label="Mato vienetas"
                    options={[...EQUIPMENT_UNIT_OPTIONS]}
                    selected={EQUIPMENT_UNIT_OPTIONS.find((o) => o.value === unitOfMeasurement)?.label ?? "Vnt"}
                    onChange={(v) => setUnitOfMeasurement(v)}
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
                {equipment.map((item) => {
                    const u = item.unitOfMeasurement ?? "vnt";
                    return (
                        <div key={item.id} className={styles.equipmentItemRow}>
                            <div className={styles.equipmentItemMain}>
                                <p className={styles.itemText}>
                                    {item.name} — {item.expirationDate}
                                </p>
                                <div className={styles.equipmentUnitSelect}>
                                    <InputFieldSelect
                                        label="Mato vienetas"
                                        options={[...EQUIPMENT_UNIT_OPTIONS]}
                                        selected={equipmentUnitLabel(u)}
                                        onChange={(v) => changeUnit(item.id, v)}
                                        disabled={updatingId === item.id}
                                    />
                                </div>
                            </div>
                            <button
                                type="button"
                                className={`${styles.button} ${styles.buttonDanger}`}
                                onClick={() => deleteEquipment(item.id)}
                            >
                                Ištrinti
                            </button>
                        </div>
                    );
                })}
            </div>
        </div>
    );
}