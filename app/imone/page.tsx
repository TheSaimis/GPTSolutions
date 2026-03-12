"use client";

import { useEffect } from "react";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import CompanyCard, { type CompanyCardRequisites } from "@/components/companyCard/CompanyCard";
import styles from "./page.module.scss";

const IMONE_REKVIZITAI: CompanyCardRequisites = {
    company_type: "UAB",
    company_name: "Senukai",
    code: "300060896",
    address: "Pramonės g. 16, Vilnius",
    city_or_district: "Vilniaus m.",
    manager_first_name: "Jonas",
    manager_last_name: "Jonaitis",
    manager_gender: "Vyras",
    role: "Generalinis direktorius",
};

export default function ImonePage() {
    useEffect(() => {
        document.title = "Įmonė";
    }, []);

    return (
        <div className={styles.page}>
            <div className={styles.topBar}>
                <Link href="/" className={styles.backLink}>
                    <ArrowLeft size={16} />
                    Grįžti į pradžią
                </Link>
            </div>

            <div className={styles.content}>
                <h1 className={styles.pageTitle}>Įmonė</h1>
                <CompanyCard {...IMONE_REKVIZITAI} />
            </div>
        </div>
    );
}