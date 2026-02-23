import Link from "next/link";
import styles from "./header.module.scss";

export default function Header() {
   return (
     <header className={styles.header}>
        <Link href="/" className={styles.logo}>
            <span className={styles.logoAccent}>Darbo</span> specialistai
        </Link>
        <nav className={styles.nav}>
            <Link href="/sablonai" className={styles.navLink}>Šablonai</Link>
            <Link href="/imones" className={styles.navLink}>Įmonės</Link>
            <Link href="/atsisiusti" className={styles.navLink}>Atsisiusti</Link>
        </nav>
    </header>
    )
}
