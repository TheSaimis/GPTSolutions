"use client";

import styles from "./styles/inputFields.module.scss";
import { CalendarDays } from "lucide-react";
import { useId, useRef } from "react";

type Props = {
    value: string;
    placeholder?: string;
    onChange: (next: string) => void;
};

function formatDate(value: string) {
    if (!value) return "mm / dd / yyyy";

    const [year, month, day] = value.split("-");
    if (!year || !month || !day) return value;

    return `${month} / ${day} / ${year}`;
}

export default function InputFieldDate({ value, placeholder, onChange }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const id = useId();

    function openPicker() {
        if (!inputRef.current) return;

        inputRef.current.focus();

        if (typeof inputRef.current.showPicker === "function") {
            inputRef.current.showPicker();
        }
    }

    return (
        <div className={styles.inputFieldDate}>
            {placeholder && (
                <label className={styles.label} htmlFor={id}>
                    {placeholder}
                </label>
            )}

            <div
                className={styles.dateShell}
                onClick={openPicker}
                role="button"
                tabIndex={0}
                onKeyDown={(e) => {
                    if (e.key === "Enter" || e.key === " ") {
                        e.preventDefault();
                        openPicker();
                    }
                }}
            >
                <span
                    className={`${styles.displayValue} ${!value ? styles.placeholder : ""}`}
                >
                    {formatDate(value)}
                </span>

                <CalendarDays size={22} className={styles.calendarIcon} />

                <input
                    id={id}
                    ref={inputRef}
                    className={styles.nativeDateInput}
                    type="date"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                />
            </div>
        </div>
    );
}