"use client";

import styles from "./page.module.scss";
import { Building2, FileText, Download, ArrowRight, User, CircleQuestionMark } from "lucide-react";
import { GeneratedFilesApi } from "@/lib/api/generatedFiles";
import Link from "next/link";
import { useEffect, useState } from "react";

export default function Home() {

  const [role, setRole] = useState<string>("");
  useEffect(() => {
    setRole(localStorage.getItem("role") || "");
    document.title = "Pagrindinis";
  },

    []);

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
        <p className={styles.heroSubtitle}>Pasirinkite veiksmą, kurį norite atlikti šiandien</p>
      </div>

      <div className={styles.grid}>
        <Link href="/sablonai" className={styles.card}>
          <div className={styles.cardIcon}>
            <FileText size={28} />
          </div>
          <h2 className={styles.cardTitle}>Šablonų katalogas</h2>
          <p className={styles.cardDescription}>
            Peržiūrėkite ir naudokite dokumentų šablonus. Raskite tinkamą šabloną savo verslui ir atsisiųskite jį vienu paspaudimu.
          </p>
          <span className={styles.cardButton}>
            Peržiūrėti šablonus <ArrowRight size={16} />
          </span>
        </Link>

        <Link href="/imones" className={styles.card}>
          <div className={styles.cardIcon}>
            <Building2 size={28} />
          </div>
          <h2 className={styles.cardTitle}>Pridėti įmonę</h2>
          <p className={styles.cardDescription}>
            Registruokite naują įmonę sistemoje ir pradėkite naudotis paslaugomis.
          </p>
          <span className={styles.cardButton}>
            Registruoti įmonę <ArrowRight size={16} />
          </span>
        </Link>

        {role == "ROLE_ADMIN" && (
          <Link href={"/naudotojai"} className={styles.card}>
            <div className={styles.cardIcon}>
              <User size={28} />
            </div>
            <h2 className={styles.cardTitle}>Pridėti naudotoją</h2>
            <p className={styles.cardDescription}>
              Registruokite naują naudotoją sistemoje.
            </p>
            <span className={styles.cardButton}>
              Registruoti naudotoją <ArrowRight size={16} />
            </span>
          </Link>
        )}

        <button className={styles.card} onClick={getGeneratedFiles}>
          <div className={styles.cardIcon}>
            <Download size={28} />
          </div>
          <h2 className={styles.cardTitle}>Atsisiųsti katalogą</h2>
          <p className={styles.cardDescription}>
            Parsisiųskite visą dokumentų katalogą .ZIP formatu ir naudokite offline.
          </p>
          <span className={styles.cardButton}>
            Atsisiųsti .ZIP <ArrowRight size={16} />
          </span>
        </button>

        <Link href="/kaip-naudotis" className={styles.card}>
          <div className={styles.cardIcon}>
            <CircleQuestionMark size={28} />
          </div>
          <h2 className={styles.cardTitle}>Kaip naudotis?</h2>
          <p className={styles.cardDescription}>
            Kaip sukurti šabloną ir naudotis sistema.
          </p>
          <span className={styles.cardButton}>
            Kaip naudotis <ArrowRight size={16} />
          </span>
        </Link>
      </div>
    </div>
  );
}