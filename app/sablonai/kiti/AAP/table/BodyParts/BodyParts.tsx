"use client";

import { useMemo, useState } from "react";
import { useAAPTable } from "../../AAPTableContext";
import { buildBodyRows, buildRiskHeader, flattenColumns } from "../tableMatrix";
import BodyPartsCategory from "./Categories/BodyPartCategory";
import { RiskApi } from "@/lib/api/AAP/risk";

function getKey(bodyPartId: number, riskSubcategoryId: number) {
  return `${bodyPartId}-${riskSubcategoryId}`;
}

export default function BodyParts() {
  const {
    bodyPartCategories,
    bodyParts,
    riskGroups,
    riskCategories,
    riskSubCategories,
    risks,
    selectedWorkerId,
    setRisks,
    beginRiskUpdate,
    endRiskUpdate,
  } = useAAPTable();
  const [pendingKeys, setPendingKeys] = useState<Set<string>>(new Set());

  const header = useMemo(
    () => buildRiskHeader(riskGroups, riskCategories, riskSubCategories),
    [riskGroups, riskCategories, riskSubCategories]
  );
  const columns = useMemo(() => flattenColumns(header), [header]);
  const bodyRows = useMemo(
    () => buildBodyRows(bodyPartCategories, bodyParts),
    [bodyPartCategories, bodyParts]
  );

  const selectedWorkerRiskMap = useMemo(() => {
    if (!selectedWorkerId) return new Map<string, (typeof risks)[number]>();
    const map = new Map<string, (typeof risks)[number]>();

    risks.forEach((riskList) => {
      if (
        riskList.worker?.id === selectedWorkerId &&
        riskList.bodyPart?.id &&
        riskList.riskSubcategory?.id
      ) {
        map.set(getKey(riskList.bodyPart.id, riskList.riskSubcategory.id), riskList);
      }
    });

    return map;
  }, [risks, selectedWorkerId]);

  async function toggleRiskCell(bodyPartId: number, riskSubcategoryId: number) {
    if (!selectedWorkerId) return;
    const key = getKey(bodyPartId, riskSubcategoryId);
    const existing = selectedWorkerRiskMap.get(key);

    setPendingKeys((prev) => new Set(prev).add(key));
    beginRiskUpdate();
    try {
      if (existing?.id) {
        await RiskApi.deleteRiskList(existing.id);
        setRisks((prev) => prev.filter((r) => r.id !== existing.id));
      } else {
        const created = await RiskApi.createRiskList({
          workerId: selectedWorkerId,
          bodyPartId,
          riskSubcategoryId,
        });
        setRisks((prev) => [...prev, created]);
      }
    } finally {
      endRiskUpdate();
      setPendingKeys((prev) => {
        const next = new Set(prev);
        next.delete(key);
        return next;
      });
    }
  }

  return (
    <tbody>
      {bodyRows.map((row) => (
        <BodyPartsCategory
          key={`c-${row.category.id}`}
          category={row.category}
          parts={row.parts}
          columns={columns}
          selectedWorkerId={selectedWorkerId}
          selectedWorkerRiskMap={selectedWorkerRiskMap}
          pendingKeys={pendingKeys}
          onToggleRisk={toggleRiskCell}
        />
      ))}
    </tbody>
  );
}