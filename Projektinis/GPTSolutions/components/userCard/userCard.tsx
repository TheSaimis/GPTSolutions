"use client";

import { User, Pencil } from "lucide-react";
import Link from "next/link";
import styles from "./userCard.module.scss";

export type UserCardData = {
    id?: number;
    email?: string;
    firstName?: string;
    lastName?: string;
    role?: string | string[];
    deleted?: boolean;
    variant?: "large" | "compact" | "mini";
};

const FIELD_LABEL: Record<string, string> = {
    id: "ID",
    email: "El. paštas",
    firstName: "Vardas",
    lastName: "Pavardė",
    role: "Rolė",
};

function roleLabel(role?: string | string[]): string {
    const r = Array.isArray(role) ? role[0] : role;
    if (r === "ROLE_ADMIN") return "Administratorius";
    if (r === "ROLE_USER") return "Naudotojas";
    return r ?? "—";
}

export default function UserCard(props: UserCardData) {
    const { id, email, firstName, lastName, role, deleted, variant = "large" } = props;
    const fullName = [firstName, lastName].filter(Boolean).join(" ") || "—";
    const allFields = [
        { key: "email", value: email },
        { key: "firstName", value: firstName },
        { key: "lastName", value: lastName },
        { key: "role", value: role != null ? roleLabel(role) : undefined },
    ].filter((f) => f.value != null && f.value !== "");
    const fields =
        variant === "mini"
            ? allFields.filter((field) => field.key === "role")
            : variant === "compact"
                ? allFields.filter((field) => field.key === "email" || field.key === "role")
                : allFields;

    return (
        <article
            className={`${styles.userCard} ${deleted ? styles.deleted : ""} ${variant === "compact" ? styles.compact : ""} ${variant === "mini" ? styles.mini : ""}`}
        >
            <div className={styles.cardHeader}>
                <div className={styles.cardIcon}>
                    <User size={22} />
                </div>
                <h2 className={styles.userName}>{fullName}</h2>
                {id != null && (
                    <Link href={`/naudotojai/${id}`} className={styles.editButton} title="Redaguoti naudotoją">
                        <Pencil size={18} />
                    </Link>
                )}
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