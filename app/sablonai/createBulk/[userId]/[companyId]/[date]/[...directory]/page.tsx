"use client";

// bullshit code wont bother fixing

import { TemplateApi } from "@/lib/api/templates";
import { GeneratedFilesApi } from "@/lib/api/generatedFiles";
import { FilesApi } from "@/lib/api/files";
import { CompanyApi } from "@/lib/api/companies";
import { UsersApi } from "@/lib/api/users";
import type { Company } from "@/lib/types/Company";
import type { User } from "@/lib/types/User";
import { useEffect, useState, use } from "react";
import CompanyCard from "@/components/companyCard/companyCard";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import { FileText } from "lucide-react";
import style from "./page.module.scss";
import Link from "next/link";
import PageBackBar from "@/components/navigation/PageBackBar";

type RouteParams = {
    templateId: string;
    userId: string;
    companyId: string;
    directory: string | string[];
    date: string;
};

type PageProps = { params: Promise<RouteParams> };

export default function Page({ params }: PageProps) {
    const { templateId, userId, companyId, directory: directorySeg, date: dateSeg } = use(params);
    const directory = Array.isArray(directorySeg) ? directorySeg : directorySeg != null ? [directorySeg] : [];

    const [company, setCompany] = useState<Company | null>(null);
    const [templatePath, setTemplatePath] = useState<string>();
    const [user, setUser] = useState<User | null>(null);
    const [currentUser, setCurrentUser] = useState<User | null>(null);
    const [date, setDate] = useState(() => decodeURIComponent(dateSeg));
    const fullPath = decodeURIComponent(directory.join("/"));

    useEffect(() => {
        async function getItems() {
            const [templateRes, companyRes, userRes] = await Promise.all([
                TemplateApi.getById(templateId),
                CompanyApi.getById(Number(companyId)),
                UsersApi.getById(Number(userId)),
            ]);
            setTemplatePath(templateRes[0].path);
            setCompany(companyRes ?? null);
            setUser(userRes);
        }
        getItems();
    }, [templateId, companyId]);

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

    async function updateDocument() {
        if (!templatePath) return;
        const res = await TemplateApi.createDocument(Number(companyId), [templatePath], {}, fullPath.split("/").pop());
        const today = nowSqlDate();
        setUser(currentUser);
        if (res) setDate(today);
    }

    async function downloadDocument() {
        GeneratedFilesApi.getGeneratedWord(fullPath).then((res) => downloadBlob(res));
    }

    async function previewPDF() {
        FilesApi.getPDF("generated", fullPath).then((res) => setPDFToView(res));
    }

    return (
        <div className={style.page}>
          <div className={style.pageColumn}>
            <PageBackBar />
          <div className={style.wrapper}>
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
                    <span className={style.value}>—</span>
                  </div>
                )}
      
                <div className={style.infoRow}>
                  <span className={style.label}>Įmonė</span>
                  <span className={style.value}>{company?.companyName}</span>
                </div>
      
                <div className={style.infoRow}>
                  <span className={style.label}>Dokumentas</span>
                  <span className={style.value}>{fullPath.split("/").pop()}</span>
                </div>
      
                <div className={style.infoRow}>
                  <span className={style.label}>Redaguota</span>
                  <span className={style.value}>{date}</span>
                </div>
              </div>
      
              {company?.modifiedAt &&
                date &&
                new Date(date.replace(" ", "T")) < new Date(company.modifiedAt.replace(" ", "T")) && (
                  <div className={style.warning}>
                    Dokumentas yra senesnis už įmonės duomenis.
                  </div>
                )}
      
              <div className={style.actions}>
                {company?.modifiedAt &&
                  date &&
                  new Date(date.replace(" ", "T")) < new Date(company.modifiedAt.replace(" ", "T")) && (
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
      
            <div className={style.side}>
              <h2 className={style.sideTitle}>Įmonės informacija</h2>
              <div className={style.sideBody}>
                <CompanyCard id={Number(companyId)} />
              </div>
            </div>
          </div>
          </div>
        </div>
      );
}