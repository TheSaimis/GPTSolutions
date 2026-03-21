import { TemplateList } from "../types/TemplateList";
import { api } from "./api";

export const TemplateApi = {

  getAll: () =>
    api.get<TemplateList[]>("/api/templates/all", { loadingMessage: "Kraunami šablonai..." }),

  getById: (id: string) =>
    api.get<string>(`/api/templates/id/${id}`),

  // getTemplatePDF: (path: string) =>
  //   api.getBlob(`/api/templates/pdf/${path}`, { loadingMessage: "Kraunamas PDF..." }),

  getTemplatesZip: () =>
    api.getBlob("/api/templates/zip", { fallbackFilename: "templates.zip" }),

  createTemplate: (file: File, directory: string) => {
    const form = new FormData();
    form.append("template", file);
    form.append("directory", directory);
    return api.post<{ status: string }>("/api/template/create", form);
  },

  createDocument: (companyId: number, templates: string[], name?: string) =>
    api.postBlob(
      "/api/template/fillFileBulk",
      {
        companyId,
        templates,
        ...(name ? { name } : {})
      },
      { loadingMessage: "Kuriami dokumentai..." }
    ),

  // renameTemplate: (directory: string, name: string) =>
  //   api.post<{ status: string }>("/api/template/rename", { directory, name }),

  // deleteTemplate: (path: string) =>
  //   api.post<{ status: string }>("/api/template/delete", { path }),
};