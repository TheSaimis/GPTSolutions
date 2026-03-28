"use client";

import type { HealthCertificateRiskFactor, HealthCertificateWorkerRisk } from "@/lib/types/healthCertificate";
import InputFieldSelect from "@/components/inputFields/inputFieldSelect";
import Risk from "../../riskController/components/risk";
import { useState } from "react";
import styles from "../../controllers.module.scss";

type WorkerRiskProps = {
  workerRisk: HealthCertificateWorkerRisk;
  riskFactors: HealthCertificateRiskFactor[];
  selectedWorkerId: number;
  onUpdate: (workerRiskId: number, riskFactorId: number) => Promise<void>;
  onDelete: (workerRiskId: number) => Promise<void>;
  onRiskUpdate: (riskId: number, input: { name: string; cipher: string }) => Promise<void>;
  busy: boolean;
};

export default function WorkerRisk({
  workerRisk,
  riskFactors,
  selectedWorkerId,
  onUpdate,
  onDelete,
  onRiskUpdate,
  busy,
}: WorkerRiskProps) {
  const [isEditing, setIsEditing] = useState(false);
  const [riskFactorId, setRiskFactorId] = useState<number>(workerRisk.riskFactor?.id ?? 0);
  const selectedRiskFactor = riskFactors.find((riskFactor) => riskFactor.id === riskFactorId);

  async function handleSave() {
    if (!selectedWorkerId || !riskFactorId) return;
    await onUpdate(workerRisk.id, riskFactorId);
    setIsEditing(false);
  }

  function handleCancel() {
    setRiskFactorId(workerRisk.riskFactor?.id ?? 0);
    setIsEditing(false);
  }

  return (
    <div>
      {isEditing ? (
        <>
          <InputFieldSelect
            options={riskFactors.map((riskFactor) => ({
              value: String(riskFactor.id),
              label: `${riskFactor.name} (${riskFactor.cipher})`,
            }))}
            selected={selectedRiskFactor ? `${selectedRiskFactor.name} (${selectedRiskFactor.cipher})` : ""}
            placeholder="Pasirinkite rizikos faktorių"
            onChange={(value) => setRiskFactorId(Number(value) || 0)}
          />
        </>
      ) : null}

      {workerRisk.riskFactor ? (
        <Risk
          risk={workerRisk.riskFactor}
          onUpdate={onRiskUpdate}
          onDelete={async () => onDelete(workerRisk.id)}
          busy={busy}
        />
      ) : (
        <p className={styles.subtitle}>Nenurodytas faktorius</p>
      )}

      {/* <div className={styles.actions}>
        <button
          type="button"
          className={`${styles.button} ${styles.buttonDanger}`}
          onClick={() => onDelete(workerRisk.id)}
          disabled={busy}
        >
          Šalinti šį priskyrimą pareigybei
        </button>
      </div> */}
    </div>
  );
}
