import { TemplateList } from "../types/TemplateList";
import { api } from "./api";

export const TemplateApi = {

  getAll: () =>
    api.get<TemplateList[]>("/api/templates/all"),

  getTemplatePDF: (path: string) =>
    api.getBlob(`/api/templates/pdf/${path}`),

  createTemplate: (file: File, directory: string) => {
    const form = new FormData();
    form.append("template", file);
    form.append("directory", directory);
    return api.post<{ status: string }>("/api/template/create", form);
  },

  createDocument: (document: Document) =>
    api.postBlob("/api/template/fillFile", document),

};