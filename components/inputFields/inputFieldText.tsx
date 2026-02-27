import styles from "./styles/inputFields.module.scss";
import { LucideIcon } from "lucide-react";

type Props = {
  value: string;
  regex?: RegExp;
  placeholder?: string;
  onChange: (v: string) => void;
  type?: string;
  icon?: LucideIcon;
};

export default function InputFieldText({
  value,
  placeholder,
  onChange,
  type,
  icon: Icon,
  regex,
}: Props) {
  function handleChange(e: React.ChangeEvent<HTMLInputElement>) {
    const val = e.target.value;

    if (val === "") {
      onChange(val);
      return;
    }

    if (regex) {
      if (regex.test(val)) onChange(val);
      return;
    }

    onChange(val);
  }

  return (
    <div className={styles.inputField}>
      <h2>{Icon && <Icon size={18} className={styles.icon} />} {placeholder}</h2>
      <input
        className={styles.input}
        type={type || "text"}
        value={value}
        placeholder={placeholder}
        onChange={handleChange}
      />
    </div>
  );
}