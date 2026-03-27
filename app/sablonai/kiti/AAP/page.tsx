"use client";

import { AAPTableProvider } from "./AAPTableContext";
import AAPTable from "./table/table";
import WorkerTypes from "./WorkerTypes";
import styles from "./page.module.scss";

export default function Page() {
  return (
    <div className={styles.page}>
      <AAPTableProvider>
        <div className={styles.tableSection}>
          <AAPTable />
        </div>
        <WorkerTypes />
      </AAPTableProvider>
    </div>
  );
}