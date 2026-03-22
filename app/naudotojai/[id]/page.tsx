"use client";

import { use, useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { User, Save, Trash } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import { UsersApi } from "@/lib/api/users";
import { MessageStore } from "@/lib/globalVariables/messages";
import { useConfirmAction } from "@/components/confirmationPanel/confirmationPanel";
import Link from "next/link";
import styles from "./page.module.scss";
import InputFieldText from "@/components/inputFields/inputFieldText";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import InputFieldPassword from "@/components/inputFields/inputFieldPassword";

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
    const [deleted, setDeleted] = useState(false);
    const [password, setPassword] = useState<string>("");
    const [deletedDate, setDeletedDate] = useState("");
    const [role, setRole] = useState("");
    const [readOnly, setReadOnly] = useState<{ createdAt?: string; modifiedAt?: string }>({});
    const { confirmAction } = useConfirmAction();

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
                    setDeleted(u.deleted ?? false);
                    setDeletedDate(u.deletedDate ?? "");
                }
            })
            .catch(() => { })
            .finally(() => setLoading(false));
    }, [id]);

    async function handleSubmit() {
        if (Number.isNaN(id)) return;
        try {
            await UsersApi.userUpdate(id, { email, firstName, lastName, role, ...(password && { password }) });
            MessageStore.push({ title: "Sėkmingai", message: "Naudotojas atnaujintas", backgroundColor: "#22C55E" });
            router.push("/naudotojai/sarasas");
        } catch {
            // error handled by api
        }
    }

    async function deleteUser() {
        if (Number.isNaN(id)) return;
        try {
            const confirmed = await confirmAction({
                title: "Ištrinti naudotoją?",
                message: "Atliekus ši veiksmą vartotojas išliks duomenų bazėje 7 dienas.\n Praėjus šiam laikotarpiui vartotojas bus ištrintas visam laikui",
                type: "delete",
                confirmText: "Ištrinti",
                cancelText: "Atšaukti",
                icon: Trash,
            });
            if (!confirmed) return;

            await UsersApi.userDelete(id);
            MessageStore.push({ title: "Sėkmingai", message: "Naudotojas ištrintas", backgroundColor: "#22C55E" });
            setDeleted(true);
            setDeletedDate(new Date().toISOString());
        } catch {
            // error handled by api
        }
    }
    async function restoreUser() {
        if (Number.isNaN(id)) return;
        try {
            await UsersApi.userRestore(id);
            MessageStore.push({ title: "Sėkmingai", message: "Naudotojas atkurtas", backgroundColor: "#22C55E" });
            setDeleted(false);
            setDeletedDate("");
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
                    { !deleted &&
                        <div className={styles.trashIcon} onClick={deleteUser}>
                            <Trash size={24} />
                        </div>
                    }
                </div>

                <div className={styles.divider} />

                {deleted && (
                    <>
                        <div className={styles.deletedSection}>
                            <div className={styles.deletedRow}>
                                <p className={styles.deletedLabel}>Šis vartotojas yra ištrintas</p>
                                <p className={styles.deletedValue}>Ištrinimo data: {deletedDate}</p>
                                <p>Praėjus 7 dienom nuo ištrinimo datos vartotojas bus pašalintas iš duomenų bazės visam laikui</p>
                            </div>
                            <button onClick={restoreUser} className="buttons">Atstatyti vartotoją</button>
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
                </div>

                <div className={styles.divider} />

                <div className={styles.form}>
                    <div className={styles.row}>
                        <InputFieldText value={firstName} onChange={setFirstName} placeholder="Vardas" />
                        <InputFieldText value={lastName} onChange={setLastName} placeholder="Pavardė" />
                    </div>
                    <InputFieldText value={email} onChange={setEmail} type="email" placeholder="El. paštas" />
                    <InputFieldSelect options={ROLE_OPTIONS} selected={role} onChange={setRole} placeholder="Teisės" />
                    <InputFieldPassword autocomplete="new-password" value={password} onChange={setPassword} placeholder="Slaptažodis" />
                </div>

                <button className={styles.submitButton} onClick={handleSubmit}>
                    <Save size={18} />
                    Išsaugoti
                </button>
            </div>
        </div>
    );
}
