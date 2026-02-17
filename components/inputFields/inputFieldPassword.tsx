import { LucideIcon } from "lucide-react";
import styles from "./styles/inputFields.module.css";

type Props = {
    value: any;
    placeholder?: string;
    onChange: (v: string) => void;
    icon?: LucideIcon;
}

export default function InputFieldPassword({value, placeholder, onChange, icon: Icon}: Props) {
    return (
        <div className={styles.inputField}>
            <h2> {Icon && <Icon size={18} className={styles.icon} />} {placeholder}</h2>
            <input type="text" value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)}/>
        </div>
    )
}