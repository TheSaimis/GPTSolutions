"use client";

import { useEffect, useState } from "react";
import { useParams, useRouter } from "next/navigation";
import { TemplateApi } from "@/lib/api/templates";
import { Document } from "@/lib/types/TemplateList";
import InputFieldNumber from "@/components/inputFields/inputFieldNumber";
import InputFieldDate from "@/components/inputFields/inputFieldDate";
import InputFieldText from "@/components/inputFields/inputFieldText";
import styles from "./page.module.scss";

export default function Login() {

    const { template } = useParams();
    const templatePath = Array.isArray(template) ? template.join("/") : template;
    const fileName = Array.isArray(template) ? template.at(-1) : template;
    const [directory, setDirectory] = useState(decodeURIComponent(templatePath));
    const [documentName, setDocumentName] = useState(fileName);


    const [company, setCompany] = useState("");
    const [code, setCode] = useState("");
    const [role, setRole] = useState("");
    const [instructionDate, setInstructionDate] = useState("");

    useEffect(() => {
        const decoded = decodeURIComponent(templatePath);
        const decodedFileName = decodeURIComponent(fileName);
        setDocumentName(decodedFileName);
        setDirectory(decoded);
        document.title = decodedFileName;
    }, []);

    function downloadBlob(blob: Blob, filename: string) {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement("a");
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
      }
      
      async function createDocument() {
        const document: Document = {company, code, role, instructionDate, directory};
      
        const { blob, filename } = await TemplateApi.createDocument(document);
        downloadBlob(blob, filename);
      }

    return (
        <div className={styles.templates}>
            this is {documentName}

            <div className={styles.mandatoryInputs}>
                <InputFieldText placeholder="Įmonės pavadinimas" value={company} onChange={setCompany} />
                <InputFieldNumber placeholder="Įmonės kodas" value={code} onChange={setCode} regex={/^\d{0,9}$/} />
                <InputFieldText placeholder="Pareigos" value={role} onChange={setRole} />
                <InputFieldDate placeholder="Instrukcijos data" value={instructionDate} onChange={setInstructionDate} />
            </div>

            <button className="buttons" onClick={createDocument}>Sukurti dokumentą</button>
        </div>
    );
}