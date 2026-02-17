import styles from "./styles/inputFields.module.css";

type Props = {
    value: any;
    placeholder?: string;
    onChange: (next: number) => void;
}

export default function InputFieldNumber({value, placeholder, onChange}: Props) {
    return (
        <div className={styles.inputField}>
            <h2>{placeholder}</h2>
            <input type="text" min="0" step="1" value={value} placeholder={placeholder} onChange={(e) => {
                        const value = e.target.value;
                        if (/^\d*([.,]\d{0,2})?$/.test(value)) {
                            onChange(Number(value.replace(",", ".")));
                        }
                    }}/>
        </div>
    )
}