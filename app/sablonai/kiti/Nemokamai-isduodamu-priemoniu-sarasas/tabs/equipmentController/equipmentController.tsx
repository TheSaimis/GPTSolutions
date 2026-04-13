"use client";

import { EquipmentApi } from "@/lib/api/equipment";
import { useEquipment } from "../../equipmentContext";
import { useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import styles from "../../page.module.scss";
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
    /** Inline list unit field: draft while typing; persist on blur only. */
    const [unitDraftById, setUnitDraftById] = useState<Record<number, string>>({});

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
        const inline = unitDraftById[item.id];
        setEditUnit(inline !== undefined ? inline : (item.unitOfMeasurement ?? "vnt"));
        if (inline !== undefined) {
            setUnitDraftById((prev) => {
                if (prev[item.id] === undefined) {
                    return prev;
                }
                const { [item.id]: _, ...rest } = prev;
                return rest;
            });
        }
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

    /** Inline unit: use DOM value on blur/Enter so the last keystroke is never lost to stale React state. */
    function commitInlineUnit(id: number, raw: string) {
        const next = raw.trim();
        const item = equipment.find((x) => x.id === id);
        const server = (item?.unitOfMeasurement ?? "vnt").trim();
        setUnitDraftById((prev) => {
            if (!(id in prev)) {
                return prev;
            }
            const { [id]: _, ...rest } = prev;
            return rest;
        });
        if (next !== server) {
            void changeUnit(id, next);
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
            {nameRow(nameLt, setNameLt, nameEn, setNameEn, nameRu, setNameRu)}
            {expRow(expLt, setExpLt, expEn, setExpEn, expRu, setExpRu)}
            <div className={styles.row} style={{ marginTop: 12, alignItems: "flex-end" }}>
                <InputFieldText value={unitOfMeasurement} onChange={setUnitOfMeasurement} placeholder="Mato vienetas" />
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
                    const unitDisplay = unitDraftById[item.id] ?? u;
                    const isEditing = editingId === item.id;
                    return (
                        <div key={item.id} className={styles.equipmentItemRow}>
                            <div className={styles.equipmentItemMain}>
                                {!isEditing ? (
                                    <>
                                        <p className={styles.itemText}>{formatEquipmentLine(item)}</p>
                                        <div className={styles.equipmentUnitSelect}>
                                            <InputFieldText
                                                value={unitDisplay}
                                                onChange={(v) =>
                                                    setUnitDraftById((prev) => ({ ...prev, [item.id]: v }))
                                                }
                                                onBlur={(v) => commitInlineUnit(item.id, v)}
                                                onKeyDown={{
                                                    Enter: (v) => commitInlineUnit(item.id, v ?? ""),
                                                }}
                                                disabled={updatingId === item.id}
                                                placeholder="Mato vienetas"
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
                                            <InputFieldText
                                                value={editUnit}
                                                onChange={setEditUnit}
                                                placeholder="Mato vienetas (pvz. vnt, poros, kg)"
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
