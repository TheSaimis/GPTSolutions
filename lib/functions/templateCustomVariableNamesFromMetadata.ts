/**
 * OOXML custom savybė `customVariables` (JSON masyvas vardų be ${}) — užpildo backend po įkėlimo / šablono užtikrinimo.
 * Grąžina `null`, jei metaduomenyse nėra lauko (seni šablonai) — tada frontend gali skenuoti failą kaip anksčiau.
 */
export function templateCustomVariableNamesFromMetadata(
  custom: Record<string, unknown> | undefined | null,
): string[] | null {
  if (!custom || typeof custom !== "object") {
    return null;
  }
  const raw = custom.customVariables;
  if (raw === undefined) {
    return null;
  }
  if (Array.isArray(raw)) {
    return raw.filter((x): x is string => typeof x === "string");
  }
  if (typeof raw === "string") {
    try {
      const parsed = JSON.parse(raw) as unknown;
      if (Array.isArray(parsed)) {
        return parsed.filter((x): x is string => typeof x === "string");
      }
    } catch {
      return null;
    }
  }
  return null;
}
