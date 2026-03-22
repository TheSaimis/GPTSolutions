"use client";

import { use, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { User, Save } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import { UsersApi } from "@/lib/api/users";
import { MessageStore } from "@/lib/globalVariables/messages";
import Link from "next/link";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";

const ROLE_OPTIONS = [
    { value: "ROLE_ADMIN", label: "Administratorius" },
    { value: "ROLE_USER", label: "Naudotojas" },
];

type PageParams = Promise<{ id: string }>;

export default function NaudotojoRedagavimasPage({ params }: { params: PageParams }) {
    const { id: idParam } = use(params);
    const router = useRouter();
    const id = typeof idParam === "string" ? parseInt(idParam, 10) : NaN;

    const [loading, setLoading] = useState(true);
    const [email, setEmail] = useState("");
    const [firstName, setFirstName] = useState("");
    const [lastName, setLastName] = useState("");
    const [role, setRole] = useState("");
    const [readOnly, setReadOnly] = useState<{ createdAt?: string; modifiedAt?: string }>({});

    useEffect(() => {
        if (Number.isNaN(id)) {
            setLoading(false);
            return;
        }
        document.title = "Redaguoti naudotoją";
        UsersApi.getById(id)
            .then((u) => {
                if (u) {
                    setEmail(u.email ?? "");
                    setFirstName(u.firstName ?? "");
                    setLastName(u.lastName ?? "");
                    setRole(u.role ?? "");
                    setReadOnly({
                        createdAt: u.createdAt,
                        modifiedAt: u.modifiedAt,
                    });
                }
            })
            .catch(() => {})
            .finally(() => setLoading(false));
    }, [id]);

    async function handleSubmit() {
        if (Number.isNaN(id)) return;
        try {
            await UsersApi.userUpdate(id, { email, firstName, lastName, role });
            MessageStore.push({ title: "Sėkmingai", message: "Naudotojas atnaujintas", backgroundColor: "#22C55E" });
            router.push("/naudotojai/sarasas");
        } catch {
            // error handled by api
        }
    }

    if (loading) {
        return <p className={styles.message}>Kraunama...</p>;
    }
    if (Number.isNaN(id)) {
        return (
            <div className={styles.page}>
                <p className={styles.message}>Neteisingas naudotojo ID.</p>
                <Link href="/naudotojai/sarasas" className={styles.backLink}>Grįžti į sąrašą</Link>
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
                        <User size={24} />
                    </div>
                    <div>
                        <h1 className={styles.title}>Redaguoti naudotoją</h1>
                        <p className={styles.subtitle}>Keiskite naudotojo duomenis (ID, sukūrimo ir redagavimo datos nekeičiamos)</p>
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
                </div>

                <div className={styles.divider} />

                <div className={styles.form}>
                    <div className={styles.row}>
                        <InputFieldText value={firstName} onChange={setFirstName} placeholder="Vardas" />
                        <InputFieldText value={lastName} onChange={setLastName} placeholder="Pavardė" />
                    </div>
                    <InputFieldText value={email} onChange={setEmail} type="email" placeholder="El. paštas" />
                    <InputFieldSelect options={ROLE_OPTIONS} selected={role} onChange={setRole} placeholder="Teisės" />
                </div>

                <button className={styles.submitButton} onClick={handleSubmit}>
                    <Save size={18} />
                    Išsaugoti
                </button>
            </div>
        </div>
    );
}
