"use client";

import { use, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { Building2, Save, Trash } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import { CompanyApi } from "@/lib/api/companies";
import { MessageStore } from "@/lib/globalVariables/messages";
import { COMPANY_TYPES } from "@/lib/types/Company";
import Link from "next/link";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { useConfirmAction } from "@/components/confirmationPanel/confirmationPanel";
import { WorkersApi } from "@/lib/api/workers";
import { CompanyWorkersApi } from "@/lib/api/companyWorkers";
import type { CompanyWorker } from "@/lib/types/Company";
import type { Worker } from "@/lib/types/Worker";
import CompanyFormLocaleToggle, { type CompanyFormLocale } from "@/components/companyForm/CompanyFormLocaleToggle";

type PageParams = Promise<{ id: string }>;

export default function ImonesRedagavimasPage({ params }: { params: PageParams }) {
    const { id: idParam } = use(params);
    const router = useRouter();
    const id = typeof idParam === "string" ? parseInt(idParam, 10) : NaN;

    const [loading, setLoading] = useState(!Number.isNaN(id));
    const [companyType, setCompanyType] = useState("");
    const [companyName, setCompanyName] = useState("");
    const [address, setAddress] = useState("");
    const [code, setCode] = useState("");
    const [cityOrDistrict, setCityOrDistrict] = useState("");
    const [managerType, setManagerType] = useState("");
    const [managerFirstName, setManagerFirstName] = useState("");
    const [managerLastName, setManagerLastName] = useState("");
    const [managerGender, setManagerGender] = useState("");
    const [role, setRole] = useState("");
    const [deleted, setDeleted] = useState<boolean | undefined>(false);
    const [deletedDate, setDeletedDate] = useState("");
    const [readOnly, setReadOnly] = useState<{ createdAt?: string; modifiedAt?: string; documentDate?: string }>({});
    const [workers, setWorkers] = useState<Worker[]>([]);
    const [companyWorkers, setCompanyWorkers] = useState<CompanyWorker[]>([]);
    const [selectedWorkerIdToAdd, setSelectedWorkerIdToAdd] = useState<number | null>(null);
    const { confirmAction } = useConfirmAction();
    const [formLocale, setFormLocale] = useState<CompanyFormLocale>("lt");
    const [managerFirstNameEn, setManagerFirstNameEn] = useState("");
    const [managerFirstNameRu, setManagerFirstNameRu] = useState("");
    const [roleEn, setRoleEn] = useState("");
    const [roleRu, setRoleRu] = useState("");

    useEffect(() => {
        if (Number.isNaN(id)) {
            return;
        }
        document.title = "Redaguoti įmonę";
        CompanyApi.getById(id)
            .then((c) => {
                if (c) {
                    setCompanyType(c.companyType ?? "");
                    setCompanyName(c.companyName ?? "");
                    setAddress(c.address ?? "");
                    setCode(c.code ?? "");
                    setCityOrDistrict(c.cityOrDistrict ?? "");
                    setManagerType(c.managerType ?? "");
                    setManagerFirstName(c.managerFirstName ?? "");
                    setManagerLastName(c.managerLastName ?? "");
                    setManagerGender(c.managerGender ?? "");
                    setDeleted(c.deleted);
                    setDeletedDate(c.deletedDate ?? "");
                    setRole(c.role ?? "");
                    setManagerFirstNameEn(c.managerFirstNameEn ?? "");
                    setManagerFirstNameRu(c.managerFirstNameRu ?? "");
                    setRoleEn(c.roleEn ?? "");
                    setRoleRu(c.roleRu ?? "");
                    setReadOnly({
                        createdAt: c.createdAt,
                        modifiedAt: c.modifiedAt,
                        documentDate: c.documentDate,
                    });
                }
            })
            .finally(() => setLoading(false));

        WorkersApi.getAll().then(setWorkers).catch(() => undefined);
        CompanyWorkersApi.getByCompanyId(id).then(setCompanyWorkers).catch(() => undefined);
    }, [id]);

    function handleFormLocaleChange(next: CompanyFormLocale) {
        if (next === "en") {
            setManagerFirstNameEn((p) => (p.trim() ? p : managerFirstName));
            setRoleEn((p) => (p.trim() ? p : role));
        } else if (next === "ru") {
            setManagerFirstNameRu((p) => (p.trim() ? p : managerFirstName));
            setRoleRu((p) => (p.trim() ? p : role));
        }
        setFormLocale(next);
    }

    async function addWorkerToCompany() {
        if (Number.isNaN(id) || !selectedWorkerIdToAdd) return;
        const exists = companyWorkers.some((item) => item.worker?.id === selectedWorkerIdToAdd);
        if (exists) return;
        const created = await CompanyWorkersApi.create({
            companyId: id,
            workerId: selectedWorkerIdToAdd,
        });
        setCompanyWorkers((prev) => [...prev, created]);
        setSelectedWorkerIdToAdd(null);
    }

    async function removeWorkerFromCompany(companyWorkerId: number) {
        await CompanyWorkersApi.delete(companyWorkerId);
        setCompanyWorkers((prev) => prev.filter((item) => item.id !== companyWorkerId));
    }

    async function deleteCompany() {
        if (Number.isNaN(id)) return;
        try {
            const confirmed = await confirmAction({
                title: "Ištrinti naudotoją?",
                message: "Atliekus ši veiksmą įmonė išliks duomenų bazėje 7 dienas.\n Praėjus šiam laikotarpiui imonė bus ištrinta visam laikui",
                type: "delete",
                confirmText: "Ištrinti",
                cancelText: "Atšaukti",
                icon: Trash,
            });
            if (!confirmed) return;

            await CompanyApi.companyDelete(id);
            MessageStore.push({ title: "Sėkmingai", message: "Įmonė ištrinta", backgroundColor: "#22C55E" });
            setDeleted(true);
            setDeletedDate(new Date().toISOString());
        } catch {
            // error handled by api
        }
    }

    async function restoreCompany() {
        if (Number.isNaN(id)) return;
        try {
            await CompanyApi.companyRestore(id);
            MessageStore.push({ title: "Sėkmingai", message: "Įmonė atkurta", backgroundColor: "#22C55E" });
            setDeleted(false);
            setDeletedDate("");
        } catch {
            // error handled by api
        }
    }

    async function handleSubmit() {
        if (Number.isNaN(id)) return;
        let payload: Parameters<typeof CompanyApi.companyUpdate>[1];
        if (formLocale === "lt") {
            payload = {
                companyType,
                companyName,
                address,
                code,
                cityOrDistrict,
                managerType,
                managerFirstName,
                managerLastName,
                managerGender,
                role,
            };
        } else if (formLocale === "en") {
            payload = {
                companyName,
                address,
                cityOrDistrict,
                managerFirstNameEn,
                managerLastName,
                roleEn,
            };
        } else {
            payload = {
                companyName,
                address,
                cityOrDistrict,
                managerFirstNameRu,
                managerLastName,
                roleRu,
            };
        }
        const res = await CompanyApi.companyUpdate(id, payload);
        if (res?.status === "SUCCESS") {
            MessageStore.push({ title: "Sėkmingai", message: "Įmonė atnaujinta", backgroundColor: "#22C55E" });
            router.back();
        }
    }

    if (loading) {
        return <p className={styles.message}>Kraunama...</p>;
    }
    if (Number.isNaN(id)) {
        return (
            <div className={styles.page}>
                <p className={styles.message}>Neteisingas įmonės ID.</p>
                <Link href="/imones/sarasas" className={styles.backLink}>Grįžti į sąrašą</Link>
            </div>
        );
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}>
                        <Building2 size={24} />
                    </div>
                    <div>
                        <h1 className={styles.title}>Redaguoti įmonę</h1>
                        <p className={styles.subtitle}>Keiskite įmonės duomenis (ID, sukūrimo ir redagavimo datos nekeičiamos)</p>
                    </div>
                    {!deleted &&
                        <div className={styles.trashIcon} onClick={deleteCompany}>
                            <Trash size={24} />
                        </div>
                    }
                </div>

                <div className={styles.divider} />

                {deleted && (
                    <>
                        <div className={styles.deletedSection}>
                            <div className={styles.deletedRow}>
                                <p className={styles.deletedLabel}>Ši įmonė yra pažymėta ištrinimui</p>
                                <p className={styles.deletedValue}>Ištrinimo data: {deletedDate}</p>
                                <p>Praėjus 7 dienom nuo ištrinimo datos vartotojas bus pašalintas iš duomenų bazės visam laikui</p>
                            </div>
                            <button onClick={restoreCompany} className="buttons">Atstatyti įmonę</button>
                        </div>
                        <div className={styles.divider} />
                    </>
                )
                }

                <div className={styles.readOnlySection}>
                    <div className={styles.readOnlyRow}>
                        <span className={styles.readOnlyLabel}>ID</span>
                        <span className={styles.readOnlyValue}>{id}</span>
                    </div>
                    {readOnly.createdAt != null && readOnly.createdAt !== "" && (
                        <div className={styles.readOnlyRow}>
                            <span className={styles.readOnlyLabel}>Sukurta</span>
                            <span className={styles.readOnlyValue}>{readOnly.createdAt}</span>
                        </div>
                    )}
                    {readOnly.modifiedAt != null && readOnly.modifiedAt !== "" && (
                        <div className={styles.readOnlyRow}>
                            <span className={styles.readOnlyLabel}>Redaguota</span>
                            <span className={styles.readOnlyValue}>{readOnly.modifiedAt}</span>
                        </div>
                    )}
                    {readOnly.documentDate != null && readOnly.documentDate !== "" && (
                        <div className={styles.readOnlyRow}>
                            <span className={styles.readOnlyLabel}>Dokumento data</span>
                            <span className={styles.readOnlyValue}>{readOnly.documentDate}</span>
                        </div>
                    )}
                </div>

                <div className={styles.divider} />

                <CompanyFormLocaleToggle value={formLocale} onChange={handleFormLocaleChange} />

                {formLocale === "lt" ? (
                    <div className={styles.form}>
                        <div className={styles.row}>
                            <InputFieldSelect options={[...COMPANY_TYPES]} selected={companyType} onChange={setCompanyType} placeholder="Įmonės tipas" />
                            <InputFieldText value={companyName} onChange={setCompanyName} placeholder="Įmonės pavadinimas" />
                        </div>

                        <InputFieldText value={address} onChange={setAddress} placeholder="Adresas" />
                        <InputFieldNumber regex={/^\d{0,9}$/} value={code} onChange={setCode} placeholder="Įmonės kodas" />
                        <InputFieldText value={cityOrDistrict} onChange={setCityOrDistrict} placeholder="Miestas / rajonas" />
                        <InputFieldText value={managerType} onChange={setManagerType} placeholder="Vadovo tipas" />

                        <InputFieldSelect options={["Vyras", "Moteris"]} selected={managerGender} onChange={setManagerGender} placeholder="Vadovo lytis" />

                        <div className={styles.row}>
                            <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={managerFirstName} onChange={setManagerFirstName} placeholder="Vadovo vardas" />
                            <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={managerLastName} onChange={setManagerLastName} placeholder="Vadovo pavardė" />
                        </div>

                        <InputFieldText value={role} onChange={setRole} placeholder="Pareigos" />
                    </div>
                ) : formLocale === "en" ? (
                    <div className={styles.form}>
                        <InputFieldText value={companyName} onChange={setCompanyName} placeholder="Įmones pavadinimas" />
                        <InputFieldText value={address} onChange={setAddress} placeholder="Adresas" />
                        <InputFieldText value={cityOrDistrict} onChange={setCityOrDistrict} placeholder="Miestas/Rajonas" />
                        <div className={styles.row}>
                            <InputFieldText value={managerFirstNameEn} onChange={setManagerFirstNameEn} placeholder="Vardas" />
                            <InputFieldText value={managerLastName} onChange={setManagerLastName} placeholder="Pavardė" />
                        </div>
                        <InputFieldText value={roleEn} onChange={setRoleEn} placeholder="Pareigos" />
                    </div>
                ) : (
                    <div className={styles.form}>
                        <InputFieldText value={companyName} onChange={setCompanyName} placeholder="Įmones pavadinimas" />
                        <InputFieldText value={address} onChange={setAddress} placeholder="Adresas" />
                        <InputFieldText value={cityOrDistrict} onChange={setCityOrDistrict} placeholder="Miestas/Rajonas" />
                        <div className={styles.row}>
                            <InputFieldText value={managerFirstNameRu} onChange={setManagerFirstNameRu} placeholder="Vardas" />
                            <InputFieldText value={managerLastName} onChange={setManagerLastName} placeholder="Pavardė" />
                        </div>
                        <InputFieldText value={roleRu} onChange={setRoleRu} placeholder="Pareigos" />
                    </div>
                )}

                {formLocale === "lt" && (
                <div className={styles.workerPanel}>
                    <h3 className={styles.workerPanelTitle}>Darbuotojų tipai įmonei</h3>
                    <div className={styles.workerAssignRow}>
                        <div className={styles.workerSelect}>
                            <InputFieldSelect
                                options={workers.map((worker) => ({
                                    value: String(worker.id),
                                    label: worker.name,
                                }))}
                                selected={
                                    selectedWorkerIdToAdd !== null
                                        ? workers.find((worker) => worker.id === selectedWorkerIdToAdd)?.name ?? ""
                                        : ""
                                }
                                placeholder="Pasirinkite darbuotojo tipą"
                                onChange={(value) => setSelectedWorkerIdToAdd(Number(value))}
                            />
                        </div>
                        <button
                            type="button"
                            className={styles.addWorkerButton}
                            onClick={addWorkerToCompany}
                            disabled={selectedWorkerIdToAdd === null}
                        >
                            Pridėti
                        </button>
                    </div>

                    <div className={styles.workerTypeList}>
                        {companyWorkers.length === 0 ? (
                            <p className={styles.workerPanelMuted}>Šiai įmonei dar nepriskirtas nei vienas darbuotojo tipas.</p>
                        ) : (
                            companyWorkers.map((item) => (
                                <div key={item.id} className={styles.workerTypeItem}>
                                    <span>{item.worker?.name ?? "Nežinomas tipas"}</span>
                                    <button
                                        type="button"
                                        className={styles.removeWorkerButton}
                                        onClick={() => removeWorkerFromCompany(item.id)}
                                    >
                                        Šalinti
                                    </button>
                                </div>
                            ))
                        )}
                    </div>
                </div>
                )}

                <button className={styles.submitButton} onClick={handleSubmit}>
                    <Save size={18} />
                    Išsaugoti
                </button>
            </div>
        </div>
    );
}