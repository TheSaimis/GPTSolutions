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
  const { setEquipment, setWorkerItems } = useEquipment();
  const [activeTab, setActiveTab] = useState<EquipmentTab>("document");

  useEffect(() => {
    EquipmentApi.getWorkerItems().then((res) => {
      setWorkerItems(res);
      console.log(res);
    });
  }, [setEquipment]);

  const ActiveComponent = componentMap[activeTab];

  return (
    <div className={styles.container}>
      <div className={styles.navigation}>
        <button className="buttons" onClick={() => setActiveTab("document")}>
          Dokumento kūrimas
        </button>
        <button className="buttons" onClick={() => setActiveTab("assignment")}>
          Apsaugos priemonių priskirimas
        </button>
        <button className="buttons" onClick={() => setActiveTab("equipment")}>
          Apsaugos priemonės
        </button>
        <button className="buttons" onClick={() => setActiveTab("template")}>
          Šablonas
        </button>
      </div>

      <h1 className={styles.title}>Nemokamai išduodamų priemonių sąrašas</h1>

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