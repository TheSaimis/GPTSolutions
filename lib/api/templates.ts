import { TemplateList } from "../types/TemplateList";
import { api } from "./api";

export const TemplateApi = {

  getAll: () =>
    api.get<TemplateList[]>("/api/templates/all"),

  getTemplatePDF: (path: string) =>
    api.getBlob(`/api/templates/pdf/${path}`),

  getTemplatesZip: () =>
    api.getBlob("/api/templates/zip"),

  createTemplate: (file: File, directory: string) => {
    const form = new FormData();
    form.append("template", file);
    form.append("directory", directory);
    return api.post<{ status: string }>("/api/template/create", form);
  },

  createDocument: (companyId: number, templates: string[]) =>
    api.postBlob("/api/template/fillFileBulk", { companyId, templates }),

};