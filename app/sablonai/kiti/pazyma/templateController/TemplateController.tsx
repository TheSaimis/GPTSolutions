"use client";

import InputFieldFile from "@/components/inputFields/inputFieldFile";
import { HealthCertificateApi } from "@/lib/api/healthCertificate";
import { downloadBlob } from "@/lib/functions/downloadBlob";
import { setPDFToView } from "@/lib/globalVariables/pdfToView";
import { useState } from "react";
import styles from "../controllers.module.scss";

export default function TemplateController() {
  const [templateFile, setTemplateFile] = useState<File | null>(null);
  const [uploadingTemplate, setUploadingTemplate] = useState(false);
  const [previewingTemplate, setPreviewingTemplate] = useState(false);
  const [downloadingTemplate, setDownloadingTemplate] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function uploadCertificateTemplate() {
    if (!templateFile) return;

    setUploadingTemplate(true);
    setError(null);
    try {
      await HealthCertificateApi.uploadTemplate(templateFile);
      setTemplateFile(null);
    } catch {
      setError("Nepavyko įkelti pažymos šablono.");
    } finally {
      setUploadingTemplate(false);
    }
  }

  async function previewCertificateTemplatePdf() {
    setPreviewingTemplate(true);
    setError(null);
    try {
      const pdf = await HealthCertificateApi.getTemplatePdf();
      setPDFToView(pdf);
    } catch {
      setError("Nepavyko atidaryti pažymos šablono PDF peržiūros.");
    } finally {
      setPreviewingTemplate(false);
    }
  }

  async function downloadCertificateTemplate() {
    setDownloadingTemplate(true);
    setError(null);
    try {
      const file = await HealthCertificateApi.downloadTemplate();
      downloadBlob(file);
    } catch {
      setError("Nepavyko atsisiųsti pažymos šablono.");
    } finally {
      setDownloadingTemplate(false);
    }
  }

  return (
    <div className={styles.controller}>
      <h3 className={styles.title}>Šablonas</h3>
      <p className={styles.subtitle}>Čia galite įkelti, peržiūrėti ir atsisiųsti pažymos šabloną.</p>

      <div className={`${styles.panel} ${styles.templatePanel}`}>
        <div className={styles.formRow}>
          <InputFieldFile
            value={templateFile}
            onChange={setTemplateFile}
            placeholder="Pažymos šablonas (.docx)"
            accept={[".docx"]}
          />
          <button
            type="button"
            className={`${styles.button} ${styles.buttonSecondary}`}
            onClick={uploadCertificateTemplate}
            disabled={!templateFile || uploadingTemplate}
          >
            {uploadingTemplate ? "Įkeliama..." : "Įkelti/atnaujinti šabloną"}
          </button>
        </div>

        <div className={styles.templateActions}>
          <button
            type="button"
            className={`${styles.button} ${styles.buttonGhost}`}
            onClick={previewCertificateTemplatePdf}
            disabled={previewingTemplate}
          >
            {previewingTemplate ? "Atidaroma..." : "Peržiūrėti esamą šabloną (PDF)"}
          </button>
          <button
            type="button"
            className={`${styles.button} ${styles.buttonGhost}`}
            onClick={downloadCertificateTemplate}
            disabled={downloadingTemplate}
          >
            {downloadingTemplate ? "Atsiunčiama..." : "Atsisiųsti esamą šabloną"}
          </button>
        </div>
      </div>

      {error ? <p className={styles.error}>{error}</p> : null}
    </div>
  );
}
