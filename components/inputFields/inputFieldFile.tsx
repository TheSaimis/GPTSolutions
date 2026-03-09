"use client";

import styles from "./styles/inputFields.module.scss";
import { forwardRef } from "react";

type Props = {
    value: File | null;
    placeholder?: string;
    accept?: string;
    onChange: (file: File | null) => void;
}

const InputFieldFile = forwardRef<HTMLInputElement, Props>(
    ({ value, placeholder, accept, onChange }, ref) => {
      return (
        <div className={styles.inputField}>
          <h2>{placeholder}</h2>
  
          <input
            className={styles.input}
            ref={ref}
            type="file"
            accept={accept}
            onChange={(e) => {
              const f = e.target.files?.[0] ?? null;
              onChange(f);
            }}
          />
  
          {value && <p>{value.name}</p>}
        </div>
      );
    }
  );
  
  InputFieldFile.displayName = "InputFieldFile";
  
  export default InputFieldFile;