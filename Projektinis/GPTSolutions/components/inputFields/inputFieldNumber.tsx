import styles from "./styles/inputFields.module.scss";

type Props = {
    value: string;
    placeholder?: string;
    onChange: (next: string) => void;
    regex?: RegExp;        // optional
    type?: "text" | "number";
}

export default function InputField({
    value,
    placeholder,
    onChange,
    regex,
    type = "text"
}: Props) {

    function handleChange(e: React.ChangeEvent<HTMLInputElement>) {

        const val = e.target.value;

        // always allow empty
        if (val === "") {
            onChange(val);
            return;
        }

        // if regex exists → validate with regex
        if (regex) {
            if (regex.test(val)) {
                onChange(val);
            }
            return;
        }

        // if no regex → allow normal number/text input
        onChange(val);
    }

    return (
        <div className={styles.inputField}>
            <h2>{placeholder}</h2>

            <input
                className={styles.input}
                type={type}
                value={value}
                placeholder={placeholder}
                onChange={handleChange}
            />

        </div>
    );
}