"use client";

import { useEffect, useState } from "react";
import styles from "./page.module.scss";
import { EquipmentApi } from "@/lib/api/equipment";
import { EquipmentProvider, useEquipment } from "./equipmentContext";

import EquipmentController from "./tabs/equipmentController/equipmentController";
import EquipmentTable from "./tabs/documentController/table";
import EquipmentTemplate from "./tabs/template/template";
import WorkerEquipmentController from "./tabs/workerEquipmentController/workerEquipmentController";

type EquipmentTab = "document" | "assignment" | "equipment" | "template";
const componentMap = {
  document: EquipmentTable,
  assignment: WorkerEquipmentController,
  equipment: EquipmentController,
  template: EquipmentTemplate,
} satisfies Record<EquipmentTab, React.ComponentType>;

function EquipmentPageContent() {
  const { setEquipment, setWorkers } = useEquipment();
  const [activeTab, setActiveTab] = useState<EquipmentTab>("document");

  useEffect(() => {
    EquipmentApi.getAll().then(setEquipment).catch(() => undefined);
    import("@/lib/api/workers").then(({ WorkersApi }) => {
      WorkersApi.getAll().then(setWorkers).catch(() => undefined);
    });
  }, [setEquipment, setWorkers]);

  const ActiveComponent = componentMap[activeTab];

  return (
    <div className={styles.workflowShell}>
      <nav className={styles.workflowNav} aria-label="AAP darbo eiga">
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "document" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("document")}
          aria-current={activeTab === "document" ? "page" : undefined}
        >
          Dokumento kūrimas
        </button>
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "assignment" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("assignment")}
          aria-current={activeTab === "assignment" ? "page" : undefined}
        >
          Apsaugos priemonių priskirimas
        </button>
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "equipment" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("equipment")}
          aria-current={activeTab === "equipment" ? "page" : undefined}
        >
          Apsaugos priemonės
        </button>
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "template" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("template")}
          aria-current={activeTab === "template" ? "page" : undefined}
        >
          Šablonas
        </button>
      </nav>

      <div className={styles.workflowContent}>
        <h1 className={styles.workflowPageTitle}>AAP Kortelės+Žiniaraščiai</h1>
        <ActiveComponent />
      </div>
    </div>
  );
}

export default function Page() {
  return (
    <EquipmentProvider>
      <EquipmentPageContent />
    </EquipmentProvider>
  );
}