import { api } from "./api";
import { encodePathForApiUrl } from "../functions/encodePathForApiUrl";
import { TemplateList } from "../types/TemplateList";

/** Atsarginis ZIP pavadinimas, jei CORS neperduoda Content-Disposition */
export function generatedZipFallbackName(): string {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `generated_${y}-${m}-${day}.zip`;
}

export const GeneratedFilesApi = {
    getAllZip: () =>
        api.getBlob("/api/generated/all/zip", {
            fallbackFilename: generatedZipFallbackName(),
        }),
    getAll: () => api.get<TemplateList[]>("/api/generated",),
    getGeneratedPDF: (path: string) =>
        api.getBlob(`/api/generated/pdf/${encodePathForApiUrl(path)}`, { loadingMessage: "Kraunamas PDF..." }),
    getGeneratedWord: (path: string) =>
        api.getBlob(`/api/generated/file/${encodePathForApiUrl(path)}`, { loadingMessage: "Kraunamas Word..." }),

    // /api/generated/file/{path}
}