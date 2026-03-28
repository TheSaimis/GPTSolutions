"use client"

import { useState } from "react";
import DocumentController from "./documentController/DocumentController";
import { PazymaProvider } from "./pazymaContext"
import RiskController from "./riskController/RiskController"
import WorkerController from "./workerController/workerController"
import styles from "./page.module.scss";

type ControllerTab = "document" | "worker" | "risk";


export default function RiskControllerPage() {
  const [activeController, setActiveController] = useState<ControllerTab>("document");

  return (
    <PazymaProvider>
      <div className={styles.topPanel}>
        <button
          type="button"
          className={`${styles.tabButton} ${activeController === "document" ? styles.tabButtonActive : ""}`}
          onClick={() => setActiveController("document")}
        >
          Dokumentų kūrimas
        </button>
        <button
          type="button"
          className={`${styles.tabButton} ${activeController === "worker" ? styles.tabButtonActive : ""}`}
          onClick={() => setActiveController("worker")}
        >
          Darbuotojų valdymas
        </button>
        <button
          type="button"
          className={`${styles.tabButton} ${activeController === "risk" ? styles.tabButtonActive : ""}`}
          onClick={() => setActiveController("risk")}
        >
          Rizikų valdymas
        </button>
      </div>
      {activeController === "document" ? <DocumentController /> : null}
      {activeController === "worker" ? <WorkerController /> : null}
      {activeController === "risk" ? <RiskController /> : null}
    </PazymaProvider>
  )
}