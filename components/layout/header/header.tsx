import Link from "next/link";
import Image from "next/image";
import styles from "./header.module.scss";

export default function Header() {
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
            <Link href="/imones" className={styles.navLink}>Įmonės</Link>
            <Link href="/atsisiusti" className={styles.navLink}>Atsisiusti</Link>
        </nav>
    </header>
    )
}