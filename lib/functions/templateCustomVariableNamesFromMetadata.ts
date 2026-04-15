/**
 * OOXML custom savybė `customVariables` (JSON masyvas vardų be ${}) — užpildo backend po įkėlimo / šablono užtikrinimo.
 * Grąžina `null`, jei metaduomenyse nėra lauko (seni šablonai) — tada frontend gali skenuoti failą kaip anksčiau.
 */
export type TemplateCustomVariableKind = "constant" | "array";
export type TemplateCustomVariableMap = Record<string, TemplateCustomVariableKind>;

export function templateCustomVariableKindsFromMetadata(
  custom: Record<string, unknown> | undefined | null,
): TemplateCustomVariableMap | null {
  if (!custom || typeof custom !== "object") {
    return null;
  }
  const raw = custom.customVariables;
  if (raw === undefined) {
    return null;
  }
  if (Array.isArray(raw)) {
    const names = raw.filter((x): x is string => typeof x === "string" && x.trim() !== "");
    return Object.fromEntries(names.map((name) => [name, "constant" as const]));
  }
  if (raw && typeof raw === "object") {
    const out: TemplateCustomVariableMap = {};
    for (const [name, kind] of Object.entries(raw as Record<string, unknown>)) {
      const normalizedName = name.trim();
      if (normalizedName === "") {
        continue;
      }
      out[normalizedName] = kind === "array" ? "array" : "constant";
    }
    return out;
  }
  if (typeof raw === "string") {
    try {
      const parsed = JSON.parse(raw) as unknown;
      if (Array.isArray(parsed)) {
        const names = parsed.filter((x): x is string => typeof x === "string" && x.trim() !== "");
        return Object.fromEntries(names.map((name) => [name, "constant" as const]));
      }
      if (parsed && typeof parsed === "object") {
        const out: TemplateCustomVariableMap = {};
        for (const [name, kind] of Object.entries(parsed as Record<string, unknown>)) {
          const normalizedName = name.trim();
          if (normalizedName === "") {
            continue;
          }
          out[normalizedName] = kind === "array" ? "array" : "constant";
        }
        return out;
      }
    } catch {
      return null;
    }
  }
  return null;
}

export function templateCustomVariableNamesFromMetadata(
  custom: Record<string, unknown> | undefined | null,
): string[] | null {
  const kinds = templateCustomVariableKindsFromMetadata(custom);
  if (kinds === null) {
    return null;
  }

  return Object.keys(kinds);
}
