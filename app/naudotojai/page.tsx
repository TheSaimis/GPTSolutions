"use client";

import { useEffect, useState } from "react";
// import InputFieldUsername from "@/components/inputFields/inputFieldUsername";
// import InputFieldRights from "@/components/inputFields/inputFieldRights";
// import InputFieldUserPassword from "@/components/inputFields/inputFieldUserPassword";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldPassword from "@/components/inputFields/inputFieldPassword";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { UserPlus, ArrowLeft, Save } from "lucide-react";
import Link from "next/link";
import styles from "./page.module.scss";
export default function NaudotojaiPage() {
    const [vardas, setVardas] = useState("");
    const [teises, setTeises] = useState("");
    const [slaptazodis, setSlaptazodis] = useState("");

    useEffect(() => {
        document.title = "Pridėti naudotoją";
    }, []);

    function handleSubmit() {
        console.log({ vardas, teises, slaptazodis });
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
                        <UserPlus size={24} />
                    </div>
                    <div>
                        <h1 className={styles.title}>Pridėti naudotoją</h1>
                        <p className={styles.subtitle}>Užpildykite naudotojo duomenis</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.form}>
                    <InputFieldText value={vardas} onChange={setVardas} placeholder="Vardas"/>
                    <InputFieldSelect options={["Administratorius", "Vartotojas"]} onChange={setTeises} placeholder="Teises"/>
                    <InputFieldPassword value={slaptazodis} onChange={setSlaptazodis} placeholder="Slaptazodis"/>
                </div>

                <button className={styles.submitButton} onClick={handleSubmit}>
                    <Save size={18} />
                    Išsaugoti
                </button>
            </div>
        </div>
    );
}