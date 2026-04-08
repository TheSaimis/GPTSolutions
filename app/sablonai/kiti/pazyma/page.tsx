"use client";

import { useState } from "react";
import DocumentController from "./documentController/DocumentController";
import { PazymaProvider } from "./pazymaContext";
import RiskController from "./riskController/RiskController";
import TemplateController from "./templateController/TemplateController";
import WorkerController from "./workerController/workerController";
import styles from "./page.module.scss";
import PazymaWorkflowTopNav, {
  type PazymaWorkflowTab,
} from "@/components/pazyma/PazymaWorkflowTopNav";

export default function RiskControllerPage() {
  const [activeController, setActiveController] =
    useState<PazymaWorkflowTab>("document");

  return (
    <PazymaProvider>
      <div className={styles.pageShell}>
        <PazymaWorkflowTopNav
          active={activeController}
          onChange={setActiveController}
        />
        <div className={styles.pageContent}>
          {activeController === "document" ? <DocumentController /> : null}
          {activeController === "worker" ? <WorkerController /> : null}
          {activeController === "risk" ? <RiskController /> : null}
          {activeController === "template" ? <TemplateController /> : null}
        </div>
      </div>
    </PazymaProvider>
  );
}