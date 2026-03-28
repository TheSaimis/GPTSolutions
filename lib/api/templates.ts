import { CustomVariable } from "../types/Company";
import { TemplateList } from "../types/TemplateList";
import { clearCachedCatalogueTree } from "../cache/catalogueTreeCache";
import { clearWordFileCache } from "../cache/wordFileCache";
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


  // createTemplate: (file: File, directory: string) => {
  //   const form = new FormData();
  //   form.append("template", file);
  //   form.append("directory", directory);
  //   return api.post<{ status: string }>("/api/template/create", form);
  // },

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
    clearWordFileCache();
    return result;
  },

  createAPPDocument: (companyId: number) => {
    const result = api.getBlob(
      `/api/risk/export/${companyId}`,
    )
    return result;
  },

  importAapXlsToDb: (file: File, reset = false) => {
    const form = new FormData();
    form.append("file", file);
    form.append("reset", String(reset));
    return api.post<{ status: string; message: string; output?: string }>(
      "/api/risk/import-xls",
      form,
      { loadingMessage: "Importuojamas AAP Excel..." }
    );
  }
  // getTemplatePDF: (path: string) =>
  //   api.getBlob(`/api/templates/pdf/${path}`, { loadingMessage: "Kraunamas PDF..." }),

  // renameTemplate: (directory: string, name: string) =>
  //   api.post<{ status: string }>("/api/template/rename", { directory, name }),

  // deleteTemplate: (path: string) =>
  //   api.post<{ status: string }>("/api/template/delete", { path }),
};