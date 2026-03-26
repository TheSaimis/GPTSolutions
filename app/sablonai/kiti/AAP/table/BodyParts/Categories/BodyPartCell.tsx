"use client";

import styles from "../bodyParts.module.scss";
import type { BodyPart } from "@/lib/types/AAP/BodyPart";

type BodyPartCellProps = {
    bodyPart: BodyPart;
};

export default function BodyPartCell({ bodyPart }: BodyPartCellProps) {



    return (
        <div className={styles.bodyPartCell}>
            {bodyPart.name}
        </div>
    )
}