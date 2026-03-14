"use client";

import { TemplateApi } from "@/lib/api/templates";
import { GeneratedFilesApi } from "@/lib/api/generatedFiles";
import { CompanyApi } from "@/lib/api/companies";
import type { Company } from "@/lib/types/Company";
import { useParams } from "next/navigation";
import { useEffect, useState } from "react";
import CompanyCard from "@/components/companyCard/companyCard";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { downloadBlob } from "@/lib/functions/downloadBlob";

type RouteParams = {
    templateId: string;
    userId: string;
    companyId: string;
    directory: string[];
};

export default function Page() {

    const [company, setCompany] = useState<Company | null>(null);
    const [templatePath, setTemplatePath] = useState<string>();
    const params = useParams<RouteParams>();
    const templateId = params.templateId;
    const userId = params.userId;
    const companyId = params.companyId;
    const directory = params.directory;
    const fullPath = directory.join("/");

    useEffect(() => {
        async function getItems() {
          const [templateRes, companyRes] = await Promise.all([
            TemplateApi.getById(templateId),
            CompanyApi.getById(Number(companyId)),
          ]);
          
          setTemplatePath(templateRes);
          setCompany(companyRes);
        }
        getItems();
      }, [templateId, companyId]);

    async function updateDocument() {
        if (!templatePath) return;
        TemplateApi.createDocument(Number(companyId), [templatePath], fullPath.split("/").pop());
    }

    async function downloadDocument() {
        GeneratedFilesApi.getGeneratedWord(fullPath).then((res) => downloadBlob(res));
    }

    async function previewPDF() {
        GeneratedFilesApi.getGeneratedPDF(fullPath).then((res) => setPDFToView(res));
    }

    return (
        <div>
            <p>Šablonas: {templatePath?.split("/").pop() || "Šablonas nerastas, tikriausiai ištrintas"}</p>
            <p>User: {userId}</p>
            <p>Company: {company?.companyName}</p>
            <p>Name: {fullPath.split("/").pop()}</p>

            <button onClick={updateDocument} className="buttons">Atnaujinti</button>
            <button onClick={previewPDF} className="buttons">Peržiūrėti failą</button>
            <button className="buttons" onClick={downloadDocument}>Atsisiusti failą</button>

            <CompanyCard id={35} />
        </div>
    );
}