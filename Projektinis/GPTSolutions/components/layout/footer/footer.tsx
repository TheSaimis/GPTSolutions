import Image from "next/image";
import styles from "./footer.module.scss";

export default function Footer() {
    return (
    <footer className={styles.footer}>
        <div className={styles.footerContent}>
            <Image
                src="/logo-red.png"
                alt="Darbo specialistai"
                width={120}
                height={32}
                className={styles.footerLogo}
            />
            <p className={styles.text}>© 2026 Darbo specialistai. Visos teisės saugomos.</p>
        </div>
    </footer>
    )
}