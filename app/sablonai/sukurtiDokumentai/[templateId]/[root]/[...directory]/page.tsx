"use client";

// bullshit code wont bother fixing

import { TemplateApi } from "@/lib/api/templates";
import { GeneratedFilesApi } from "@/lib/api/generatedFiles";
import { HealthCertificateApi } from "@/lib/api/healthCertificate/certificate";
import { DOCUMENT_TYPES, Metadata } from "@/lib/types/TemplateList";
import { FilesApi } from "@/lib/api/files";
import { CompanyApi } from "@/lib/api/companies";
import { UsersApi } from "@/lib/api/users";
import type { Company, CustomVariable } from "@/lib/types/Company";
import type { User } from "@/lib/types/User";
import { useEffect, useState, use } from "react";
import CompanyCard from "@/components/companyCard/companyCard";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { downloadBlob, DownloadResult } from "@/lib/functions/downloadBlob";
import { FileText } from "lucide-react";
import style from "./page.module.scss";
import Link from "next/link";
import PageBackBar from "@/components/navigation/PageBackBar";

type RouteParams = {
  templateId: string;
  root: string;
  directory: string | string[];
};

type PageProps = { params: Promise<RouteParams> };

export default function Page({ params }: PageProps) {
  const { templateId, directory: directorySeg, root } = use(params);
  const directory = Array.isArray(directorySeg) ? directorySeg : directorySeg != null ? [directorySeg] : [];
  const [modifiedDate, setModifiedDate] = useState("");
  const [company, setCompany] = useState<Company | null>(null);
  const [customVariables, setCustomVariables] = useState<CustomVariable | null>(null);
  const [userId, setUserId] = useState<string | null>(null);
  const [companyId, setCompanyId] = useState<string | null>(null);
  const [templatePath, setTemplatePath] = useState<string>();
  const [user, setUser] = useState<User | null>(null);
  const [currentUser, setCurrentUser] = useState<User | null>(null);
  const [metadata, setMetadata] = useState<Metadata | null>(null);
  /** Iš OOXML custom, kai nėra userId arba API negrąžina naudotojo */
  const [editorFromMetadata, setEditorFromMetadata] = useState("");
  const fullPath = decodeURIComponent(directory.join("/"));

  const hasCompany =
    companyId != null &&
    companyId !== "" &&
    Number.isFinite(Number(companyId)) &&
    Number(companyId) > 0;

  /** Skaitinį 0 JSON neprarandame (0 || "" būtų "") */
  function metaString(v: unknown): string {
    if (v === undefined || v === null) {
      return "";
    }
    return String(v).trim();
  }

  useEffect(() => {
    async function getItems() {
      const res = await FilesApi.getFileData(root, fullPath);
      const custom = res.metadata?.custom ?? {};
      const core = res.metadata?.core ?? {};
      setMetadata(res.metadata ?? null);
      setModifiedDate(
        metaString(custom.modifiedAt) ||
          metaString(core.modified) ||
          metaString(core.created) ||
          ""
      );
      setCompanyId(metaString(custom.companyId));
      setUserId(metaString(custom.userId));
      setCustomVariables(custom.customVariables ?? null);
      setEditorFromMetadata(
        metaString(custom.createdBy) || metaString(core.lastModifiedBy) || ""
      );
    }
    void getItems();
  }, [root, fullPath]);

  useEffect(() => {
    if (!templateId) {
      return;
    }
    void (async () => {
      try {
        const templateRes = await TemplateApi.getById([templateId]);
        setTemplatePath(templateRes[0]?.path);
      } catch {
        setTemplatePath(undefined);
      }
    })();
  }, [templateId]);

  useEffect(() => {
    const id = Number(companyId);
    if (!companyId || !Number.isFinite(id) || id <= 0) {
      setCompany(null);
      return;
    }
    void CompanyApi.getById(id)
      .then((c) => setCompany(c ?? null))
      .catch(() => setCompany(null));
  }, [companyId]);

  useEffect(() => {
    const id = Number(userId);
    if (!userId || !Number.isFinite(id) || id <= 0) {
      setUser(null);
      return;
    }
    void UsersApi.getById(id)
      .then((u) => setUser(u))
      .catch(() => setUser(null));
  }, [userId]);

  useEffect(() => {
    async function loadCurrentUser() {
      const first = localStorage.getItem("name");
      const last = localStorage.getItem("lastName");
      const id = localStorage.getItem("id");
      if (first && last) {
        setCurrentUser({ firstName: first, lastName: last, id: Number(id) });
      }
    }
    loadCurrentUser();
  }, []);

  function nowSqlDate() {
    const d = new Date();
    const pad = (n: number) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
  }

  /** `templateType` custom property, or legacy `documentData.documentType`. */
  function isHealthCertificateMetadata(meta: Metadata | null): boolean {
    const custom = meta?.custom;
    if (!custom) return false;
    if (custom.templateType === DOCUMENT_TYPES.healthCertificate) return true;
    const raw = custom.documentData;
    if (typeof raw !== "string" || raw.trim() === "") return false;
    try {
      const o = JSON.parse(raw) as { documentType?: string };
      return o.documentType === DOCUMENT_TYPES.healthCertificate;
    } catch {
      return false;
    }
  }

  async function updateDocument() {

    let res: DownloadResult | undefined;
    if (isHealthCertificateMetadata(metadata)) {
      res = await HealthCertificateApi.createDocumentFromMetadata(
        Number(companyId),
        metadata?.custom?.documentData as string,
      );
    } else if (templatePath) {
      res = await TemplateApi.createDocument(Number(companyId), [templatePath], {}, fullPath.split("/").pop());
    }
      if (res) setModifiedDate(nowSqlDate());
      setUser(currentUser);
  }

  async function downloadDocument() {
    GeneratedFilesApi.getGeneratedWord(fullPath).then((res) => downloadBlob(res));
  }

  async function previewPDF() {
    FilesApi.getPDF("generated", fullPath).then((res) => setPDFToView(res));
  }

  function formatDate(value?: string | null) {
    if (!value) return "—";

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString("lt-LT", {
      timeZone: "Europe/Vilnius",
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
    });
  }

  return (
    <div className={style.page}>
      <div className={style.pageColumn}>
        <PageBackBar />
        <div
          className={`${style.wrapper} ${!hasCompany ? style.wrapperWithoutCompany : ""}`}
        >
          <div className={style.main}>
            <div className={style.header}>
              <div className={style.iconBox}>
                <FileText size={26} />
              </div>

              <div className={style.titleWrap}>
                <h1 className={style.title}>Dokumentas</h1>
                <p className={style.subtitle}>Peržiūra ir atnaujinimas</p>
              </div>
            </div>

            <div className={style.infoList}>
              <div className={style.infoRow}>
                <span className={style.label}>Šablonas</span>
                <span className={style.value}>
                  {typeof templatePath === "string" ? (
                    templatePath?.split("/")?.pop()
                  ) : (
                    "Šablonas nerastas, tikriausiai ištrintas"
                  )}
                </span>
              </div>

              {user?.id != null ? (
                <Link href={`/naudotojai/${user.id}`} className={style.infoRow}>
                  <span className={style.label}>Dokumentą redagavo</span>
                  <span className={style.value}>
                    {user.firstName} {user.lastName}
                  </span>
                </Link>
              ) : (
                <div className={style.infoRow}>
                  <span className={style.label}>Dokumentą redagavo</span>
                  <span className={style.value}>
                    {editorFromMetadata.trim() !== "" ? editorFromMetadata : "—"}
                  </span>
                </div>
              )}

              {hasCompany && (
                <div className={style.infoRow}>
                  <span className={style.label}>Įmonė</span>
                  <span className={style.value}>
                    {company?.companyName?.trim() || "—"}
                  </span>
                </div>
              )}

              <div className={style.infoRow}>
                <span className={style.label}>Dokumentas</span>
                <span className={style.value}>{fullPath.split("/").pop()}</span>
              </div>

              <div className={style.infoRow}>
                <span className={style.label}>Redaguota</span>
                <span className={style.value}>{formatDate(modifiedDate)}</span>
              </div>
            </div>

            {company?.modifiedAt &&
              modifiedDate &&
              new Date(modifiedDate.replace(" ", "T")) < new Date(company.modifiedAt.replace(" ", "T")) && (
                <div className={style.warning}>
                  <p>Dokumentas yra senesnis už įmonės duomenis, atnaujinkite dokumentą</p>
                  <p>Jeigu atnaujinote įmonės pavadinima, tipą ar kategoriją, šis dokumentas bus irašomas kitame kataloge.</p>
                  <p>Tokiu atveju atnaujintą dokumentą matysite tik naujame kataloge.</p>
                </div>
              )}

            <div className={style.actions}>
              {company?.modifiedAt &&
                modifiedDate &&
                new Date(modifiedDate) < new Date(company.modifiedAt) && (
                  <button onClick={updateDocument} className={style.primaryButton}>
                    Atnaujinti
                  </button>
                )}
              <button onClick={previewPDF} className={style.secondaryButton}>
                Peržiūrėti failą
              </button>

              <button onClick={downloadDocument} className={style.successButton}>
                Atsisiųsti failą
              </button>
            </div>
          </div>

          {hasCompany && (
            <div className={style.side}>
              <h2 className={style.sideTitle}>Įmonės informacija</h2>
              <div className={style.sideBody}>
                <CompanyCard id={Number(companyId)} company={company ?? undefined} />
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}