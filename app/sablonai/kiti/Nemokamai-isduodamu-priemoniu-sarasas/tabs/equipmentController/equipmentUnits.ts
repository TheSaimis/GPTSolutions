export const EQUIPMENT_UNIT_OPTIONS = [
    { value: "vnt", label: "Vnt" },
    { value: "poros", label: "Poros" },
] as const;

export function equipmentUnitLabel(stored?: string): string {
    return stored === "poros" ? "Poros" : "Vnt";
}
