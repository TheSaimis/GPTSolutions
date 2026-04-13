export type EquipmentDocLang = "LT" | "EN" | "RU";

/** Dokumente: vnt/poros (bet koks registras) verčiami; kitas tekstas rodomas kaip įvesta. */
export function equipmentUnitLabel(stored?: string, docLang: EquipmentDocLang = "LT"): string {
    const s = (stored ?? "").trim();
    const lower = s.toLowerCase();
    if (lower === "poros") {
        if (docLang === "EN") {
            return "Pairs";
        }
        if (docLang === "RU") {
            return "Пары";
        }
        return "Poros";
    }
    if (s === "" || lower === "vnt") {
        if (docLang === "EN") {
            return "Pcs.";
        }
        if (docLang === "RU") {
            return "шт.";
        }
        return "Vnt";
    }
    return s;
}
