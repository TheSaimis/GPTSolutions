"use client";

import styles from "../../page.module.scss";

export default function EquipmentTemplate() {
    return (
        <div className={styles.card}>
            <p className={styles.itemText}>
                Šiame modulyje dokumentas generuojamas iš sistemos duomenų (darbuotojai + priskirtos priemonės),
                todėl atskiro DOCX šablono įkėlimo nereikia.
            </p>
            <p className={styles.muted}>
                Naudokite skiltį „Dokumento kūrimas“ — pasirinkite įmonę ir sugeneruokite dokumentą.
            </p>
        </div>
    );
}