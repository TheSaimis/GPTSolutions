"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { Building2, ChevronDown, Pencil } from "lucide-react";
import type { Company } from "@/lib/types/Company";
import { companyLabels } from "@/lib/types/Company";
import styles from "./CompanyExpandableRow.module.scss";

const DETAIL_ORDER: (keyof Company)[] = [
  "id",
  "companyType",
  "companyName",
  "code",
  "address",
  "cityOrDistrict",
  "managerType",
  "managerFirstName",
  "managerLastName",
  "managerGender",
  "role",
  "documentDate",
  "createdAt",
  "modifiedAt",
];

type Props = {
  company: Company;
};

export default function CompanyExpandableRow({ company }: Props) {
  const [expanded, setExpanded] = useState(false);
  const [role, setRole] = useState("");

  useEffect(() => {
    setRole(localStorage.getItem("role") || "");
  }, []);

  const id = company.id;
  const type = company.companyType ?? "—";
  const name = company.companyName ?? "—";
  const code = company.code ?? "—";

  const detailEntries = DETAIL_ORDER.filter((key) => {
    const v = company[key];
    return v !== null && v !== undefined && String(v).trim() !== "";
  }).map((key) => [key, company[key]] as const);

  return (
    <article className={styles.wrap}>
      <button
        type="button"
        className={styles.summary}
        onClick={() => setExpanded((e) => !e)}
        aria-expanded={expanded}
      >
        <span className={styles.chevron} data-open={expanded}>
          <ChevronDown size={20} aria-hidden />
        </span>
        <span className={styles.cellType}>{type}</span>
        <span className={styles.cellName}>{name}</span>
        <span className={styles.cellCode}>{code}</span>
        {role === "ROLE_ADMIN" && id != null && (
          <span
            className={styles.editWrap}
            onClick={(e) => e.stopPropagation()}
          >
            <Link href={`/imone/${id}`} className={styles.editLink} title="Redaguoti įmonę">
              <Pencil size={18} />
            </Link>
          </span>
        )}
      </button>

      {expanded && (
        <div className={styles.details}>
          <div className={styles.detailsInner}>
            <div className={styles.detailsHeader}>
              <Building2 size={20} className={styles.detailsIcon} />
              <span>Visi duomenys</span>
            </div>
            <dl className={styles.rekvizitai}>
              {detailEntries.map(([key, value]) => (
                <div key={key} className={styles.row}>
                  <dt className={styles.label}>{companyLabels[key] ?? key}</dt>
                  <dd className={styles.value}>{String(value)}</dd>
                </div>
              ))}
            </dl>
          </div>
        </div>
      )}
    </article>
  );
}
