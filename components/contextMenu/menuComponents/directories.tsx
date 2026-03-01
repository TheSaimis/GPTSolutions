"use client";

import styles from "../contextMenu.module.scss";

type Props = {
  onSelect: (action: string) => void;
};

export default function Directories({ onSelect }: Props) {
  return (
    <>
      <button className={styles.button} onClick={() => onSelect("new-folder")}>
        Naujas aplankas
      </button>
      <button className={styles.button} onClick={() => onSelect("new-template")}>
        Naujas šablonas
      </button>
      <button className={styles.button} onClick={() => onSelect("rename")}>
        Pervadinti
      </button>
      <button className={styles.button} onClick={() => onSelect("delete")}>
        Ištrinti
      </button>
    </>
  );
}