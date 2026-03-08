"use client";

import { useEffect } from "react";
import { HelpCircle, ArrowLeft, Code2, Info } from "lucide-react";
import Link from "next/link";
import styles from "./page.module.scss";

export default function KaipNaudotiPage() {
    useEffect(() => {
        document.title = "Šablonų instrukcija | Sistema";
    }, []);

    const kintamieji = [
        { kodas: "${kompanija}", aprasymas: "Įmonės pavadinimas" },
        { kodas: "${tipas}", aprasymas: "Įmonės kategorija (trumpinys)" },
        { kodas: "${tipasPilnas}", aprasymas: "Pilnas įmonės kategorijos pavadinimas" },
        { kodas: "${adresas}", aprasymas: "Įmonės registracijos adresas" },
        { kodas: "${kodas}", aprasymas: "Įmonės kodas" },
        { kodas: "${data}", aprasymas: "Įsakymo arba dokumento data" },
        { kodas: "${vadovas}", aprasymas: "Įmonės vadovo vardas ir pavardė" },
        { kodas: "${vardas}", aprasymas: "Vadovo vardas" },
        { kodas: "${pavarde}", aprasymas: "Vadovo pavardė" },
        { kodas: "${role}", aprasymas: "Pasirašančio asmens pareigos" },
    ];

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
                        <HelpCircle size={24} />
                    </div>
                    <div>
                        <h1 className={styles.title}>Šablonų naudojimas</h1>
                        <p className={styles.subtitle}>Kaip teisingai užpildyti kintamuosius</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.content}>
                    <div className={styles.infoBox}>
                        <Info size={20} />
                        <p>
                            Dokumentų šablonuose naudokite žemiau pateiktus kintamuosius. 
                            Sistema juos automatiškai pakeis į tikrus duomenis generuojant failą.
                        </p>
                    </div>

                    <table className={styles.varTable}>
                        <thead>
                            <tr>
                                <th>Kintamasis</th>
                                <th>Reikšmė</th>
                            </tr>
                        </thead>
                        <tbody>
                            {kintamieji.map((item, index) => (
                                <tr key={index}>
                                    <td><code>{item.kodas}</code></td>
                                    <td>{item.aprasymas}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <footer className={styles.footer}>
                <p>© 2026 Dokumentų Valdymo Sistema</p>
            </footer>
        </div>
    );
}