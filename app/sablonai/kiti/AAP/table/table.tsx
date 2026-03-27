"use client";

import styles from "./aapTable.module.scss";
import BodyParts from "./BodyParts/BodyParts";
import Risks from "./Risks/Risks";

export default function AAPTable() {
    return (
        <div className={styles.wrapper}>
            <div className={styles.scroll}>
                <table className={styles.table}>
                    <Risks />
                    <BodyParts />
                </table>
            </div>
        </div>
    );
}