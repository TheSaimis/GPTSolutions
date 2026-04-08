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
  search?: boolean;
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
  search = false,
}: Props) {
  const [visible, setVisible] = useState(false);
  const [searchValue, setSearchValue] = useState("");
  const [internalSelectedLabel, setInternalSelectedLabel] = useState("");
  const containerRef = useRef<HTMLDivElement>(null);
  const searchInputRef = useRef<HTMLInputElement>(null);

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
  const selectedText = typeof selected === "string" ? selected.trim() : "";
  const selectedOptionLabel = useMemo(() => {
    if (!selectedText) return "";
    const byValue = normalizedOptions.find(
      (option) => String(option.value) === selectedText
    );
    if (byValue) return byValue.label;
    const byLabel = normalizedOptions.find(
      (option) => option.label.trim() === selectedText
    );
    return byLabel?.label ?? selectedText;
  }, [normalizedOptions, selectedText]);
  const shownSelection = selectedOptionLabel || internalSelectedLabel;

  const displayValue = isEmpty
    ? emptyMessage
    : shownSelection || placeholder || "—";

  const showMuted =
    !isEmpty && !shownSelection && Boolean(placeholder?.trim());

  const filteredOptions = useMemo(() => {
    if (!search) return normalizedOptions;
    const query = searchValue.trim().toLowerCase();
    if (!query) return normalizedOptions;
    return normalizedOptions.filter((option) =>
      option.label.toLowerCase().includes(query)
    );
  }, [normalizedOptions, search, searchValue]);

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
    if (!visible) {
      setSearchValue("");
      return;
    }
    if (!search) return;
    const timeout = window.setTimeout(() => searchInputRef.current?.focus(), 0);
    return () => window.clearTimeout(timeout);
  }, [visible, search]);

  useEffect(() => {
    setInternalSelectedLabel(selectedOptionLabel);
  }, [selectedOptionLabel]);

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
        onClick={() => {
          if (effectivelyDisabled) return;
          if (search) {
            setVisible(true);
            return;
          }
          setVisible((prev) => !prev);
        }}
        onFocus={() => {
          if (!effectivelyDisabled && search) {
            setVisible(true);
          }
        }}
        onKeyDown={(e) => {
          if (effectivelyDisabled) return;
          if (e.key === "Enter" || e.key === " ") {
            e.preventDefault();
            if (search) {
              setVisible(true);
            } else {
              setVisible((prev) => !prev);
            }
          }
          if (e.key === "Escape") setVisible(false);
        }}
        role="combobox"
        aria-expanded={visible && !isEmpty}
        aria-disabled={effectivelyDisabled}
        aria-haspopup="listbox"
        tabIndex={effectivelyDisabled ? -1 : 0}
      >
        {search && !isEmpty ? (
          <input
            ref={searchInputRef}
            type="text"
            value={visible ? searchValue : shownSelection}
            placeholder={placeholder || "Ieškoti..."}
            className={styles.selectSearchInput}
            onClick={(e) => e.stopPropagation()}
            onFocus={() => {
              if (!effectivelyDisabled) setVisible(true);
            }}
            onChange={(e) => {
              if (!visible) setVisible(true);
              setSearchValue(e.target.value);
            }}
            onKeyDown={(e) => {
              if (e.key === "Escape") {
                setVisible(false);
              }
            }}
            disabled={effectivelyDisabled}
            aria-label={label || placeholder || "Paieška"}
          />
        ) : (
          <span
            className={
              showMuted || isEmpty ? styles.selectValueMuted : styles.selectValue
            }
          >
            {displayValue}
          </span>
        )}
        <ChevronDown
          size={18}
          className={`${styles.selectChevron} ${visible && !isEmpty ? styles.selectChevronOpen : ""}`}
          aria-hidden
        />
      </div>

      {visible && !isEmpty ? (
        <ul className={styles.selectOptionsList} role="listbox">
          {filteredOptions.map((v) => (
            <li key={String(v.value) + v.label} role="none">
              <button
                type="button"
                role="option"
                className={styles.selectOptionButton}
                onClick={(e) => {
                  e.stopPropagation();
                  onChange(v.value || v.label);
                  setInternalSelectedLabel(v.label);
                  setSearchValue("");
                  setVisible(false);
                }}
              >
                {v.label}
              </button>
            </li>
          ))}
          {search && filteredOptions.length === 0 ? (
            <li role="none" className={styles.selectNoResults}>
              Nerasta pasirinkimų
            </li>
          ) : null}
        </ul>
      ) : null}
    </div>
  );
}
