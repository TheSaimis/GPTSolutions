import { CustomVariable } from "../types/Company";
import { TemplateList } from "../types/TemplateList";
import { clearCachedCatalogueTree } from "../cache/catalogueTreeCache";
import { api } from "./api";

export const TemplateApi = {

  getAll: () =>
    api.get<TemplateList[]>("/api/templates/all", { loadingMessage: "Kraunami šablonai..." }),

  getById: (id: string) =>
    api.get<string>(`/api/templates/id/${id}`),


  getTemplatesZip: () =>
    api.getBlob("/api/templates/zip"),

  createTemplate: (file: File, directory: string) => {
    const form = new FormData();
    form.append("template", file);
    form.append("directory", directory);
    return api.post<{ status: string }>("/api/template/create", form);
  },

  createDocument: (companyId: number, templates: string[], custom?: CustomVariable, name?: string) => {
    const result = api.postBlob(
      "/api/template/fillFileBulk",
      {
        companyId,
        templates,
        ...(custom ? { custom } : {}),
        ...(name ? { name } : {})
      },
      { loadingMessage: "Kuriami dokumentai..." }
    )
    // if (!result.ok) return result;
    clearCachedCatalogueTree("generated");
    return result;
}
  // getTemplatePDF: (path: string) =>
  //   api.getBlob(`/api/templates/pdf/${path}`, { loadingMessage: "Kraunamas PDF..." }),

  // renameTemplate: (directory: string, name: string) =>
  //   api.post<{ status: string }>("/api/template/rename", { directory, name }),

  // deleteTemplate: (path: string) =>
  //   api.post<{ status: string }>("/api/template/delete", { path }),
};