"use client";

import { EquipmentApi } from "@/lib/api/equipment";
import { useEquipment } from "../../equipmentContext";
import { useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import styles from "../../page.module.scss";
import { EQUIPMENT_UNIT_OPTIONS, equipmentUnitLabel } from "./equipmentUnits";
import type { Equipment } from "@/lib/types/equipment/equipment";

function hasPair(name: string, expiration: string): boolean {
    return name.trim() !== "" && expiration.trim() !== "";
}

function buildCreatePayload(
    nameLt: string,
    expLt: string,
    nameEn: string,
    expEn: string,
    nameRu: string,
    expRu: string,
    unitOfMeasurement: string,
): Parameters<typeof EquipmentApi.createEquipment>[0] {
    const base: Parameters<typeof EquipmentApi.createEquipment>[0] = { unitOfMeasurement };
    if (hasPair(nameLt, expLt)) {
        base.name = nameLt.trim();
        base.expirationDate = expLt.trim();
    }
    if (hasPair(nameEn, expEn)) {
        base.nameEn = nameEn.trim();
        base.expirationDateEn = expEn.trim();
    }
    if (hasPair(nameRu, expRu)) {
        base.nameRu = nameRu.trim();
        base.expirationDateRu = expRu.trim();
    }
    return base;
}

export default function EquipmentController() {
    const { equipment, setEquipment } = useEquipment();
    const [nameLt, setNameLt] = useState("");
    const [nameEn, setNameEn] = useState("");
    const [nameRu, setNameRu] = useState("");
    const [expLt, setExpLt] = useState("");
    const [expEn, setExpEn] = useState("");
    const [expRu, setExpRu] = useState("");
    const [unitOfMeasurement, setUnitOfMeasurement] = useState("vnt");
    const [creating, setCreating] = useState(false);
    const [updatingId, setUpdatingId] = useState<number | null>(null);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [eNameLt, setENameLt] = useState("");
    const [eNameEn, setENameEn] = useState("");
    const [eNameRu, setENameRu] = useState("");
    const [eExpLt, setEExpLt] = useState("");
    const [eExpEn, setEExpEn] = useState("");
    const [eExpRu, setEExpRu] = useState("");
    const [editUnit, setEditUnit] = useState("vnt");

    const canCreate =
        hasPair(nameLt, expLt) || hasPair(nameEn, expEn) || hasPair(nameRu, expRu);

    function resetCreateForm() {
        setNameLt("");
        setNameEn("");
        setNameRu("");
        setExpLt("");
        setExpEn("");
        setExpRu("");
        setUnitOfMeasurement("vnt");
    }

    async function createEquipment() {
        if (!canCreate) return;
        setCreating(true);
        try {
            const payload = buildCreatePayload(
                nameLt,
                expLt,
                nameEn,
                expEn,
                nameRu,
                expRu,
                unitOfMeasurement,
            );
            const created = await EquipmentApi.createEquipment(payload);
            setEquipment([...equipment, created]);
            resetCreateForm();
        } finally {
            setCreating(false);
        }
    }

    function startEdit(item: Equipment) {
        setEditingId(item.id);
        setENameLt(item.name ?? "");
        setENameEn(item.nameEn?.trim() ?? "");
        setENameRu(item.nameRu?.trim() ?? "");
        setEExpLt(item.expirationDate ?? "");
        setEExpEn(item.expirationDateEn?.trim() ?? "");
        setEExpRu(item.expirationDateRu?.trim() ?? "");
        setEditUnit(item.unitOfMeasurement ?? "vnt");
    }

    function cancelEdit() {
        setEditingId(null);
    }

    async function saveEdit(id: number) {
        if (!hasPair(eNameLt, eExpLt) && !hasPair(eNameEn, eExpEn) && !hasPair(eNameRu, eExpRu)) {
            return;
        }
        setUpdatingId(id);
        try {
            const name = hasPair(eNameLt, eExpLt)
                ? eNameLt.trim()
                : hasPair(eNameEn, eExpEn)
                  ? eNameEn.trim()
                  : eNameRu.trim();
            const expiration = hasPair(eNameLt, eExpLt)
                ? eExpLt.trim()
                : hasPair(eNameEn, eExpEn)
                  ? eExpEn.trim()
                  : eExpRu.trim();
            const updated = await EquipmentApi.updateEquipment(id, {
                name,
                expirationDate: expiration,
                nameEn: hasPair(eNameEn, eExpEn) ? eNameEn.trim() : "",
                expirationDateEn: hasPair(eNameEn, eExpEn) ? eExpEn.trim() : "",
                nameRu: hasPair(eNameRu, eExpRu) ? eNameRu.trim() : "",
                expirationDateRu: hasPair(eNameRu, eExpRu) ? eExpRu.trim() : "",
                unitOfMeasurement: editUnit,
            });
            setEquipment((prev) => prev.map((x) => (x.id === id ? updated : x)));
            setEditingId(null);
        } finally {
            setUpdatingId(null);
        }
    }

    async function deleteEquipment(id: number) {
        await EquipmentApi.deleteEquipment(id);
        setEquipment(equipment.filter((item) => item.id !== id));
        if (editingId === id) {
            setEditingId(null);
        }
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

    function formatEquipmentLine(item: (typeof equipment)[0]): string {
        const parts = [`${item.name} — ${item.expirationDate}`];
        if (item.nameEn?.trim()) {
            parts.push(`EN: ${item.nameEn} — ${item.expirationDateEn?.trim() || "—"}`);
        }
        if (item.nameRu?.trim()) {
            parts.push(`RU: ${item.nameRu} — ${item.expirationDateRu?.trim() || "—"}`);
        }
        return parts.join(" · ");
    }

    const nameRow = (
        nLt: string,
        setNLt: (v: string) => void,
        nEn: string,
        setNEn: (v: string) => void,
        nRu: string,
        setNRu: (v: string) => void,
    ) => (
        <div className={styles.row}>
            <InputFieldText value={nLt} onChange={setNLt} placeholder="Pavadinimas LT" />
            <InputFieldText value={nEn} onChange={setNEn} placeholder="Pavadinimas EN" />
            <InputFieldText value={nRu} onChange={setNRu} placeholder="Pavadinimas RU" />
        </div>
    );

    const expRow = (
        xLt: string,
        setXLt: (v: string) => void,
        xEn: string,
        setXEn: (v: string) => void,
        xRu: string,
        setXRu: (v: string) => void,
    ) => (
        <div className={styles.row}>
            <InputFieldText value={xLt} onChange={setXLt} placeholder="Tinkamumo terminas LT" />
            <InputFieldText value={xEn} onChange={setXEn} placeholder="Tinkamumo terminas EN" />
            <InputFieldText value={xRu} onChange={setXRu} placeholder="Tinkamumo terminas RU" />
        </div>
    );

    return (
        <div className={styles.card}>
            <p className={styles.mutedSmall} style={{ marginBottom: 10 }}>
                Bent vienoje kalboje užpildykite pavadinimą ir terminą; kiti laukai neprivalomi.
            </p>
            {nameRow(nameLt, setNameLt, nameEn, setNameEn, nameRu, setNameRu)}
            {expRow(expLt, setExpLt, expEn, setExpEn, expRu, setExpRu)}
            <div className={styles.row} style={{ marginTop: 12, alignItems: "flex-end" }}>
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
                    disabled={creating || !canCreate}
                >
                    {creating ? "Kuriama..." : "Pridėti priemonę"}
                </button>
            </div>

            <div className={styles.list}>
                {equipment.map((item) => {
                    const u = item.unitOfMeasurement ?? "vnt";
                    const isEditing = editingId === item.id;
                    return (
                        <div key={item.id} className={styles.equipmentItemRow}>
                            <div className={styles.equipmentItemMain}>
                                {!isEditing ? (
                                    <>
                                        <p className={styles.itemText}>{formatEquipmentLine(item)}</p>
                                        <div className={styles.equipmentUnitSelect}>
                                            <InputFieldSelect
                                                label="Mato vienetas"
                                                options={[...EQUIPMENT_UNIT_OPTIONS]}
                                                selected={equipmentUnitLabel(u)}
                                                onChange={(v) => changeUnit(item.id, v)}
                                                disabled={updatingId === item.id}
                                            />
                                        </div>
                                        <button
                                            type="button"
                                            className={`${styles.button} ${styles.buttonSecondary} ${styles.buttonCompact}`}
                                            style={{ marginTop: 8 }}
                                            onClick={() => startEdit(item)}
                                            disabled={updatingId === item.id}
                                        >
                                            Redaguoti
                                        </button>
                                    </>
                                ) : (
                                    <div style={{ width: "100%" }}>
                                        {nameRow(eNameLt, setENameLt, eNameEn, setENameEn, eNameRu, setENameRu)}
                                        {expRow(eExpLt, setEExpLt, eExpEn, setEExpEn, eExpRu, setEExpRu)}
                                        <div className={styles.row} style={{ marginTop: 12 }}>
                                            <InputFieldSelect
                                                label="Mato vienetas"
                                                options={[...EQUIPMENT_UNIT_OPTIONS]}
                                                selected={
                                                    EQUIPMENT_UNIT_OPTIONS.find((o) => o.value === editUnit)?.label ??
                                                    "Vnt"
                                                }
                                                onChange={(v) => setEditUnit(v)}
                                            />
                                            <button
                                                type="button"
                                                className={styles.button}
                                                onClick={() => saveEdit(item.id)}
                                                disabled={
                                                    updatingId === item.id ||
                                                    (!hasPair(eNameLt, eExpLt) &&
                                                        !hasPair(eNameEn, eExpEn) &&
                                                        !hasPair(eNameRu, eExpRu))
                                                }
                                            >
                                                {updatingId === item.id ? "Saugoma..." : "Išsaugoti"}
                                            </button>
                                            <button
                                                type="button"
                                                className={`${styles.button} ${styles.buttonSecondary}`}
                                                onClick={cancelEdit}
                                                disabled={updatingId === item.id}
                                            >
                                                Atšaukti
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                            {!isEditing ? (
                                <button
                                    type="button"
                                    className={`${styles.button} ${styles.buttonDanger}`}
                                    onClick={() => deleteEquipment(item.id)}
                                >
                                    Ištrinti
                                </button>
                            ) : null}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
