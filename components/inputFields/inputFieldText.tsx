"use client";

import React, { forwardRef } from "react";
import styles from "./styles/inputFields.module.scss";
import { LucideIcon } from "lucide-react";

type Props = {
  value: string;
  regex?: RegExp;
  placeholder?: string;
  onChange: (v: string) => void;
  onFocus?: (b: boolean) => void;
  onKeyDown?: Record<string, () => void>;
  type?: string;
  icon?: LucideIcon;
  disabled?: boolean;
};

const InputFieldText = forwardRef<HTMLInputElement, Props>(
  (
    { value, placeholder, onChange, type, icon: Icon, regex, onFocus, onKeyDown, disabled },
    ref
  ) => {
    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
      if (disabled) return;
      const val = e.target.value;

      if (val === "") {
        onChange(val);
        return;
      }

      if (regex && !regex.test(val)) return;

      onChange(val);
    }

    return (
      <div className={styles.inputField}>
        <h2>
          {Icon && <Icon size={18} className={styles.icon} />} {placeholder}
        </h2>

        <input
          ref={ref}
          className={styles.input}
          type={type || "text"}
          value={value}
          placeholder={placeholder}
          disabled={disabled}
          onChange={handleChange}
          onFocus={(e) => {
            e.target.select();
            onFocus?.(true);
          }}
          onBlur={() => onFocus?.(false)}
          onKeyDown={(e) => {
            const fn = onKeyDown?.[e.key];
            if (!fn) return;
            e.preventDefault();
            fn();
          }}
        />
      </div>
    );
  }
);
InputFieldText.displayName = "InputFieldText";

export default InputFieldText;