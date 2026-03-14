"use client";

import { useEffect, useState } from "react";
import { Building2, ArrowLeft, Save } from "lucide-react";
import { CompanyApi } from "@/lib/api/companies";
import { MessageStore } from "@/lib/globalVariables/messages";
import { COMPANY_TYPES } from "@/lib/types/Company";
import Link from "next/link";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";

export default function ImonesPage() {
    const [companyType, setCompanyType] = useState("");
    const [companyName, setCompanyName] = useState("");
    const [address, setAddress] = useState("");
    const [code, setCode] = useState("");
    const [managerFirstName, setManagerFirstName] = useState("");
    const [managerLastName, setManagerLastName] = useState("");
    const [managerGender, setManagerGender] = useState("");
    const [role, setRole] = useState("");

    useEffect(() => {
        document.title = "Pridėti įmonę";
        CompanyApi.getAll();
    }, []);

    async function handleSubmit() {
        const res = await CompanyApi.companyCreate({ companyType, companyName, address, code, managerFirstName, managerLastName, managerGender, role });
        if (!res.status) {
            MessageStore.push({ title: "Sėkmingai", message: "įmonė sukurta", backgroundColor: "#22C55E" });
        }
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <Link href="/" className={styles.backLink}>
                    <ArrowLeft size={16} />
                    Grįžti į pradžią
                </Link>
            </div>

            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}>
                        <Building2 size={24} />
                    </div>
                    <div>
                        <h1 className={styles.title}>Pridėti įmonę</h1>
                        <p className={styles.subtitle}>Užpildykite įmonės duomenis</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.form}>
                    <div className={styles.row}>
                        <InputFieldSelect options={COMPANY_TYPES} onChange={setCompanyType} placeholder="Įmonės tipas" />
                        <InputFieldText value={companyName} onChange={setCompanyName} placeholder="Įmones pavadinimas" />
                    </div>

                    <InputFieldSelect options={["Vyras", "Moteris"]} onChange={setManagerGender} placeholder="Vadovo lytis" />

                    <InputFieldText value={address} onChange={setAddress} placeholder="Adresas" />
                    <InputFieldNumber regex={/^\d{0,9}$/} value={code} onChange={setCode} placeholder="Įmonės kodas" />

                    <div className={styles.row}>
                        <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={managerFirstName} onChange={setManagerFirstName} placeholder="Vardas" />
                        <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={managerLastName} onChange={setManagerLastName} placeholder="Pavardė" />
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