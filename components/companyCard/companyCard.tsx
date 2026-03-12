"use client";

import { Building2 } from "lucide-react";
import styles from "./companyCard.module.scss";

export type CompanyCardRequisites = {
    company_type?: string;
    company_name?: string;
    code?: string;
    address?: string;
    city_or_district?: string;
    manager_first_name?: string;
    manager_last_name?: string;
    manager_gender?: string;
    role?: string;
};

const REKVIZITAS_LABEL: Record<string, string> = {
    code: "Įmonės kodas",
    address: "Adresas",
    city_or_district: "Miestas / rajonas",
    manager_first_name: "Vadovo vardas",
    manager_last_name: "Vadovo pavardė",
    manager_gender: "Vadovo lytis",
    role: "Pareigos",
};

export default function CompanyCard(props: CompanyCardRequisites) {
    const {
        company_type = "",
        company_name = "",
        code,
        address,
        city_or_district,
        manager_first_name,
        manager_last_name,
        manager_gender,
        role,
    } = props;

    const pavadinimas = [company_type, company_name].filter(Boolean).join(" ") || "—";
    const rekvizitai = [
        { key: "code", value: code },
        { key: "address", value: address },
        { key: "city_or_district", value: city_or_district },
        { key: "manager_first_name", value: manager_first_name },
        { key: "manager_last_name", value: manager_last_name },
        { key: "manager_gender", value: manager_gender },
        { key: "role", value: role },
    ].filter((r) => r.value != null && r.value !== "");

    return (
        <article className={styles.companyCard}>
            <div className={styles.cardHeader}>
                <div className={styles.cardIcon}>
                    <Building2 size={22} />
                </div>
                <h2 className={styles.companyName}>{pavadinimas}</h2>
            </div>
            <dl className={styles.rekvizitai}>
                {rekvizitai.map(({ key, value }) => (
                    <div key={key} className={styles.row}>
                        <dt className={styles.label}>{REKVIZITAS_LABEL[key] ?? key}</dt>
                        <dd className={styles.value}>{String(value)}</dd>
                    </div>
                ))}
            </dl>
        </article>
    );
}