"use client";

import type { BodyPart, BodyPartCategory } from "@/lib/types/AAP/BodyPart";
import type { RiskList, RiskSubcategory } from "@/lib/types/AAP/Risk";
import BodyPartCell from "./BodyPartCell";
import styles from "../../aapTable.module.scss";

interface BodyPartsCategoryProps {
  category: BodyPartCategory;
  parts: BodyPart[];
  columns: RiskSubcategory[];
  selectedWorkerId: number | null;
  selectedWorkerRiskMap: Map<string, RiskList>;
  pendingKeys: Set<string>;
  onToggleRisk: (bodyPartId: number, riskSubcategoryId: number) => void;
}

function getKey(bodyPartId: number, riskSubcategoryId: number) {
  return `${bodyPartId}-${riskSubcategoryId}`;
}

export default function BodyPartsCategory({
  category,
  parts,
  columns,
  selectedWorkerId,
  selectedWorkerRiskMap,
  pendingKeys,
  onToggleRisk,
}: BodyPartsCategoryProps) {
  const rowCount = Math.max(1, parts.length);

  if (parts.length === 0) {
    return (
      <tr>
        <td className={`${styles.td} ${styles.stickyLeft} ${styles.categoryCol}`}>{category.name}</td>
        <td className={`${styles.td} ${styles.stickyLeftSecond} ${styles.partCol}`}>-</td>
        {columns.map((col, index) => (
          <td
            key={col.id}
            className={`${styles.td} ${styles.cell} ${index % 2 === 1 ? styles.altColumn : ""}`}
          />
        ))}
      </tr>
    );
  }

  return (
    <>
      {parts.map((part, idx) => (
        <tr key={part.id}>
          {idx === 0 && (
            <td className={`${styles.td} ${styles.stickyLeft} ${styles.categoryCol}`} rowSpan={rowCount}>
              {category.name}
            </td>
          )}
          <BodyPartCell
            bodyPart={part}
            columns={columns}
            isWorkerSelected={selectedWorkerId !== null}
            isRiskActive={(riskSubcategoryId) =>
              selectedWorkerRiskMap.has(getKey(part.id, riskSubcategoryId))
            }
            isPending={(riskSubcategoryId) => pendingKeys.has(getKey(part.id, riskSubcategoryId))}
            onToggleRisk={onToggleRisk}
          />
        </tr>
      ))}
    </>
  );
}
