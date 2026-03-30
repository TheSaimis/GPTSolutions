import { LucideIcon, ChevronDown } from "lucide-react";
import styles from "./styles/inputFields.module.scss";
import { useEffect, useMemo, useRef, useState } from "react";

type Option =
  | string
  | {
      value?: string;
      label: string;
    };

type Props = {
  options: Option[];
  /** Rodo pasirinktos opcijos pavadinimą (valdomas tekstas iš tėvo) */
  selected?: string;
  placeholder?: string;
  /** Viršutinė etiketė (pvz. „Įmonė“). Jei nenurodyta – viršuje nieko nerodoma. */
  label?: string;
  /** Kai nėra opcijų – rodomas tekstas disablintame lauke */
  emptyMessage?: string;
  onChange: (v: string) => void;
  disabled?: boolean;
  icon?: LucideIcon;
};

export default function InputFieldSelect({
  options,
  selected = "",
  placeholder = "",
  label,
  emptyMessage = "Nėra pasirinkimų",
  onChange,
  disabled = false,
  icon: Icon,
}: Props) {
  const [visible, setVisible] = useState(false);
  const [internalSelectedLabel, setInternalSelectedLabel] = useState("");
  const containerRef = useRef<HTMLDivElement>(null);

  const normalizedOptions = useMemo(
    () =>
      options.map((o) =>
        typeof o === "string"
          ? { value: o, label: o }
          : { value: o.value ?? o.label, label: o.label }
      ),
    [options]
  );

  const isEmpty = normalizedOptions.length === 0;
  const effectivelyDisabled = disabled || isEmpty;
  const selectedText = selected.trim();
  const shownSelection = selectedText || internalSelectedLabel;

  const displayValue = isEmpty
    ? emptyMessage
    : shownSelection || placeholder || "—";

  const showMuted =
    !isEmpty && !shownSelection && Boolean(placeholder?.trim());

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (
        containerRef.current &&
        !containerRef.current.contains(e.target as Node)
      ) {
        setVisible(false);
      }
    }
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, []);

  useEffect(() => {
    if (effectivelyDisabled) {
      setVisible(false);
    }
  }, [effectivelyDisabled]);

  useEffect(() => {
    if (selectedText) {
      setInternalSelectedLabel(selectedText);
    }
  }, [selectedText]);

  return (
    <div
      ref={containerRef}
      className={`${styles.inputField} ${styles.selectField}`}
    >
      {label?.trim() ? (
        <span className={styles.selectFieldLabel}>
          {Icon ? <Icon size={16} className={styles.selectFieldLabelIcon} /> : null}
          {label}
        </span>
      ) : null}

      <div
        className={`${styles.selectShell} ${visible && !isEmpty ? styles.selectShellOpen : ""} ${effectivelyDisabled ? styles.selectShellDisabled : ""}`}
        onClick={() =>
          !effectivelyDisabled && setVisible((prev) => !prev)
        }
        onKeyDown={(e) => {
          if (effectivelyDisabled) return;
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            setVisible((prev) => !prev);
          }
          if (e.key === "Escape") setVisible(false);
        }}
        role="combobox"
        aria-expanded={visible && !isEmpty}
        aria-disabled={effectivelyDisabled}
        aria-haspopup="listbox"
        tabIndex={effectivelyDisabled ? -1 : 0}
      >
        <span
          className={
            showMuted || isEmpty ? styles.selectValueMuted : styles.selectValue
          }
        >
          {displayValue}
        </span>
        <ChevronDown
          size={18}
          className={`${styles.selectChevron} ${visible && !isEmpty ? styles.selectChevronOpen : ""}`}
          aria-hidden
        />
      </div>

      {visible && !isEmpty ? (
        <ul className={styles.selectOptionsList} role="listbox">
          {normalizedOptions.map((v) => (
            <li key={String(v.value) + v.label} role="none">
              <button
                type="button"
                role="option"
                className={styles.selectOptionButton}
                onClick={(e) => {
                  e.stopPropagation();
                  onChange(v.value || v.label);
                  setInternalSelectedLabel(v.label);
                  setVisible(false);
                }}
              >
                {v.label}
              </button>
            </li>
          ))}
        </ul>
      ) : null}
    </div>
  );
}
