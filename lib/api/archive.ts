import { api } from "./api";
import { TemplateList } from "../types/TemplateList";

/** Atsarginis ZIP pavadinimas, jei CORS neperduoda Content-Disposition */
export function generatedZipFallbackName(): string {
    const d = new Date();
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, "0");
    const day = String(d.getDate()).padStart(2, "0");
    return `generated_${y}-${m}-${day}.zip`;
}

export const ArchiveApi = {
    getAllZip: () =>
        api.getBlob("/api/archive/all/zip", {
            fallbackFilename: generatedZipFallbackName(),
        }),
    getAll: () => api.get<TemplateList[]>("/api/catalogue/roots?root=archive"),
    getArchiveTree: () => api.get<TemplateList[]>("/api/catalogue/roots?root=archive"),

    // getGeneratedPDF: (path: string) =>
    //     api.getBlob(`/api/generated/pdf/${path}`, { loadingMessage: "Kraunamas PDF..." }),
    // getGeneratedWord: (path: string) =>
    //     api.getBlob(`/api/generated/file/${path}`, { loadingMessage: "Kraunamas Word..." }),

    // /api/generated/file/{path}
}