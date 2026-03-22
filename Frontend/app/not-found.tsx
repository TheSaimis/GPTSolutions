import Link from "next/link";
import { Home, FileQuestion } from "lucide-react";
import styles from "./not-found.module.scss";

export default function NotFound() {
    return (
        <div className={styles.page}>
            <div className={styles.card}>
                <div className={styles.iconWrap}>
                    <FileQuestion size={48} />
                </div>
                <h1 className={styles.title}>404</h1>
                <p className={styles.subtitle}>Puslapis nerastas</p>
                <p className={styles.description}>
                    Puslapis su šia nuoroda neegzistuoja arba puslapis buvo perkeltas.
                </p>
                <Link href="/" className={styles.homeLink}>
                    <Home size={18} />
                    Grįžti į pradžią
                </Link>
            </div>
        </div>
    );
}