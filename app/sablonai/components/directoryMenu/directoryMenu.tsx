"use client";

import styles from "./directoryMenu.module.scss";
import { DirectoryStore, useDirectoryStore } from "@/lib/globalVariables/directoriesToSend";
import { useRouter } from "next/navigation";


export default function DirectoryMenu() {

    const router = useRouter();

    const hasSelection = useDirectoryStore(
        (state) => state.selected.length > 0
      );
      function clicked() {
        router.push(`/sablonai/createBulk`);
      }
    

    return (
        <div className={`${styles.directoryMenu} ${hasSelection && styles.selected}`}>
            <button type="button" disabled={!hasSelection} onClick={clicked}>Kurti dokumentus</button>
            <button type="button" onClick={() => DirectoryStore.clear()}>Išvalyti pasirinkimą</button>
        </div>
    );
}  
