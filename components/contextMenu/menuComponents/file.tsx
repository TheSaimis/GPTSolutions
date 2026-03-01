import styles from "../contextMenu.module.scss";

type Props = {
    value: any;
    directory: any;
    onChange: (next: boolean) => void;
}

export default function File({ value, onChange, directory }: Props) {

    function onClicked() {
        onChange(!value);
        console.log(directory);
    }

    return (
        <button className={styles.button} onClick={onClicked}>
            Naujas šablonas
        </button>
    )
}