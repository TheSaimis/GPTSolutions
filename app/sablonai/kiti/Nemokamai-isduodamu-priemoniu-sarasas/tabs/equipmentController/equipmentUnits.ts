export const EQUIPMENT_UNIT_OPTIONS = [
    { value: "vnt", label: "Vnt" },
    { value: "poros", label: "Poros" },
] as const;

export type EquipmentDocLang = "LT" | "EN" | "RU";

export function equipmentUnitLabel(stored?: string, docLang: EquipmentDocLang = "LT"): string {
    const isPoros = stored === "poros";
    if (docLang === "EN") {
        return isPoros ? "Pairs" : "Pcs.";
    }
    if (docLang === "RU") {
        return isPoros ? "Пары" : "шт.";
    }
    return isPoros ? "Poros" : "Vnt";
}
