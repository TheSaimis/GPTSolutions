"use client";

import { useEffect, useState } from "react";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldPassword from "@/components/inputFields/inputFieldPassword";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import { UsersApi } from "@/lib/api/users";
import { UserPlus, Save } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import styles from "./page.module.scss";

export default function NaudotojaiPage() {
    const [firstName, setFirstName] = useState("");
    const [lastName, setLastName] = useState("");
    const [email, setEmail] = useState("");
    const [role, setRole] = useState("");
    const [password, setPassword] = useState("");

    useEffect(() => {
        document.title = "Pridėti naudotoją";
    }, []);

    async function handleSubmit() {
        UsersApi.userCreate({ firstName, lastName, email, role, password });
    }

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <PageBackBar />
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
                    <div className={styles.nameField}>
                        <InputFieldText value={firstName} onChange={setFirstName} placeholder="Vardas" />
                        <InputFieldText value={lastName} onChange={setLastName} placeholder="Pavardė" />
                    </div>
                    <InputFieldText value={email} onChange={setEmail} type="email" placeholder="Prisijungimo paštas" />
                    <InputFieldSelect options={[{ value: "ROLE_ADMIN", label: "Administratorius" },{ value: "ROLE_USER", label: "Naudotojas" },]} onChange={setRole} placeholder="Teisės"/>
                    <InputFieldPassword autocomplete={"new-password"} value={password} onChange={setPassword} placeholder="Slaptažodis" />
                </div>

                <button className={styles.submitButton} onClick={handleSubmit}>
                    <Save size={18} />
                    Išsaugoti
                </button>
            </div>
        </div>
    );
}