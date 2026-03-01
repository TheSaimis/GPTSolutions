import { LucideIcon, ChevronDown } from "lucide-react";
import styles from "./styles/inputFields.module.scss";
import { useEffect, useRef, useState } from "react";



type Option =
  | string
  | {
      value?: string;
      label: string;
    };


type Props = {
    options: Option[];
    selected?: any;
    placeholder?: string;
    onChange: (v: string) => void;
    icon?: LucideIcon;
}



export default function InputFieldSelect({ options, selected, placeholder, onChange, icon: Icon }: Props) {

    const [visible, setVisible] = useState(false);
    const [option, setOption] = useState(selected || placeholder || options[0]);
    const [search, setSearch] = useState("");
    const containerRef = useRef<HTMLDivElement>(null);

    const normalizedOptions = options.map(o =>
        typeof o === "string"
          ? { value: o, label: o }
          : { value: o.value ?? o.label, label: o.label }
      );

    useEffect(() => {
        function handleClickOutside(e: MouseEvent) {
          if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
            setVisible(false);
          }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
      }, []);

    return (
        <div ref={containerRef} className={`${styles.inputField} ${styles.select}`}>

            <h2> {Icon && <Icon size={18} className={styles.icon} />} {placeholder}</h2>

            <div onClick={() => setVisible(!visible)} className={`${styles.select} ${styles.input}`}>
                <div className={styles.selected}>
                    <p>{option}</p>
                    <ChevronDown size={18} className={styles.icon} />
                </div>
                <div className={`${visible ? styles.visible : ""} ${styles.options}`}>
                    {normalizedOptions.map((v) => (
                        <p
                            key={v.value ?? v.label}
                            onClick={() => {
                                setOption(v.label);
                                onChange(v.value || v.label);
                            }}
                        >
                            {v.label}
                        </p>
                    ))}
                </div>
            </div>

        </div>
    )
}