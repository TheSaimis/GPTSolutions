"use client";

import styles from "./page.module.scss";
import { Building2, FileText, Archive, ArrowRight } from "lucide-react";
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
    {
      href: "/atsisiusti",
      icon: Archive,
      title: "Atsisiųsti katalogą",
      description: "Parsisiųskite visą dokumentų katalogą",
    },
  ];

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
      </div>
    </div>
  );
}
