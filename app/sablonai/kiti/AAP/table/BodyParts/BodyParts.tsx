"use client";
import BodyPartsCategory from "./Categories/BodyPartCategory";
import { AAPTableProvider, useAAPTable } from "../../AAPTableContext";
import styles from "./bodyParts.module.scss";

export default function BodyParts() {

    const { loading } = useAAPTable();
    const { bodyPartCategories } = useAAPTable();

    return (
        <div className={styles.bodyParts}>
            THIS IS bodyparts
            {bodyPartCategories.map((bodyPart) => (

                <BodyPartsCategory key={bodyPart.id} category={bodyPart}/>

            ))}


        </div>
    )
}