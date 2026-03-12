"use client";

import { User } from "lucide-react";
import styles from "./userCard.module.scss";

export type UserCardData = {
    id?: number;
    email?: string;
    firstName?: string;
    lastName?: string;
    role?: string;
};

const FIELD_LABEL: Record<string, string> = {
    id: "ID",
    email: "El. paštas",
    firstName: "Vardas",
    lastName: "Pavardė",
    role: "Rolė",
};

function roleLabel(role?: string): string {
    if (role === "ROLE_ADMIN") return "Administratorius";
    if (role === "ROLE_USER") return "Naudotojas";
    return role ?? "—";
}

export default function UserCard(props: UserCardData) {
    const { id, email, firstName, lastName, role } = props;
    const fullName = [firstName, lastName].filter(Boolean).join(" ") || "—";
    const fields = [
        { key: "id", value: id != null ? String(id) : undefined },
        { key: "email", value: email },
        { key: "firstName", value: firstName },
        { key: "lastName", value: lastName },
        { key: "role", value: role != null ? roleLabel(role) : undefined },
    ].filter((f) => f.value != null && f.value !== "");

    return (
        <article className={styles.userCard}>
            <div className={styles.cardHeader}>
                <div className={styles.cardIcon}>
                    <User size={22} />
                </div>
                <h2 className={styles.userName}>{fullName}</h2>
            </div>
            <dl className={styles.fields}>
                {fields.map(({ key, value }) => (
                    <div key={key} className={styles.row}>
                        <dt className={styles.label}>{FIELD_LABEL[key] ?? key}</dt>
                        <dd className={styles.value}>{String(value)}</dd>
                    </div>
                ))}
            </dl>
        </article>
    );
}