"use client";

import styles from "./PazymaWorkflowTopNav.module.scss";

export type PazymaWorkflowTab = "document" | "worker" | "risk" | "template";

type PazymaWorkflowTopNavProps = {
  active: PazymaWorkflowTab;
  onChange: (tab: PazymaWorkflowTab) => void;
};

const TABS: { id: PazymaWorkflowTab; label: string }[] = [
  { id: "document", label: "Dokumentų kūrimas" },
  { id: "worker", label: "Darbuotojų valdymas" },
  { id: "risk", label: "Rizikų valdymas" },
  { id: "template", label: "Šablonas" },
];

export default function PazymaWorkflowTopNav({
  active,
  onChange,
}: PazymaWorkflowTopNavProps) {
  return (
    <nav className={styles.nav} aria-label="Sveikatos pažymų darbo eiga">
      {TABS.map(({ id, label }) => (
        <button
          key={id}
          type="button"
          className={`${styles.tab} ${active === id ? styles.tabActive : ""}`}
          onClick={() => onChange(id)}
          aria-current={active === id ? "page" : undefined}
        >
          {label}
        </button>
      ))}
    </nav>
  );
}
