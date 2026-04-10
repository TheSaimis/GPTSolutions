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
    <div className={styles.container}>
      <div className={styles.navigation}>
        <button
          className={`${styles.tabButton} ${activeTab === "document" ? styles.tabButtonActive : ""}`}
          onClick={() => setActiveTab("document")}
        >
          Dokumento kūrimas
        </button>
        <button
          className={`${styles.tabButton} ${activeTab === "assignment" ? styles.tabButtonActive : ""}`}
          onClick={() => setActiveTab("assignment")}
        >
          Apsaugos priemonių priskirimas
        </button>
        <button
          className={`${styles.tabButton} ${activeTab === "equipment" ? styles.tabButtonActive : ""}`}
          onClick={() => setActiveTab("equipment")}
        >
          Apsaugos priemonės
        </button>
        <button
          className={`${styles.tabButton} ${activeTab === "template" ? styles.tabButtonActive : ""}`}
          onClick={() => setActiveTab("template")}
        >
          Šablonas
        </button>
      </div>

      <h1 className={styles.title}>AAP Kortelės+Žiniaraščiai</h1>

      <ActiveComponent />
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