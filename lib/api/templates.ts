import { CustomVariable } from "../types/Company";
import { TemplateList, TemplateId } from "../types/TemplateList";
import { clearCachedCatalogueTree } from "../cache/catalogueTreeCache";
import { clearWordFileCache } from "../cache/wordFileCache";
import { resolveTemplateIdsFromCache } from "../functions/resolveTemplateIdsFromCache";
import { MessageStore } from "../globalVariables/messages";
import { api, type Json } from "./api";

/** Vienas kelias arba kelias + tik tam šablonui skirti makro (bulk). */
export type BulkTemplateItem =
  | string
  | { path: string; custom?: CustomVariable; replacements?: CustomVariable };

export const TemplateApi = {

  getAll: () =>
    api.get<TemplateList[]>("/api/templates/all", { loadingMessage: "Kraunami šablonai..." }),

  getById: async (inputIds: string[] | string) => {
    const ids = Array.from(
      new Set(
        (Array.isArray(inputIds) ? inputIds : [inputIds])
          .map((id) => String(id).trim())
          .filter(Boolean)
      )
    );

    if (ids.length === 0) return [];

    const cached = resolveTemplateIdsFromCache(ids);
    const cachedIds = new Set(cached.map((item) => item.id));
    const missingIds = ids.filter((id) => !cachedIds.has(id));

    if (missingIds.length === 0) {
      return cached;
    }

    try {
      const remote = await api.post<TemplateId[]>(`/api/templates/id`, { ids: missingIds });
      const byId = new Map<string, TemplateId>();
      [...cached, ...remote].forEach((item) => {
        if (!byId.has(item.id)) {
          byId.set(item.id, item);
        }
      });

      const resolved = ids
        .map((id) => byId.get(id))
        .filter((entry): entry is TemplateId => Boolean(entry));

      if (resolved.length === 0) {
        throw new Error("Nerastas nė vienas šablonas, tikriausiai jie ištrinti.");
      }

      if (resolved.length < ids.length) {
        MessageStore.push({
          title: "Įspėjimas",
          message: "Dalis šablonų nebuvo rasta, tikriausiai jie ištrinti.",
          backgroundColor: "#d69e2e",
        });
      }

      return resolved;
    } catch {
      if (cached.length > 0) {
        MessageStore.push({
          title: "Įspėjimas",
          message: "Dalis šablonų nebuvo rasta, tikriausiai jie ištrinti.",
          backgroundColor: "#d69e2e",
        });
        return cached;
      }
      throw new Error("Nerastas nė vienas šablonas, tikriausiai jie ištrinti.");
    }
  },

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

  createDocument: (
    companyId: number | null | undefined,
    templates: string[] | BulkTemplateItem[],
    custom?: CustomVariable,
    name?: string
  ) => {
    const normalizedTemplates: BulkTemplateItem[] = templates.map((t) =>
      typeof t === "string" ? t : { ...t }
    );
    const body: Record<string, unknown> = {
      ...(companyId != null && companyId > 0 ? { companyId } : {}),
      templates: normalizedTemplates.map((t) => {
        if (typeof t === "string") {
          return t;
        }
        const per = t.custom ?? t.replacements;
        const cleaned =
          per != null
            ? Object.fromEntries(
                Object.entries(per).filter(
                  ([, v]) => typeof v === "string" && v.trim() !== ""
                )
              )
            : {};
        return Object.keys(cleaned).length > 0
          ? { path: t.path, custom: cleaned }
          : t.path;
      }),
      ...(name ? { name } : {}),
    };
    if (custom != null) {
      const cleanedGlobal = Object.fromEntries(
        Object.entries(custom).filter(
          ([, v]) => typeof v === "string" && v.trim() !== ""
        )
      );
      if (Object.keys(cleanedGlobal).length > 0) {
        body.custom = cleanedGlobal;
      }
    }
    const result = api.postBlob("/api/template/fillFileBulk", body as Json, {
      loadingMessage: "Kuriami dokumentai...",
    });
    // if (!result.ok) return result;
    clearCachedCatalogueTree("generated");
    clearWordFileCache();
    return result;
  },

  /**
   * @param signer Optional: parašų laukai Excel faile (pareigos / vardas ir pavardė).
   *               Naudokite POST su JSON, jei reikšmės ilgos ar su specialiais simboliais.
   */
  createAPPDocument: (
    companyId: number,
    signer?: { nameAndSurname?: string; role?: string },
  ) => {
    const hasSigner =
      signer &&
      ((signer.nameAndSurname != null && signer.nameAndSurname.trim() !== "") ||
        (signer.role != null && signer.role.trim() !== ""));
    if (hasSigner) {
      return api.postBlob(`/api/risk/export/${companyId}`, {
        nameAndSurname: signer?.nameAndSurname?.trim() ?? "",
        role: signer?.role?.trim() ?? "",
      });
    }
    return api.getBlob(`/api/risk/export/${companyId}`);
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