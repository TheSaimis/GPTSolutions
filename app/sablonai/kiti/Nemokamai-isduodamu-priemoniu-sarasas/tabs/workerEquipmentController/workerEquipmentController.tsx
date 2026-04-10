"use client";

import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { EquipmentApi } from "@/lib/api/equipment";
import { CompanyWorkersApi } from "@/lib/api/companyWorkers";
import { CompanyApi } from "@/lib/api/companies";
import { useCallback, useEffect, useMemo, useState } from "react";
import { useEquipment } from "../../equipmentContext";
import { equipmentUnitLabel } from "../equipmentController/equipmentUnits";
import styles from "../../page.module.scss";
import type { Company, CompanyWorker } from "@/lib/types/Company";
import type { CompanyWorkerEquipmentRow } from "@/lib/types/companyWorkerEquipment";
import type { AapEquipmentGroupRow } from "@/lib/types/aapEquipmentGroup";

type GroupEditorProps = {
    group: AapEquipmentGroupRow;
    workerOptions: { value: string; label: string }[];
    equipmentOptions: { value: string; label: string }[];
    onReplaceGroup: (g: AapEquipmentGroupRow) => void;
    onRemoveGroup: (id: number) => void;
};

function AapGroupEditor({
    group,
    workerOptions,
    equipmentOptions,
    onReplaceGroup,
    onRemoveGroup,
}: GroupEditorProps) {
    const [workerId, setWorkerId] = useState("");
    const [equipmentId, setEquipmentId] = useState("");
    const [busy, setBusy] = useState(false);

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
        setBusy(true);
        try {
            const next = await EquipmentApi.addEquipmentToAapEquipmentGroup(group.id, e);
            onReplaceGroup(next);
            setEquipmentId("");
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

    return (
        <div className={styles.assignmentGroup}>
            <div style={{ display: "flex", flexWrap: "wrap", justifyContent: "space-between", gap: 8, alignItems: "center" }}>
                <p className={styles.assignmentGroupTitle}>{group.name}</p>
                <button
                    type="button"
                    className={`${styles.button} ${styles.buttonDanger} ${styles.buttonCompact}`}
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
            <p className={styles.mutedSmall} style={{ margin: "0 0 8px" }}>
                Darbuotojai (vienoje lentelės eilutėje bus sujungti per kablelį)
            </p>
            <div className={styles.row} style={{ marginBottom: 8 }}>
                <InputFieldSelect
                    label="Pridėti tipą į grupę"
                    options={workerOptions}
                    selected={wLabel}
                    placeholder="Pasirinkite"
                    onChange={setWorkerId}
                    search
                />
                <button type="button" className={styles.button} disabled={!workerId || busy} onClick={addWorker}>
                    Pridėti
                </button>
            </div>
            <ul className={styles.assignmentList}>
                {group.workers.map((row) => (
                    <li key={row.id} className={styles.assignmentListItem}>
                        <span>{row.worker.name}</span>
                        <button
                            type="button"
                            className={`${styles.button} ${styles.buttonDanger} ${styles.buttonCompact}`}
                            disabled={busy}
                            onClick={() => removeWorker(row.worker.id)}
                        >
                            Pašalinti
                        </button>
                    </li>
                ))}
            </ul>
            <p className={styles.mutedSmall} style={{ margin: "10px 0 8px" }}>
                Priemonės (tos pačios eilutės stulpelyje — sujungtos per kablelį)
            </p>
            <div className={styles.row} style={{ marginBottom: 8 }}>
                <InputFieldSelect
                    label="Pridėti priemonę į grupę"
                    options={equipmentOptions}
                    selected={eLabel}
                    placeholder="Pasirinkite"
                    onChange={setEquipmentId}
                    search
                />
                <button type="button" className={styles.button} disabled={!equipmentId || busy} onClick={addEquipment}>
                    Pridėti
                </button>
            </div>
            <ul className={styles.assignmentList}>
                {group.equipment.map((row) => (
                    <li key={row.id} className={styles.assignmentListItem}>
                        <span>
                            {row.equipment.name} ({equipmentUnitLabel(row.equipment.unitOfMeasurement)})
                        </span>
                        <button
                            type="button"
                            className={`${styles.button} ${styles.buttonDanger} ${styles.buttonCompact}`}
                            disabled={busy}
                            onClick={() => removeEquipment(row.equipment.id)}
                        >
                            Pašalinti
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function WorkerEquipmentController() {
    const { equipment } = useEquipment();
    const [companies, setCompanies] = useState<Company[]>([]);
    const [companyId, setCompanyId] = useState<string>("");
    const [companyWorkers, setCompanyWorkers] = useState<CompanyWorker[]>([]);
    const [assignments, setAssignments] = useState<CompanyWorkerEquipmentRow[]>([]);
    const [groups, setGroups] = useState<AapEquipmentGroupRow[]>([]);
    const [newGroupName, setNewGroupName] = useState("");
    const [workerId, setWorkerId] = useState<string>("");
    const [equipmentId, setEquipmentId] = useState<string>("");
    const [assigning, setAssigning] = useState(false);
    const [loadingCompany, setLoadingCompany] = useState(false);
    const [creatingGroup, setCreatingGroup] = useState(false);

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
            setAssignments([]);
            setGroups([]);
            setWorkerId("");
            return;
        }
        setLoadingCompany(true);
        Promise.all([
            CompanyWorkersApi.getByCompanyId(id),
            EquipmentApi.getCompanyWorkerEquipment(id),
            EquipmentApi.getAapEquipmentGroups(id),
        ])
            .then(([cw, rows, gr]) => {
                setCompanyWorkers(cw);
                setAssignments(rows);
                setGroups(gr);
            })
            .catch(() => {
                setCompanyWorkers([]);
                setAssignments([]);
                setGroups([]);
            })
            .finally(() => setLoadingCompany(false));
    }, [companyId]);

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

    const selectedWorkerLabel =
        workerOptions.find((o) => o.value === workerId)?.label ?? "";
    const selectedEquipmentLabel =
        equipmentOptions.find((o) => o.value === equipmentId)?.label ?? "";

    async function assign() {
        const c = Number(companyId);
        const w = Number(workerId);
        const e = Number(equipmentId);
        if (!c || !w || !e) return;
        setAssigning(true);
        try {
            const created = await EquipmentApi.createCompanyWorkerEquipment({
                companyId: c,
                workerId: w,
                equipmentId: e,
            });
            setAssignments((prev) => {
                if (prev.some((x) => x.id === created.id)) return prev;
                return [...prev, created].sort((a, b) => {
                    const wa = a.worker?.name ?? "";
                    const wb = b.worker?.name ?? "";
                    if (wa !== wb) return wa.localeCompare(wb, "lt");
                    return (a.equipment?.name ?? "").localeCompare(b.equipment?.name ?? "", "lt");
                });
            });
            setEquipmentId("");
        } finally {
            setAssigning(false);
        }
    }

    async function removeRow(id: number) {
        await EquipmentApi.deleteCompanyWorkerEquipment(id);
        setAssignments((prev) => prev.filter((x) => x.id !== id));
    }

    async function createGroup() {
        const c = Number(companyId);
        const name = newGroupName.trim();
        if (!c || !name) return;
        setCreatingGroup(true);
        try {
            const g = await EquipmentApi.createAapEquipmentGroup({ companyId: c, name });
            setGroups((prev) => [...prev, g].sort((a, b) => a.sortOrder - b.sortOrder || a.id - b.id));
            setNewGroupName("");
        } finally {
            setCreatingGroup(false);
        }
    }

    const replaceGroup = useCallback((g: AapEquipmentGroupRow) => {
        setGroups((prev) => prev.map((x) => (x.id === g.id ? g : x)));
    }, []);

    async function removeGroup(id: number) {
        await EquipmentApi.deleteAapEquipmentGroup(id);
        setGroups((prev) => prev.filter((g) => g.id !== id));
    }

    const assignmentsByWorker = useMemo(() => {
        const m = new Map<number, { name: string; rows: CompanyWorkerEquipmentRow[] }>();
        for (const cw of companyWorkers) {
            const w = cw.worker;
            if (w?.id == null) continue;
            m.set(w.id, { name: w.name, rows: [] });
        }
        for (const row of assignments) {
            const wid = row.worker?.id;
            if (wid == null) continue;
            const bucket = m.get(wid);
            if (bucket) {
                bucket.rows.push(row);
            } else {
                m.set(wid, { name: row.worker?.name ?? "—", rows: [row] });
            }
        }
        return Array.from(m.entries()).sort((a, b) =>
            a[1].name.localeCompare(b[1].name, "lt"),
        );
    }, [assignments, companyWorkers]);

    return (
        <div className={styles.card}>
            <p className={styles.helpNote}>
                <strong>Grupės (rekomenduojama):</strong> jei įmonei sukūrėte bent vieną grupę ir joje priskyrėte
                darbuotojų tipus bei priemones, AAP Word dokumentuose lentelėje bus <strong>viena eilutė vienai grupei</strong>{" "}
                (visi tos grupės darbuotojai ir priemonės toje pačioje eilutėje). Jei grupių nėra, naudojama senesnė
                logika: eilutės pagal kiekvieną darbuotojo tipo ir priemonės porą arba įmonės{" "}
                <code>company_worker_equipment</code> / bendrą šabloną.
            </p>
            <p className={styles.helpNote}>
                Pasirinkite įmonę ir jos darbuotojų tipą (pareigybę). Čia nustatote, kokios apsaugos priemonės taikomos{" "}
                <strong>tik šiai įmonei</strong> (be grupių). Jei įmonei ir tipui jau yra bent viena eilutė šiame sąraše
                ir <strong>grupių nėra</strong>, AAP dokumentuose naudojamas <strong>tik šis sąrašas</strong> (ne bendras
                „šablonas“ iš senojo priskyrimo).
            </p>
            <div className={styles.row}>
                <InputFieldSelect
                    label="Įmonė"
                    options={companyOptions}
                    selected={selectedCompanyLabel}
                    placeholder="Pasirinkite įmonę"
                    onChange={setCompanyId}
                    search
                />
            </div>

            {!companyId ? (
                <p className={styles.muted}>Pirmiausia pasirinkite įmonę.</p>
            ) : loadingCompany ? (
                <p className={styles.muted}>Kraunama…</p>
            ) : companyWorkers.length === 0 ? (
                <p className={styles.muted}>
                    Šiai įmonei nepriskirti darbuotojų tipai. Juos galite priskirti įmonės kortelėje arba rizikų modulyje.
                </p>
            ) : (
                <>
                    <h3 className={styles.subheading}>Grupės (viena Word lentelės eilutė per grupę)</h3>
                    <div className={styles.row} style={{ alignItems: "end" }}>
                        <div style={{ flex: 1, minWidth: 200 }}>
                            <label className={styles.mutedSmall} htmlFor="new-aap-group-name">
                                Naujos grupės pavadinimas
                            </label>
                            <input
                                id="new-aap-group-name"
                                type="text"
                                value={newGroupName}
                                onChange={(e) => setNewGroupName(e.target.value)}
                                placeholder="Pvz. Gamyba, Biuras…"
                                style={{
                                    width: "100%",
                                    marginTop: 4,
                                    padding: "10px 12px",
                                    borderRadius: 10,
                                    border: "1px solid #e2e8f0",
                                }}
                            />
                        </div>
                        <button
                            type="button"
                            className={styles.button}
                            disabled={!newGroupName.trim() || creatingGroup}
                            onClick={createGroup}
                        >
                            {creatingGroup ? "Kuriama…" : "Sukurti grupę"}
                        </button>
                    </div>
                    {groups.length === 0 ? (
                        <p className={styles.mutedSmall} style={{ marginTop: 8 }}>
                            Grupių nėra — dokumentai generuojami pagal žemiau esančią priskyrimų logiką.
                        </p>
                    ) : (
                        <div className={styles.assignmentGroups} style={{ marginTop: 12 }}>
                            {groups.map((g) => (
                                <AapGroupEditor
                                    key={g.id}
                                    group={g}
                                    workerOptions={workerOptions}
                                    equipmentOptions={equipmentOptions}
                                    onReplaceGroup={replaceGroup}
                                    onRemoveGroup={removeGroup}
                                />
                            ))}
                        </div>
                    )}

                    <h3 className={styles.subheading}>Priskyrimas be grupės (senoji logika)</h3>
                    <div className={styles.row}>
                        <InputFieldSelect
                            label="Darbuotojų tipas (pareigybė)"
                            options={workerOptions}
                            selected={selectedWorkerLabel}
                            placeholder="Pasirinkite tipą"
                            onChange={setWorkerId}
                            search
                        />
                        <InputFieldSelect
                            label="Apsaugos priemonė"
                            options={equipmentOptions}
                            selected={selectedEquipmentLabel}
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

                    <h3 className={styles.subheading}>Priskirtos priemonės pagal tipą</h3>
                    <div className={styles.assignmentGroups}>
                        {assignmentsByWorker.map(([wid, { name, rows }]) => (
                            <div key={wid} className={styles.assignmentGroup}>
                                <p className={styles.assignmentGroupTitle}>{name}</p>
                                {rows.length === 0 ? (
                                    <p className={styles.mutedSmall}>
                                        Nėra įmonės įrašų — dokumentuose naudojamas bendras šablonas (jei
                                        sukonfigūruotas).
                                    </p>
                                ) : (
                                    <ul className={styles.assignmentList}>
                                        {rows.map((row) => (
                                            <li key={row.id} className={styles.assignmentListItem}>
                                                <span>
                                                    {row.equipment?.name ?? "—"} (
                                                    {equipmentUnitLabel(row.equipment?.unitOfMeasurement)})
                                                </span>
                                                <button
                                                    type="button"
                                                    className={`${styles.button} ${styles.buttonDanger} ${styles.buttonCompact}`}
                                                    onClick={() => removeRow(row.id)}
                                                >
                                                    Pašalinti
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
