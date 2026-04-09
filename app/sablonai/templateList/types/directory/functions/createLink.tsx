"use client";

import { useCallback, useMemo, useState, type CSSProperties } from "react";
import { addFileToTree } from "@/app/sablonai/components/utilities/addFile";
import { useCatalogueTree } from "@/app/sablonai/catalogueTreeContext";
import { FilesApi } from "@/lib/api/files";
import { MessageStore } from "@/lib/globalVariables/messages";

export function useCreateLink() {
  const { setCatalogueTree } = useCatalogueTree();
  const [open, setOpen] = useState(false);
  const [name, setName] = useState("Nauja nuoroda");
  const [url, setUrl] = useState("https://");
  const [directory, setDirectory] = useState("");
  const [root, setRoot] = useState("");
  const [submitting, setSubmitting] = useState(false);

  function normalizeWebsiteUrl(raw: string): string | null {
    const trimmed = raw.trim();
    if (!trimmed) return null;

    const withScheme = /^https?:\/\//i.test(trimmed) ? trimmed : `https://${trimmed}`;

    try {
      const parsed = new URL(withScheme);
      if ((parsed.protocol !== "http:" && parsed.protocol !== "https:") || !parsed.hostname) {
        return null;
      }
      return parsed.toString();
    } catch {
      return null;
    }
  }

  const closeModal = useCallback(() => {
    setOpen(false);
    setSubmitting(false);
  }, []);

  const openCreateLinkModal = useCallback((path: string, fileType: string) => {
    if (!fileType) return;
    setDirectory(path ?? "");
    setRoot(fileType);
    setName("Nauja nuoroda");
    setUrl("https://");
    setOpen(true);
  }, []);

  async function submitLink() {
    const cleanName = name.trim();
    if (!cleanName) {
      MessageStore.push({
        title: "Neteisingas pavadinimas",
        message: "Įveskite nuorodos pavadinimą",
        backgroundColor: "#e53e3e",
      });
      return;
    }

    const normalizedUrl = normalizeWebsiteUrl(url);
    if (!normalizedUrl) {
      MessageStore.push({
        title: "Neteisinga nuoroda",
        message: "Įveskite pilną svetainės adresą, pvz. https://example.com",
        backgroundColor: "#e53e3e",
      });
      return;
    }

    setSubmitting(true);
    const res = await FilesApi.createLink(cleanName, normalizedUrl, directory, root);
    setSubmitting(false);

    if (res.status !== "SUCCESS" || !res.file) {
      MessageStore.push({
        title: "Nuorodos sukurti nepavyko",
        message: res.error ?? "Nežinoma klaida",
        backgroundColor: "#e53e3e",
      });
      return;
    }

    setCatalogueTree((prev) => addFileToTree(prev, directory, res.file));
    closeModal();
  }

  const linkModal = useMemo(
    () =>
      open ? (
        <div style={backdropStyle} onClick={closeModal}>
          <div style={modalStyle} onClick={(e) => e.stopPropagation()}>
            <h3 style={titleStyle}>Nauja nuoroda</h3>
            <input
              type="text"
              value={name}
              onChange={(e) => setName(e.target.value)}
              placeholder="Nuorodos pavadinimas"
              style={inputStyle}
              autoFocus
              disabled={submitting}
            />
            <input
              type="text"
              value={url}
              onChange={(e) => setUrl(e.target.value)}
              placeholder="https://example.com"
              style={inputStyle}
              disabled={submitting}
              onKeyDown={(e) => {
                if (e.key === "Enter") {
                  e.preventDefault();
                  void submitLink();
                }
              }}
            />
            <div style={buttonRowStyle}>
              <button type="button" style={cancelButtonStyle} onClick={closeModal} disabled={submitting}>
                Atšaukti
              </button>
              <button type="button" style={confirmButtonStyle} onClick={() => void submitLink()} disabled={submitting}>
                Sukurti
              </button>
            </div>
          </div>
        </div>
      ) : null,
    [closeModal, name, open, submitting, url]
  );

  return { openCreateLinkModal, linkModal };
}

const backdropStyle: CSSProperties = {
  position: "fixed",
  inset: 0,
  background: "rgba(15, 23, 42, 0.55)",
  display: "flex",
  alignItems: "center",
  justifyContent: "center",
  zIndex: 9999,
  padding: "16px",
};

const modalStyle: CSSProperties = {
  width: "100%",
  maxWidth: "420px",
  background: "#fff",
  borderRadius: "12px",
  boxShadow: "0 12px 30px rgba(0,0,0,0.2)",
  padding: "18px",
  display: "grid",
  gap: "10px",
};

const titleStyle: CSSProperties = {
  margin: 0,
  fontSize: "18px",
  fontWeight: 700,
  color: "#0f172a",
};

const inputStyle: CSSProperties = {
  width: "100%",
  border: "1px solid #cbd5e1",
  borderRadius: "8px",
  padding: "10px 12px",
  fontSize: "14px",
  color: "#0f172a",
};

const buttonRowStyle: CSSProperties = {
  display: "grid",
  gridTemplateColumns: "1fr 1fr",
  gap: "8px",
  marginTop: "4px",
};

const cancelButtonStyle: CSSProperties = {
  border: "1px solid #cbd5e1",
  background: "#fff",
  color: "#334155",
  borderRadius: "8px",
  padding: "10px 12px",
  cursor: "pointer",
};

const confirmButtonStyle: CSSProperties = {
  border: "none",
  background: "#dc2626",
  color: "#fff",
  borderRadius: "8px",
  padding: "10px 12px",
  cursor: "pointer",
};
