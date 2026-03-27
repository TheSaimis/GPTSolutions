"use client";

import type { BodyPart } from "@/lib/types/AAP/BodyPart";
import type { RiskSubcategory } from "@/lib/types/AAP/Risk";
import styles from "../../aapTable.module.scss";

interface BodyPartCellProps {
  bodyPart: BodyPart;
  columns: RiskSubcategory[];
  isWorkerSelected: boolean;
  isRiskActive: (riskSubcategoryId: number) => boolean;
  isPending: (riskSubcategoryId: number) => boolean;
  onToggleRisk: (bodyPartId: number, riskSubcategoryId: number) => void;
}

export default function BodyPartCell({
  bodyPart,
  columns,
  isWorkerSelected,
  isRiskActive,
  isPending,
  onToggleRisk,
}: BodyPartCellProps) {
  return (
    <>
      <td className={`${styles.td} ${styles.stickyLeftSecond} ${styles.partCol}`}>{bodyPart.name}</td>
      {columns.map((col, index) => {
        const active = isRiskActive(col.id);
        const pending = isPending(col.id);

        return (
          <td
            key={col.id}
            className={`${styles.td} ${styles.cell} ${index % 2 === 1 ? styles.altColumn : ""}`}
          >
            <button
              type="button"
              className={`${styles.cellButton} ${active ? styles.cellButtonActive : ""} ${
                !isWorkerSelected || pending ? styles.cellButtonDisabled : ""
              }`}
              disabled={!isWorkerSelected || pending}
              title={
                !isWorkerSelected
                  ? "Pasirinkite darbuotojo tipą"
                  : active
                  ? "Pašalinti riziką"
                  : "Pridėti riziką"
              }
              onClick={() => onToggleRisk(bodyPart.id, col.id)}
            >
              {active ? "+" : ""}
            </button>
          </td>
        );
      })}
    </>
  );
}
