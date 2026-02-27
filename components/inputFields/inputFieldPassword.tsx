import { LucideIcon, Eye, EyeClosed } from "lucide-react";
import styles from "./styles/inputFields.module.scss";
import { useState } from "react";

type Props = {
    value: any;
    placeholder?: string;
    onChange: (v: string) => void;
    icon?: LucideIcon;
}



export default function InputFieldPassword({ value, placeholder, onChange, icon: Icon }: Props) {

    const [visible, setVisible] = useState(false);

    return (
        <div className={styles.inputField}>
            <h2> {Icon && <Icon size={18} className={styles.icon} />} {placeholder}</h2>
            <div className={styles.password}>
                <input className={styles.input} type={visible ? "text" : "password"} value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} />
                <button onClick={() => setVisible(!visible)}>{visible ? <Eye size={18} className={styles.icon} /> : <EyeClosed size={18} className={styles.icon} />}</button>
            </div>
        </div>
    )
}