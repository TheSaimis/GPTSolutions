import Link from "next/link";
import { ArrowLeft } from "lucide-react";
import styles from "./PageBackBar.module.scss";

type Props = { href?: string; label?: string; className?: string };

export default function PageBackBar({ href = "/", label = "Grįžti į pradžią", className }: Props) {
    return (
        <div className={`${styles.bar} ${className ?? ""}`}>
            <Link href={href} className={styles.link}>
                <ArrowLeft size={16} aria-hidden />
                {label}
            </Link>
        </div>
    );
}
