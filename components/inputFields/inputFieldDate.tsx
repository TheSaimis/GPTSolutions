import styles from "./styles/inputFields.module.css";

type Props = {
    value: any;
    placeholder?: string;
    onChange: (next: string) => void;
}

export default function InputFieldDate({value, placeholder, onChange}: Props) {
    return (
        <div className={styles.inputField}>
            <h2>{placeholder}</h2>
            <input type="date" value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)}/>
        </div>
    )
}