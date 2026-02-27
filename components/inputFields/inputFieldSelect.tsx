import { LucideIcon, ChevronDown } from "lucide-react";
import styles from "./styles/inputFields.module.scss";
import { useEffect, useRef, useState } from "react";

type Props = {
    options: any[];
    selected?: any;
    placeholder?: string;
    onChange: (v: string) => void;
    icon?: LucideIcon;
}



export default function InputFieldSelect({ options, selected, placeholder, onChange, icon: Icon }: Props) {

    const [visible, setVisible] = useState(false);
    const [option, setOption] = useState(selected || placeholder || options[0]);
    const containerRef = useRef<HTMLDivElement>(null);

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
                    {options.map((v) => (
                        <p
                            key={v}
                            onClick={() => {
                                setOption(v);
                                onChange(v);
                            }}
                        >
                            {v}
                        </p>
                    ))}
                </div>
            </div>

        </div>
    )
}