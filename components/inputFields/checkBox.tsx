import styles from "./styles/inputFields.module.scss";
import { Check } from "lucide-react";

type Props = {
    value: any;
    onChange: (next: boolean) => void;
}

export default function CheckBox({value, onChange}: Props) {
    return (
        <div className={`${styles.checkBox} ${value && styles.checked}`} onClick={() => onChange(!value)}>
            <Check className={styles.icon}/>
        </div>
    )
}