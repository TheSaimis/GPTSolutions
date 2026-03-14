"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { Building2, ArrowLeft, Save } from "lucide-react";
import { CompanyApi } from "@/lib/api/companies";
import { MessageStore } from "@/lib/globalVariables/messages";
import { COMPANY_TYPES } from "@/lib/types/Company";
import Link from "next/link";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";

export default function ImonesRedagavimasPage() {
    const params = useParams();
    const router = useRouter();
    const id = typeof params.id === "string" ? parseInt(params.id, 10) : NaN;

    const [loading, setLoading] = useState(true);
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
    const [readOnly, setReadOnly] = useState<{ createdAt?: string; modifiedAt?: string; documentDate?: string }>({});

    useEffect(() => {
        if (Number.isNaN(id)) {
            setLoading(false);
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
                    setRole(c.role ?? "");
                    setReadOnly({
                        createdAt: c.createdAt,
                        modifiedAt: c.modifiedAt,
                        documentDate: c.documentDate,
                    });
                }
            })
            .finally(() => setLoading(false));
    }, [id]);

    async function handleSubmit() {
        if (Number.isNaN(id)) return;
        const res = await CompanyApi.companyUpdate(id, {
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
        });
        if (res?.status === "SUCCESS") {
            MessageStore.push({ title: "Sėkmingai", message: "Įmonė atnaujinta", backgroundColor: "#22C55E" });
            router.push("/imones/sarasas");
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
                <Link href="/imones/sarasas" className={styles.backLink}>
                    <ArrowLeft size={16} />
                    Grįžti į sąrašą
                </Link>
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
                </div>

                <div className={styles.divider} />

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

                <button className={styles.submitButton} onClick={handleSubmit}>
                    <Save size={18} />
                    Išsaugoti
                </button>
            </div>
        </div>
    );
}
