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
    const [companyNameEn, setCompanyNameEn] = useState("");
    const [companyNameRu, setCompanyNameRu] = useState("");
    const [address, setAddress] = useState("");
    const [addressEn, setAddressEn] = useState("");
    const [addressRu, setAddressRu] = useState("");
    const [code, setCode] = useState("");
    const [cityOrDistrict, setCityOrDistrict] = useState("");
    const [cityOrDistrictEn, setCityOrDistrictEn] = useState("");
    const [cityOrDistrictRu, setCityOrDistrictRu] = useState("");
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
    const [managerLastNameEn, setManagerLastNameEn] = useState("");
    const [managerLastNameRu, setManagerLastNameRu] = useState("");

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
                    setCompanyNameEn(c.companyNameEn ?? "");
                    setCompanyNameRu(c.companyNameRu ?? "");
                    setAddress(c.address ?? "");
                    setAddressEn(c.addressEn ?? "");
                    setAddressRu(c.addressRu ?? "");
                    setCode(c.code ?? "");
                    setCityOrDistrict(c.cityOrDistrict ?? "");
                    setCityOrDistrictEn(c.cityOrDistrictEn ?? "");
                    setCityOrDistrictRu(c.cityOrDistrictRu ?? "");
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
                    setManagerLastNameEn(c.managerLastNameEn ?? "");
                    setManagerLastNameRu(c.managerLastNameRu ?? "");
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
            setManagerLastNameEn((p) => (p.trim() ? p : managerLastName));
            setRoleEn((p) => (p.trim() ? p : role));
        } else if (next === "ru") {
            setManagerFirstNameRu((p) => (p.trim() ? p : managerFirstName));
            setManagerLastNameRu((p) => (p.trim() ? p : managerLastName));
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

        const basePayload = {
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
        } as const;

        const optionalLocaleFields: Partial<Record<
            | "companyNameEn"
            | "companyNameRu"
            | "addressEn"
            | "addressRu"
            | "cityOrDistrictEn"
            | "cityOrDistrictRu"
            | "managerFirstNameEn"
            | "managerFirstNameRu"
            | "managerLastNameEn"
            | "managerLastNameRu"
            | "roleEn"
            | "roleRu",
            string
        >> = {};

        if (companyNameEn.trim()) optionalLocaleFields.companyNameEn = companyNameEn.trim();
        if (companyNameRu.trim()) optionalLocaleFields.companyNameRu = companyNameRu.trim();
        if (addressEn.trim()) optionalLocaleFields.addressEn = addressEn.trim();
        if (addressRu.trim()) optionalLocaleFields.addressRu = addressRu.trim();
        if (cityOrDistrictEn.trim()) optionalLocaleFields.cityOrDistrictEn = cityOrDistrictEn.trim();
        if (cityOrDistrictRu.trim()) optionalLocaleFields.cityOrDistrictRu = cityOrDistrictRu.trim();
        if (managerFirstNameEn.trim()) optionalLocaleFields.managerFirstNameEn = managerFirstNameEn.trim();
        if (managerFirstNameRu.trim()) optionalLocaleFields.managerFirstNameRu = managerFirstNameRu.trim();
        if (managerLastNameEn.trim()) optionalLocaleFields.managerLastNameEn = managerLastNameEn.trim();
        if (managerLastNameRu.trim()) optionalLocaleFields.managerLastNameRu = managerLastNameRu.trim();
        if (roleEn.trim()) optionalLocaleFields.roleEn = roleEn.trim();
        if (roleRu.trim()) optionalLocaleFields.roleRu = roleRu.trim();

        const payload: Parameters<typeof CompanyApi.companyUpdate>[1] = {
            ...basePayload,
            ...optionalLocaleFields,
        };

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
                            <InputFieldSelect label="Įmonės tipas" options={[...COMPANY_TYPES]} selected={companyType} onChange={setCompanyType} placeholder="Įmonės tipas" />
                            <InputFieldText value={companyName} onChange={setCompanyName} placeholder="Įmonės pavadinimas" />
                        </div>

                        <InputFieldText value={address} onChange={setAddress} placeholder="Adresas" />
                        <InputFieldNumber regex={/^\d{0,9}$/} value={code} onChange={setCode} placeholder="Įmonės kodas" />
                        <InputFieldText value={cityOrDistrict} onChange={setCityOrDistrict} placeholder="Miestas / rajonas" />
                        <InputFieldText value={managerType} onChange={setManagerType} placeholder="Vadovo tipas" />

                        <InputFieldSelect label="Vadovo lytis" options={["Vyras", "Moteris"]} selected={managerGender} onChange={setManagerGender} placeholder="Vadovo lytis" />

                        <div className={styles.row}>
                            <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={managerFirstName} onChange={setManagerFirstName} placeholder="Vadovo vardas" />
                            <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={managerLastName} onChange={setManagerLastName} placeholder="Vadovo pavardė" />
                        </div>

                        <InputFieldText value={role} onChange={setRole} placeholder="Pareigos" />
                    </div>
                ) : formLocale === "en" ? (
                    <div className={styles.form}>
                        <div className={styles.localeNotice}>
                            <h2>Anglų kalbos laukai</h2>
                            <p>Šie duomenys naudojami anglų kalbos šablonams ir nėra privalomi.</p>
                        </div>
                        <InputFieldText value={companyNameEn} onChange={setCompanyNameEn} placeholder="Įmonės pavadinimas (EN)" />
                        <InputFieldText value={addressEn} onChange={setAddressEn} placeholder="Adresas (EN)" />
                        <InputFieldText value={cityOrDistrictEn} onChange={setCityOrDistrictEn} placeholder="Miestas / rajonas (EN)" />
                        <div className={styles.row}>
                            <InputFieldText value={managerFirstNameEn} onChange={setManagerFirstNameEn} placeholder="Vardas (EN)" />
                            <InputFieldText value={managerLastNameEn} onChange={setManagerLastNameEn} placeholder="Pavardė (EN)" />
                        </div>
                        <InputFieldText value={roleEn} onChange={setRoleEn} placeholder="Pareigos (EN)" />
                    </div>
                ) : (
                    <div className={styles.form}>
                        <div className={styles.localeNotice}>
                            <h2>Rusų kalbos laukai</h2>
                            <p>Šie duomenys naudojami rusų kalbos šablonams ir nėra privalomi.</p>
                        </div>
                        <InputFieldText value={companyNameRu} onChange={setCompanyNameRu} placeholder="Įmonės pavadinimas (RU)" />
                        <InputFieldText value={addressRu} onChange={setAddressRu} placeholder="Adresas (RU)" />
                        <InputFieldText value={cityOrDistrictRu} onChange={setCityOrDistrictRu} placeholder="Miestas / rajonas (RU)" />
                        <div className={styles.row}>
                            <InputFieldText value={managerFirstNameRu} onChange={setManagerFirstNameRu} placeholder="Vardas (RU)" />
                            <InputFieldText value={managerLastNameRu} onChange={setManagerLastNameRu} placeholder="Pavardė (RU)" />
                        </div>
                        <InputFieldText value={roleRu} onChange={setRoleRu} placeholder="Pareigos (RU)" />
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