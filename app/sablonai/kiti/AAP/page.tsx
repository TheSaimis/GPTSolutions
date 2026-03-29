"use client";

import { AAPTableProvider } from "./AAPTableContext";
import AAPTable from "./table/table";
import WorkerTypes from "./WorkerTypes";
import styles from "./page.module.scss";

export default function Page() {
  return (
    <div className={styles.page}>
      <AAPTableProvider>
        <div className={styles.layout}>
          <WorkerTypes />
          <div className={styles.tableCard}>
            <div className={styles.tableSection}>
              <AAPTable />
            </div>
          </div>
        </div>
      </AAPTableProvider>
    </div>
  );
}