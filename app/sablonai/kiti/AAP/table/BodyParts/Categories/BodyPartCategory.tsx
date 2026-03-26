"use client";
import BodyPartCell from "./BodyPartCell";
import type { BodyPart, BodyPartCategory } from "@/lib/types/AAP/BodyPart";
import styles from "../bodyParts.module.scss";
import { useAAPTable } from "../../../AAPTableContext";

interface BodyPartsCategoryProps {
    category: BodyPartCategory;
}

export default function BodyPartsCategory({ category }: BodyPartsCategoryProps) {

    const { bodyParts } = useAAPTable();
    const filtered = bodyParts.filter(
        (bp) => bp.id === category.id
    );

    return (
        <div className={styles.bodyPartCategory}>
            <p>
                {category.name}
            </p>
            {filtered.map((bp) => (
                <BodyPartCell key={bp.id} bodyPart={bp}/>
            ))}
        </div>
    );
}