import styles from "./CompanyFormLocaleToggle.module.scss";

export type CompanyFormLocale = "lt" | "ru" | "en";

type Props = {
    value: CompanyFormLocale;
    onChange: (locale: CompanyFormLocale) => void;
};

export default function CompanyFormLocaleToggle({ value, onChange }: Props) {
    return (
        <>
            <div className={styles.toggleRow} role="tablist" aria-label="Formos kalba">
                {(["lt", "ru", "en"] as const).map((loc) => (
                    <button
                        key={loc}
                        type="button"
                        role="tab"
                        aria-selected={value === loc}
                        className={`${styles.toggleBtn} ${value === loc ? styles.toggleBtnActive : ""}`}
                        onClick={() => onChange(loc)}
                    >
                        {loc.toUpperCase()}
                    </button>
                ))}
            </div>
        </>
    );
}
