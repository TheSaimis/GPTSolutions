"use client";

import styles from "./styles/inputFields.module.scss";
import { forwardRef } from "react";

type Props = {
  value: File | File[] | null;
  placeholder?: string;
  accept?: readonly string[] | string;
  onChange: (files: File | File[]) => void;
}

const normalizeAccept = (accept?: readonly string[] | string) => {
  const arr = Array.isArray(accept) ? accept : accept ? [accept] : [];
  return arr.map(ext => ext.startsWith(".") ? ext : `.${ext}`).join(", ");
};

const InputFieldFile = forwardRef<HTMLInputElement, Props>(
  ({ value, placeholder, accept, onChange }, ref) => {
    return (
      <div className={styles.inputField}>
        <h2>{placeholder}</h2>
        <input
          className={styles.input}
          ref={ref}
          type="file"
          multiple
          accept={normalizeAccept(accept)}
          onChange={(e) => {
            const files = Array.from(e.target.files ?? []);
            onChange(files.length === 1 ? files[0] : files);
          }}
        />
        {value && (Array.isArray(value)
          ? value.map((f) => <p key={f.name}>{f.name}</p>)
          : <p>{value.name}</p>
        )}
      </div>
    );
  }
);

InputFieldFile.displayName = "InputFieldFile";

export default InputFieldFile;