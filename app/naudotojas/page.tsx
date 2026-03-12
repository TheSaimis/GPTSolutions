"use client";

import { useEffect } from "react";
import { ArrowLeft } from "lucide-react";
import Link from "next/link";
import UserCard, { type UserCardData } from "@/components/userCard/UserCard";
import styles from "./page.module.scss";

const NAUDOTOJO_DUOMENYS: UserCardData = {
    id: 1,
    email: "jonas.jonaitis@example.com",
    firstName: "Jonas",
    lastName: "Jonaitis",
    role: "ROLE_USER",
};

export default function NaudotojasPage() {
    useEffect(() => {
        document.title = "Naudotojas";
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
                <h1 className={styles.pageTitle}>Naudotojas</h1>
                <UserCard {...NAUDOTOJO_DUOMENYS} />
            </div>
        </div>
    );
}