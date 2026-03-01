"use client";

import { useEffect, useState } from "react";
import { Building2, ArrowLeft, Save } from "lucide-react";
import { CompanyApi } from "@/lib/api/companies";
import Link from "next/link";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";

export default function ImonesPage() {
    const [company_type, setCompanyType] = useState("");
    const [company_name, setCompanyName] = useState("");
    const [address, setAddress] = useState("");
    const [code, setCode] = useState("");
    const [manager_first_name, setManagerFirstName] = useState("");
    const [manager_last_name, setManagerLastName] = useState("");
    const [role, setRole] = useState("");

    useEffect(() => {
        document.title = "Pridėti įmonę";
    }, []);

    function handleSubmit() {
        CompanyApi.companyCreate({ company_type, company_name, address, code, manager_first_name, manager_last_name, role });
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
                        <InputFieldSelect options={["UAB", "AB", "MB"]} onChange={setCompanyType} placeholder="Įmonės tipas"/>
                        <InputFieldText value={company_name} onChange={setCompanyName} placeholder="Įmones pavadinimas"/>
                    </div>

                    <InputFieldText value={address} onChange={setAddress} placeholder="Adresas"/>
                    <InputFieldNumber regex={/^\d{0,9}$/} value={code} onChange={setCode} placeholder="Įmonės kodas"/>

                    <div className={styles.row}>
                        <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={manager_first_name} onChange={setManagerFirstName} placeholder="Vardas"/>
                        <InputFieldText regex={/^[A-Za-zĄČĘĖĮŠŲŪŽąčęėįšųūž]+$/} value={manager_last_name} onChange={setManagerLastName} placeholder="Pavardė"/>
                    </div>

                    <InputFieldText value={role} onChange={setRole} placeholder="Pareigos"/>
                </div>

                <button className={styles.submitButton} onClick={handleSubmit}>
                    <Save size={18} />
                    Išsaugoti
                </button>
            </div>
        </div>
    );
}