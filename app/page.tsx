"use client";

import styles from "./page.module.scss";
import { Building2, FileText, Archive, ArrowRight, Download } from "lucide-react";
import { GeneratedFilesApi } from "@/lib/api/generatedFiles";
import Link from "next/link";

export default function Home() {



  const cards = [
    {
      href: "/sablonai",
      icon: FileText,
      title: "Šablonų katalogas",
      description: "Peržiūrėkite ir naudokite dokumentų šablonus",
    },
    {
      href: "/imones",
      icon: Building2,
      title: "Pridėti įmonę",
      description: "Registruokite naują įmonę sistemoje",
    },
    // {
    //   href: "/atsisiusti",
    //   icon: Archive,
    //   title: "Atsisiųsti katalogą",
    //   description: "Parsisiųskite visą dokumentų katalogą",
    // },
  ];

  async function getGeneratedFiles() {
    const { blob, filename } = await GeneratedFilesApi.getAll();

    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename || "generated.zip";
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  }

  return (
    <div className={styles.page}>
      <div className={styles.hero}>
        <h1 className={styles.heroTitle}>Sveiki atvykę</h1>
        <p className={styles.heroSubtitle}>Pasirinkite veiksmą, kurį norite atlikti</p>
      </div>

      <div className={styles.grid}>
        {cards.map((card) => (
          <Link key={card.href} href={card.href} className={styles.card}>
            <div className={styles.cardIcon}>
              <card.icon size={28} />
            </div>
            <div className={styles.cardContent}>
              <h2 className={styles.cardTitle}>{card.title}</h2>
              <p className={styles.cardDescription}>{card.description}</p>
            </div>
            <ArrowRight size={18} className={styles.cardArrow} />
          </Link>
        ))}

        <button className={styles.card} onClick={getGeneratedFiles}>
          <div className={styles.cardIcon}>
            <Archive/>
          </div>
          <div className={styles.cardContent}>
            <h2 className={styles.cardTitle}>Atsisiųsti katalogą</h2>
            <p className={styles.cardDescription}>Parsisiųskite visą dokumentų katalogą</p>
          </div>
          <Download size={18} className={styles.cardArrow} />
        </button>

      </div>
    </div>
  );
}
