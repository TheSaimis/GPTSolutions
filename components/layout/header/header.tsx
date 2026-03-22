"use client"

import Link from "next/link";
import Image from "next/image";
import styles from "./header.module.scss";
import { useEffect, useState } from "react";
import { User } from "lucide-react";

export default function Header() {

    const [name, setName] = useState<string | null>(null);
    const [role, setRole] = useState<string>("");

    useEffect(() => {
        setName(localStorage.getItem("name"));
        setRole(localStorage.getItem("role") || "");
    }, []);

    return (
        <header className={styles.header}>
            <Link href="/" className={styles.logo}>
                <Image
                    src="/logo-red.png"
                    alt="Darbo specialistai"
                    width={180}
                    height={48}
                    className={styles.logoImage}
                    priority
                />
            </Link>
            <nav className={styles.nav}>
                <Link href="/sablonai" className={styles.navLink}>Šablonai</Link>
                <Link href="/sablonai/sukurtiDokumentai" className={styles.navLink}>Dokumentai</Link>

                {role === "ROLE_ADMIN" && (
                    <>
                        <Link href="/admin" className={styles.navLink}>Administravimas</Link>
                        <Link href="/imones/sarasas" className={styles.navLink}>Įmonės</Link>
                        <Link href="/naudotojai/sarasas" className={styles.navLink}>Vartotojai</Link>
                    </>
                )}
                {name &&
                    <Link href="/profilis" className={styles.navLink}><User size={16} /> {name}</Link>
                }
            </nav>
        </header>
    )
}