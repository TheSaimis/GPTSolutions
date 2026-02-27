"use client";

import { useEffect, useState } from "react";
// import InputFieldCompanyType from "@/components/inputFields/inputFieldCompanyType";
// import InputFieldCompanyName from "@/components/inputFields/inputFieldCompanyName";
// import InputFieldAddress from "@/components/inputFields/inputFieldAddress";
// import InputFieldCompanyCode from "@/components/inputFields/inputFieldCompanyCode";
// import InputFieldFirstName from "@/components/inputFields/inputFieldFirstName";
// import InputFieldLastName from "@/components/inputFields/inputFieldLastName";
// import InputFieldPosition from "@/components/inputFields/inputFieldPosition";
import { Building2, ArrowLeft, Save } from "lucide-react";
import Link from "next/link";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";

export default function ImonesPage() {
    const [tipas, setTipas] = useState("");
    const [pavadinimas, setPavadinimas] = useState("");
    const [adresas, setAdresas] = useState("");
    const [kodas, setKodas] = useState("");
    const [vardas, setVardas] = useState("");
    const [pavarde, setPavarde] = useState("");
    const [pareigos, setPareigos] = useState("");

    useEffect(() => {
        document.title = "Pridėti įmonę";
    }, []);

    function handleSubmit() {
        console.log({ tipas, pavadinimas, adresas, kodas, vardas, pavarde, pareigos });
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
                        <InputFieldSelect options={["UAB", "AB", "MB"]} onChange={setTipas} placeholder="Įmonės tipas"/>
                        <InputFieldText value={pavadinimas} onChange={setPavadinimas} placeholder="Įmones pavadinimas"/>
                    </div>

                    <InputFieldText value={adresas} onChange={setAdresas} placeholder="Adresas"/>

                    <InputFieldNumber regex={/^\d{0,9}$/} value={kodas} onChange={setKodas} placeholder="Įmonės kodas"/>

                    <div className={styles.row}>
                        <InputFieldText value={vardas} onChange={setVardas} placeholder="Vardas"/>
                        <InputFieldText value={pavarde} onChange={setPavarde} placeholder="Pavardė"/>
                    </div>

                    <InputFieldText value={pareigos} onChange={setPareigos} placeholder="Pareigos"/>
                </div>

                <button className={styles.submitButton} onClick={handleSubmit}>
                    <Save size={18} />
                    Išsaugoti
                </button>
            </div>
        </div>
    );
}