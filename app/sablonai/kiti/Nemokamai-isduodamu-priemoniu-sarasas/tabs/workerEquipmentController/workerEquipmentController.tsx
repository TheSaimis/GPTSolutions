"use client";

import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldText from "@/components/inputFields/inputFieldText";
import { EquipmentApi } from "@/lib/api/equipment";
import { CompanyWorkersApi } from "@/lib/api/companyWorkers";
import { CompanyApi } from "@/lib/api/companies";
import { useCallback, useEffect, useMemo, useState, type CSSProperties } from "react";
import { useEquipment } from "../../equipmentContext";
import { EQUIPMENT_UNIT_OPTIONS, equipmentUnitLabel } from "../equipmentController/equipmentUnits";
import type { Equipment } from "@/lib/types/equipment/equipment";
import styles from "../../page.module.scss";
import ctrl from "../../../pazyma/controllers.module.scss";
import type { Company, CompanyWorker } from "@/lib/types/Company";
import type { AapEquipmentGroupRow } from "@/lib/types/aapEquipmentGroup";

const DOC_QTY_MIN = 1;
const DOC_QTY_MAX = 99999;

function parseDocumentQuantity(raw: string): number {
    const n = parseInt(String(raw).replace(/\s/g, ""), 10);
    if (!Number.isFinite(n) || n < DOC_QTY_MIN) return DOC_QTY_MIN;
    if (n > DOC_QTY_MAX) return DOC_QTY_MAX;
    return n;
}

const quantityInputStyle: CSSProperties = {
    width: 80,
    marginTop: 4,
    padding: "8px 10px",
    borderRadius: 10,
    border: "1px solid #e2e8f0",
};

type GroupEditorProps = {
    group: AapEquipmentGroupRow;
    workerOptions: { value: string; label: string }[];
    equipmentOptions: { value: string; label: string }[];
    onReplaceGroup: (g: AapEquipmentGroupRow) => void;
    onRemoveGroup: (id: number) => void;
    onEquipmentUpdated: (updated: Equipment) => void | Promise<void>;
};

function AapGroupEditor({
    group,
    workerOptions,
    equipmentOptions,
    onReplaceGroup,
    onRemoveGroup,
    onEquipmentUpdated,
}: GroupEditorProps) {
    const [workerId, setWorkerId] = useState("");
    const [equipmentId, setEquipmentId] = useState("");
    const [addEquipmentQty, setAddEquipmentQty] = useState("1");
    const [busy, setBusy] = useState(false);
    const [editingEquipmentId, setEditingEquipmentId] = useState<number | null>(null);
    const [editName, setEditName] = useState("");
    const [editExpiration, setEditExpiration] = useState("");
    const [editUnit, setEditUnit] = useState("vnt");
    const [editingGroupName, setEditingGroupName] = useState(false);
    const [groupNameDraft, setGroupNameDraft] = useState(group.name);

    useEffect(() => {
        if (!editingGroupName) {
            setGroupNameDraft(group.name);
        }
    }, [group.name, editingGroupName]);

    const wLabel = workerOptions.find((o) => o.value === workerId)?.label ?? "";
    const eLabel = equipmentOptions.find((o) => o.value === equipmentId)?.label ?? "";

    async function addWorker() {
        const w = Number(workerId);
        if (!w) return;
        setBusy(true);
        try {
            const next = await EquipmentApi.addWorkerToAapEquipmentGroup(group.id, w);
            onReplaceGroup(next);
            setWorkerId("");
        } finally {
            setBusy(false);
        }
    }

    async function addEquipment() {
        const e = Number(equipmentId);
        if (!e) return;
        const qty = parseDocumentQuantity(addEquipmentQty);
        setBusy(true);
        try {
            const next = await EquipmentApi.addEquipmentToAapEquipmentGroup(group.id, e, { quantity: qty });
            onReplaceGroup(next);
            setEquipmentId("");
            setAddEquipmentQty("1");
        } finally {
            setBusy(false);
        }
    }

    async function patchGroupEquipmentQuantity(equipmentEntityId: number, raw: string) {
        const qty = parseDocumentQuantity(raw);
        const row = group.equipment.find((r) => r.equipment.id === equipmentEntityId);
        if (row && (row.quantity ?? 1) === qty) return;
        setBusy(true);
        try {
            const next = await EquipmentApi.patchAapEquipmentGroupEquipment(group.id, equipmentEntityId, {
                quantity: qty,
            });
            onReplaceGroup(next);
        } finally {
            setBusy(false);
        }
    }

    async function removeWorker(wid: number) {
        setBusy(true);
        try {
            const next = await EquipmentApi.removeWorkerFromAapEquipmentGroup(group.id, wid);
            onReplaceGroup(next);
        } finally {
            setBusy(false);
        }
    }

    async function removeEquipment(eid: number) {
        setBusy(true);
        try {
            const next = await EquipmentApi.removeEquipmentFromAapEquipmentGroup(group.id, eid);
            onReplaceGroup(next);
        } finally {
            setBusy(false);
        }
    }

    function beginEditEquipment(row: AapEquipmentGroupRow["equipment"][number]) {
        const e = row.equipment;
        setEditingEquipmentId(e.id);
        setEditName(e.name);
        setEditExpiration(e.expirationDate);
        setEditUnit(e.unitOfMeasurement ?? "vnt");
    }

    function cancelEditEquipment() {
        setEditingEquipmentId(null);
    }

    async function saveEditEquipment() {
        if (editingEquipmentId == null) return;
        const name = editName.trim();
        const expirationDate = editExpiration.trim();
        if (!name || !expirationDate) return;
        setBusy(true);
        try {
            const updated = await EquipmentApi.updateEquipment(editingEquipmentId, {
                name,
                expirationDate,
                unitOfMeasurement: editUnit,
            });
            await onEquipmentUpdated(updated);
            setEditingEquipmentId(null);
        } finally {
            setBusy(false);
        }
    }

    async function saveGroupName() {
        const name = groupNameDraft.trim();
        if (!name) return;
        if (name === group.name) {
            setEditingGroupName(false);
            return;
        }
        setBusy(true);
        try {
            const updated = await EquipmentApi.updateAapEquipmentGroup(group.id, { name });
            onReplaceGroup(updated);
            setEditingGroupName(false);
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className={ctrl.panel}>
            <div
                style={{
                    display: "flex",
                    flexWrap: "wrap",
                    justifyContent: "space-between",
                    gap: 8,
                    alignItems: "center",
                    marginBottom: 12,
                }}
            >
                <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: 8, flex: 1, minWidth: 0 }}>
                    {editingGroupName ? (
                        <>
                            <input
                                type="text"
                                value={groupNameDraft}
                                onChange={(e) => setGroupNameDraft(e.target.value)}
                                aria-label="Grupės pavadinimas"
                                style={{
                                    flex: 1,
                                    minWidth: 160,
                                    maxWidth: "100%",
                                    padding: "10px 12px",
                                    borderRadius: 10,
                                    border: "1px solid #e2e8f0",
                                }}
                            />
                            <button
                                type="button"
                                className={`${ctrl.button} ${ctrl.buttonPrimary}`}
                                disabled={busy || !groupNameDraft.trim()}
                                onClick={saveGroupName}
                            >
                                Išsaugoti
                            </button>
                            <button
                                type="button"
                                className={`${ctrl.button} ${ctrl.buttonGhost}`}
                                disabled={busy}
                                onClick={() => {
                                    setEditingGroupName(false);
                                    setGroupNameDraft(group.name);
                                }}
                            >
                                Atšaukti
                            </button>
                        </>
                    ) : (
                        <>
                            <h4 className={ctrl.workerAddTitle} style={{ margin: 0 }}>
                                {group.name}
                            </h4>
                            <button
                                type="button"
                                className={`${ctrl.button} ${ctrl.buttonGhost}`}
                                disabled={busy}
                                onClick={() => {
                                    setGroupNameDraft(group.name);
                                    setEditingGroupName(true);
                                }}
                            >
                                Pervadinti
                            </button>
                        </>
                    )}
                </div>
                <button
                    type="button"
                    className={`${ctrl.button} ${ctrl.buttonDanger}`}
                    disabled={busy}
                    onClick={() => {
                        if (window.confirm(`Pašalinti grupę „${group.name}“?`)) {
                            onRemoveGroup(group.id);
                        }
                    }}
                >
                    Šalinti grupę
                </button>
            </div>

            <h4 className={ctrl.workerAddTitle}>1. Darbuotojų tipai (pareigybės)</h4>
            <p className={ctrl.workerAddHint} style={{ marginBottom: 10 }}>
                Word lentelėje visi šios grupės tipai bus vienoje eilutėje, atskirti kableliu.
            </p>
            <div className={`${ctrl.formRow}`} style={{ marginBottom: 10 }}>
                <InputFieldSelect
                    label="Pridėti tipą į grupę"
                    options={workerOptions}
                    selected={wLabel}
                    placeholder="Pasirinkite"
                    onChange={setWorkerId}
                    search
                />
                <button
                    type="button"
                    className={`${ctrl.button} ${ctrl.buttonPrimary}`}
                    disabled={!workerId || busy}
                    onClick={addWorker}
                >
                    Pridėti
                </button>
            </div>
            <ul className={styles.assignmentList}>
                {group.workers.map((row) => (
                    <li key={row.id} className={styles.aapCompactRow}>
                        <span className={styles.aapCompactRowText}>{row.worker.name}</span>
                        <button
                            type="button"
                            className={`${ctrl.button} ${ctrl.buttonDanger}`}
                            style={{ padding: "6px 12px", fontSize: 13 }}
                            disabled={busy}
                            onClick={() => removeWorker(row.worker.id)}
                        >
                            Pašalinti
                        </button>
                    </li>
                ))}
            </ul>

            <h4 className={ctrl.workerAddTitle} style={{ marginTop: 16 }}>
                2. Apsaugos priemonės
            </h4>
            <p className={ctrl.workerAddHint} style={{ marginBottom: 10 }}>
                Toje pačioje Word eilutėje priemonės bus sujungtos per kablelį. „Redaguoti“ keičia bendrą priemonės
                aprašą visur sistemoje.
            </p>
            <div className={`${ctrl.formRow}`} style={{ marginBottom: 10 }}>
                <InputFieldSelect
                    label="Pridėti priemonę į grupę"
                    options={equipmentOptions}
                    selected={eLabel}
                    placeholder="Pasirinkite"
                    onChange={setEquipmentId}
                    search
                />
                <div style={{ minWidth: 88 }}>
                    <label className={styles.mutedSmall} htmlFor={`grp-${group.id}-new-qty`}>
                        Kiekis (vnt./poros)
                    </label>
                    <input
                        id={`grp-${group.id}-new-qty`}
                        type="number"
                        min={DOC_QTY_MIN}
                        max={DOC_QTY_MAX}
                        value={addEquipmentQty}
                        onChange={(e) => setAddEquipmentQty(e.target.value)}
                        style={quantityInputStyle}
                    />
                </div>
                <button
                    type="button"
                    className={`${ctrl.button} ${ctrl.buttonPrimary}`}
                    disabled={!equipmentId || busy}
                    onClick={addEquipment}
                >
                    Pridėti
                </button>
            </div>
            <ul className={styles.assignmentList}>
                {group.equipment.map((row) => (
                    <li
                        key={row.id}
                        className={
                            editingEquipmentId === row.equipment.id
                                ? `${styles.aapCompactRow} ${styles.aapCompactRowEdit}`
                                : styles.aapCompactRow
                        }
                    >
                        {editingEquipmentId === row.equipment.id ? (
                            <div
                                style={{
                                    display: "flex",
                                    flexDirection: "column",
                                    gap: 10,
                                    width: "100%",
                                }}
                            >
                                <div className={ctrl.formRow}>
                                    <InputFieldText
                                        value={editName}
                                        onChange={setEditName}
                                        placeholder="Priemonės pavadinimas"
                                    />
                                    <InputFieldText
                                        value={editExpiration}
                                        onChange={setEditExpiration}
                                        placeholder="Tinkamumo periodas (pvz. 12 mėn.)"
                                    />
                                    <InputFieldSelect
                                        label="Mato vienetas"
                                        options={[...EQUIPMENT_UNIT_OPTIONS]}
                                        selected={
                                            EQUIPMENT_UNIT_OPTIONS.find((o) => o.value === editUnit)?.label ?? "Vnt"
                                        }
                                        onChange={setEditUnit}
                                    />
                                </div>
                                <div style={{ display: "flex", gap: 8, flexWrap: "wrap" }}>
                                    <button
                                        type="button"
                                        className={`${ctrl.button} ${ctrl.buttonPrimary}`}
                                        disabled={busy || !editName.trim() || !editExpiration.trim()}
                                        onClick={saveEditEquipment}
                                    >
                                        Išsaugoti
                                    </button>
                                    <button
                                        type="button"
                                        className={`${ctrl.button} ${ctrl.buttonGhost}`}
                                        disabled={busy}
                                        onClick={cancelEditEquipment}
                                    >
                                        Atšaukti
                                    </button>
                                </div>
                            </div>
                        ) : (
                            <>
                                <span className={styles.aapCompactRowText}>
                                    {row.equipment.name} ({equipmentUnitLabel(row.equipment.unitOfMeasurement)}) —{" "}
                                    {row.equipment.expirationDate}
                                </span>
                                <div className={styles.aapCompactActions}>
                                    <button
                                        type="button"
                                        className={`${ctrl.button} ${ctrl.buttonGhost}`}
                                        style={{ padding: "6px 12px", fontSize: 13 }}
                                        disabled={busy}
                                        onClick={() => beginEditEquipment(row)}
                                    >
                                        Redaguoti
                                    </button>
                                    <label className={styles.mutedSmall} style={{ margin: 0 }}>
                                        Kiekis
                                    </label>
                                    <input
                                        type="number"
                                        className={styles.aapQtyInput}
                                        min={DOC_QTY_MIN}
                                        max={DOC_QTY_MAX}
                                        defaultValue={String(row.quantity ?? 1)}
                                        key={`ge-${group.id}-${row.equipment.id}-${row.quantity ?? 1}`}
                                        disabled={busy}
                                        onBlur={(e) => patchGroupEquipmentQuantity(row.equipment.id, e.target.value)}
                                    />
                                    <button
                                        type="button"
                                        className={`${ctrl.button} ${ctrl.buttonDanger}`}
                                        style={{ padding: "6px 12px", fontSize: 13 }}
                                        disabled={busy}
                                        onClick={() => removeEquipment(row.equipment.id)}
                                    >
                                        Pašalinti
                                    </button>
                                </div>
                            </>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function WorkerEquipmentController() {
    const { equipment, setEquipment } = useEquipment();
    const [companies, setCompanies] = useState<Company[]>([]);
    const [companyId, setCompanyId] = useState<string>("");
    const [companyWorkers, setCompanyWorkers] = useState<CompanyWorker[]>([]);
    const [groups, setGroups] = useState<AapEquipmentGroupRow[]>([]);
    const [newGroupName, setNewGroupName] = useState("");
    const [loadingCompany, setLoadingCompany] = useState(false);
    const [creatingGroup, setCreatingGroup] = useState(false);
    const [selectedGroupId, setSelectedGroupId] = useState<number | null>(null);

    useEffect(() => {
        CompanyApi.getAll().then(setCompanies).catch(() => setCompanies([]));
    }, []);

    const companyOptions = useMemo(
        () =>
            companies
                .filter((c) => c.id)
                .map((c) => ({
                    value: String(c.id),
                    label: `${c.companyType ?? ""} ${c.companyName ?? ""}`.trim() || `Įmonė #${c.id}`,
                })),
        [companies],
    );

    const selectedCompanyLabel =
        companyOptions.find((o) => o.value === companyId)?.label ?? "";

    useEffect(() => {
        const id = Number(companyId);
        if (!id) {
            setCompanyWorkers([]);
            setGroups([]);
            setSelectedGroupId(null);
            return;
        }
        setLoadingCompany(true);
        Promise.all([CompanyWorkersApi.getByCompanyId(id), EquipmentApi.getAapEquipmentGroups(id)])
            .then(([cw, gr]) => {
                setCompanyWorkers(cw);
                setGroups(gr);
            })
            .catch(() => {
                setCompanyWorkers([]);
                setGroups([]);
            })
            .finally(() => setLoadingCompany(false));
    }, [companyId]);

    useEffect(() => {
        if (groups.length === 0) {
            setSelectedGroupId(null);
            return;
        }
        setSelectedGroupId((prev) =>
            prev != null && groups.some((g) => g.id === prev) ? prev : groups[0].id,
        );
    }, [groups]);

    const selectedGroup = useMemo(
        () => (selectedGroupId == null ? null : groups.find((g) => g.id === selectedGroupId) ?? null),
        [groups, selectedGroupId],
    );

    const workerOptions = useMemo(
        () =>
            companyWorkers
                .map((cw) => cw.worker)
                .filter((w): w is NonNullable<typeof w> => w != null && Boolean(w.id))
                .map((w) => ({ value: String(w.id), label: w.name })),
        [companyWorkers],
    );

    const equipmentOptions = useMemo(
        () =>
            equipment.map((e) => ({
                value: String(e.id),
                label: `${e.name} (${equipmentUnitLabel(e.unitOfMeasurement)})`,
            })),
        [equipment],
    );

    async function createGroup() {
        const c = Number(companyId);
        const name = newGroupName.trim();
        if (!c || !name) return;
        setCreatingGroup(true);
        try {
            const g = await EquipmentApi.createAapEquipmentGroup({ companyId: c, name });
            setGroups((prev) => [...prev, g].sort((a, b) => a.sortOrder - b.sortOrder || a.id - b.id));
            setSelectedGroupId(g.id);
            setNewGroupName("");
        } finally {
            setCreatingGroup(false);
        }
    }

    const replaceGroup = useCallback((g: AapEquipmentGroupRow) => {
        setGroups((prev) => prev.map((x) => (x.id === g.id ? g : x)));
    }, []);

    const onEquipmentUpdated = useCallback(
        async (updated: Equipment) => {
            setEquipment((prev) => prev.map((x) => (x.id === updated.id ? updated : x)));
            const id = Number(companyId);
            if (!id) return;
            const gr = await EquipmentApi.getAapEquipmentGroups(id);
            setGroups(gr);
        },
        [companyId, setEquipment],
    );

    async function removeGroup(id: number) {
        await EquipmentApi.deleteAapEquipmentGroup(id);
        setGroups((prev) => prev.filter((g) => g.id !== id));
    }

    return (
        <div className={`${ctrl.workerLayout}`}>
            <aside className={ctrl.workerMenu} aria-label="Įmonė ir grupių sąrašas">
                <h3 className={ctrl.title} style={{ fontSize: 18 }}>
                    Įmonė ir grupės
                </h3>
                <InputFieldSelect
                    label="Įmonė"
                    options={companyOptions}
                    selected={selectedCompanyLabel}
                    placeholder="Pasirinkite įmonę"
                    onChange={setCompanyId}
                    search
                />

                {!companyId ? (
                    <p className={ctrl.subtitle}>1 žingsnis: pasirinkite įmonę, kuriai sudarinėjate AAP grupes.</p>
                ) : loadingCompany ? (
                    <p className={ctrl.subtitle}>Kraunama…</p>
                ) : companyWorkers.length === 0 ? (
                    <p className={ctrl.subtitle}>
                        Šiai įmonei nepriskirti darbuotojų tipai. Juos priskirkite įmonės kortelėje arba rizikų modulyje.
                    </p>
                ) : (
                    <>
                        <p className={ctrl.subtitle}>
                            <strong>Kaip veikia:</strong> kiekviena grupė = viena eilutė AAP Word lentelėje (visi pasirinkti
                            tipai ir priemonės sujungti toje eilutėje).
                        </p>
                        <ol
                            className={ctrl.subtitle}
                            style={{ margin: "0 0 12px", paddingLeft: "1.15rem", lineHeight: 1.5 }}
                        >
                            <li>Pasirinkite grupę sąraše arba sukurkite naują.</li>
                            <li>Dešinėje pridėkite darbuotojų tipus, tada apsaugos priemones.</li>
                        </ol>

                        <h4 className={ctrl.workerAddTitle}>Grupių sąrašas</h4>
                        <div className={ctrl.workerList}>
                            {groups.length === 0 ? (
                                <p className={ctrl.subtitle}>Grupių nėra — sukurkite žemiau.</p>
                            ) : (
                                groups.map((g) => (
                                    <button
                                        key={g.id}
                                        type="button"
                                        className={`${ctrl.workerListButton} ${selectedGroupId === g.id ? ctrl.workerListButtonActive : ""}`}
                                        onClick={() => setSelectedGroupId(g.id)}
                                    >
                                        {g.name}
                                    </button>
                                ))
                            )}
                        </div>

                        <div style={{ marginTop: 12, paddingTop: 12, borderTop: "1px solid #e2e8f0" }}>
                            <h4 className={ctrl.workerAddTitle}>Nauja grupė</h4>
                            <div className={ctrl.formRow}>
                                <InputFieldText
                                    value={newGroupName}
                                    onChange={setNewGroupName}
                                    placeholder="Naujos grupės pavadinimas (pvz. Gamyba)"
                                />
                                <button
                                    type="button"
                                    className={`${ctrl.button} ${ctrl.buttonPrimary}`}
                                    disabled={!newGroupName.trim() || creatingGroup}
                                    onClick={createGroup}
                                >
                                    {creatingGroup ? "Kuriama…" : "Sukurti grupę"}
                                </button>
                            </div>
                        </div>
                    </>
                )}
            </aside>

            <section className={ctrl.controller}>
                <h3 className={ctrl.title}>Grupės turinys</h3>
                {!companyId ? (
                    <p className={ctrl.subtitle}>Pasirinkite įmonę kairėje, tada grupę.</p>
                ) : loadingCompany ? (
                    <p className={ctrl.subtitle}>Kraunama…</p>
                ) : companyWorkers.length === 0 ? (
                    <p className={ctrl.subtitle}>Be darbuotojų tipų grupių kurti negalima.</p>
                ) : groups.length === 0 ? (
                    <div className={ctrl.panel}>
                        <p className={ctrl.subtitle}>
                            Dar nėra grupių. Kairėje įveskite pavadinimą ir spauskite <strong>Sukurti grupę</strong>, tada
                            čia pridėsite tipus ir priemones.
                        </p>
                    </div>
                ) : selectedGroup ? (
                    <AapGroupEditor
                        group={selectedGroup}
                        workerOptions={workerOptions}
                        equipmentOptions={equipmentOptions}
                        onReplaceGroup={replaceGroup}
                        onRemoveGroup={removeGroup}
                        onEquipmentUpdated={onEquipmentUpdated}
                    />
                ) : (
                    <p className={ctrl.subtitle}>Pasirinkite grupę kairėje.</p>
                )}
            </section>
        </div>
    );
}
