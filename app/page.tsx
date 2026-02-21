"use client";

import styles from "./page.module.scss";
import { Building2, File, Archive } from "lucide-react";
import Link from "next/link";
import { useRouter } from "next/navigation";


export default function Home() {

  const router = useRouter()

  return (
    <div className={styles.dashboardContainer}>

      <div className={styles.dashboard}>

        <Link href={"/sablonai"} className={styles.button}>
          <File />
          <p>
            Šablonų katalogas
          </p>
        </Link>

        <Link href={"/imones"} className={styles.button}>
          <Building2 />
          <p>
            Pridėti įmonę
          </p>
        </Link>


        <Link href={"/atsisiusti"} className={styles.button}>
          <Archive />
          <p>
            Atsisiusti katalogą
          </p>
        </Link>


      </div>

    </div>
  );
}
