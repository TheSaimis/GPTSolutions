"use client";

import styles from "./styles/inputFields.module.scss";
import { forwardRef } from "react";

type SingleProps = {
  value: File | null;
  placeholder?: string;
  accept?: readonly string[] | string;
  multiple?: false;
  onChange: (file: File | null) => void;
};

type MultiProps = {
  value: File[];
  placeholder?: string;
  accept?: readonly string[] | string;
  multiple: true;
  onChange: (files: File[]) => void;
};

type Props = SingleProps | MultiProps;

const normalizeAccept = (accept?: readonly string[] | string) => {
  const arr = Array.isArray(accept) ? accept : accept ? [accept] : [];
  return arr.map((ext) => (ext.startsWith(".") ? ext : `.${ext}`)).join(", ");
};

const InputFieldFile = forwardRef<HTMLInputElement, Props>(
  (props, ref) => {
    const { value, placeholder, accept } = props;

    return (
      <div className={styles.inputField}>
        <h2>{placeholder}</h2>
        <input
          className={styles.input}
          ref={ref}
          type="file"
          multiple={props.multiple}
          accept={normalizeAccept(accept)}
          onChange={(e) => {
            const files = Array.from(e.target.files ?? []);

            if (props.multiple) {
              props.onChange(files);
            } else {
              props.onChange(files[0] ?? null);
            }
          }}
        />
        {value &&
          (Array.isArray(value) ? (
            value.map((f) => <p key={f.name}>{f.name}</p>)
          ) : (
            <p>{value.name}</p>
          ))}
      </div>
    );
  }
);

InputFieldFile.displayName = "InputFieldFile";

export default InputFieldFile;