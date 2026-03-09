"use client";

import { useEffect, useState } from "react";
import { HelpCircle, ArrowLeft, Info, Copy, CheckCircle2, X, ZoomIn, Search } from "lucide-react";
import Link from "next/link";
import Image from "next/image";
import styles from "./page.module.scss";

// Svarbu: Nuotraukos turi būti tame pačiame aplanke kaip šis failas
import beforeImg from "./before.png";
import afterImg from "./after.png";

export default function KaipNaudotiPage() {
    const [searchTerm, setSearchTerm] = useState("");
    const [copiedIndex, setCopiedIndex] = useState<number | null>(null);
    const [selectedImg, setSelectedImg] = useState<any>(null);

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

    const filteredKintamieji = kintamieji.filter((item) =>
        item.kodas.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.aprasymas.toLowerCase().includes(searchTerm.toLowerCase())
    );

    const copyToClipboard = (text: string, index: number) => {
        navigator.clipboard.writeText(text).then(() => {
            setCopiedIndex(index);
            setTimeout(() => setCopiedIndex(null), 1500);
        });
    };

    return (
        <div className={styles.page}>
            {/* Padidinimo modalinis langas */}
            {selectedImg && (
                <div className={styles.modalOverlay} onClick={() => setSelectedImg(null)}>
                    <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
                        <button className={styles.closeButton} onClick={() => setSelectedImg(null)}>
                            <X size={32} />
                        </button>
                        <Image src={selectedImg} alt="Padidintas vaizdas" priority />
                    </div>
                </div>
            )}

            <div className={styles.topBar}>
                <Link href="/" className={styles.backLink}>
                    <ArrowLeft size={16} />
                    Grįžti į pradžią
                </Link>
            </div>

            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><HelpCircle size={24} /></div>
                    <div>
                        <h1 className={styles.title}>Šablonų naudojimas</h1>
                        <p className={styles.subtitle}>Kintamųjų sąrašas ir jų reikšmės</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.content}>
                    <div className={styles.infoBox}>
                        <Info size={20} />
                        <p>Naudokite šiuos kintamuosius šablonuose. Sistema juos užpildys automatiškai.</p>
                    </div>

                    <div className={styles.searchWrapper}>
                        <Search size={18} className={styles.searchIcon} />
                        <input
                            type="text"
                            placeholder="Ieškoti kintamojo..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className={styles.searchInput}
                        />
                    </div>

                    <table className={styles.varTable}>
                        <thead>
                            <tr>
                                <th>Kintamasis</th>
                                <th>Reikšmė</th>
                                <th style={{ width: "40px" }}></th>
                            </tr>
                        </thead>
                        <tbody>
                            {filteredKintamieji.map((item, index) => (
                                <tr key={index}>
                                    <td><code>{item.kodas}</code></td>
                                    <td>{item.aprasymas}</td>
                                    <td>
                                        <button onClick={() => copyToClipboard(item.kodas, index)} className={styles.copyButton}>
                                            {copiedIndex === index ? <CheckCircle2 size={16} className={styles.successIcon} /> : <Copy size={16} />}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Pavyzdžių sekcija su vienodo dydžio konteineriais */}
            <div className={styles.exampleCard}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><Info size={24} /></div>
                    <div>
                        <h2 className={styles.title}>Pavyzdys: Kaip tai veikia</h2>
                        <p className={styles.subtitle}>Paspauskite ant nuotraukos, kad padidintumėte</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.exampleGrid}>
                    <div className={styles.exampleItem}>
                        <h3>Šablonas (Prieš)</h3>
                        <div className={styles.imageWrapper} onClick={() => setSelectedImg(beforeImg)}>
                            <div className={styles.zoomIcon}><ZoomIn size={24} /></div>
                            <div className={styles.imageHeightFix}>
                                <Image src={beforeImg} alt="Prieš" placeholder="blur" fill className={styles.imgContain} />
                            </div>
                        </div>
                    </div>
                    <div className={styles.exampleItem}>
                        <h3>Galutinis Dokumentas (Po)</h3>
                        <div className={styles.imageWrapper} onClick={() => setSelectedImg(afterImg)}>
                            <div className={styles.zoomIcon}><ZoomIn size={24} /></div>
                            <div className={styles.imageHeightFix}>
                                <Image src={afterImg} alt="Po" placeholder="blur" fill className={styles.imgContain} />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer className={styles.footer}>
                <p>© 2026 Dokumentų Valdymo Sistema</p>
            </footer>
        </div>
    );
}