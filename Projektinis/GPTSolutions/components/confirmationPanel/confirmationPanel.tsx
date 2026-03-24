"use client";

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useState,
  type ReactNode,
  type CSSProperties,
} from "react";
import type { LucideIcon } from "lucide-react";
import { AlertTriangle } from "lucide-react";

type ConfirmType = "delete" | "warning" | "edit" | "info";

type ConfirmOptions = {
  type?: ConfirmType;
  title: string;
  message?: string;
  confirmText?: string;
  cancelText?: string;
  icon?: LucideIcon;
};

type ConfirmState = ConfirmOptions & {
  open: boolean;
  resolve?: (value: boolean) => void;
};

type ConfirmActionContextType = {
  confirmAction: (options: ConfirmOptions) => Promise<boolean>;
};

const ConfirmActionContext = createContext<ConfirmActionContextType | null>(null);

export function ConfirmActionProvider({ children }: { children: ReactNode }) {
  const [confirmState, setConfirmState] = useState<ConfirmState>({
    open: false,
    type: "warning",
    title: "",
    message: "",
    confirmText: "Patvirtinti",
    cancelText: "Atšaukti",
    icon: undefined,
  });

  const confirmAction = useCallback((options: ConfirmOptions) => {
    return new Promise<boolean>((resolve) => {
      setConfirmState({
        open: true,
        type: options.type ?? "warning",
        title: options.title,
        message: options.message ?? "",
        confirmText: options.confirmText ?? "Patvirtinti",
        cancelText: options.cancelText ?? "Atšaukti",
        icon: options.icon,
        resolve,
      });
    });
  }, []);

  const closeModal = useCallback((result: boolean) => {
    setConfirmState((prev) => {
      prev.resolve?.(result);
      return {
        open: false,
        type: "warning",
        title: "",
        message: "",
        confirmText: "Patvirtinti",
        cancelText: "Atšaukti",
        icon: undefined,
      };
    });
  }, []);

  const value = useMemo(() => ({ confirmAction }), [confirmAction]);

  const getThemeColor = () => {
    switch (confirmState.type) {
      case "delete": return "#EF2629"; // Tavo pagrindinė raudona
      case "info": return "#3B82F6";
      case "edit": return "#F59E0B";
      default: return "#0F172A";
    }
  };

  const Icon = confirmState.icon || AlertTriangle;

  return (
    <ConfirmActionContext.Provider value={value}>
      {children}

      {confirmState.open && (
        <div style={backdropStyle}>
          <div style={modalStyle}>
            <div style={headerStyle}>
              <div style={{
                color: getThemeColor(),
                background: `${getThemeColor()}15`,
                padding: '12px',
                borderRadius: '12px',
                display: 'inline-flex',
                marginBottom: '16px'
              }}>
                <Icon size={28} />
              </div>
              <h3 style={titleStyle}>{confirmState.title}</h3>
            </div>

            {confirmState.message && (
              <p className="whitespace-pre-line" style={messageStyle}>
                {confirmState.message}
              </p>
            )}

            <div style={buttonRowStyle}>
              <button type="button" onClick={() => closeModal(false)} style={cancelButtonStyle}>
                {confirmState.cancelText}
              </button>

              <button
                type="button"
                onClick={() => closeModal(true)}
                style={{ ...confirmButtonStyle, background: getThemeColor() }}
              >
                {confirmState.confirmText}
              </button>
            </div>
          </div>
        </div>
      )}
    </ConfirmActionContext.Provider>
  );
}

export function useConfirmAction() {
  const context = useContext(ConfirmActionContext);
  if (!context) throw new Error("useConfirmAction must be used inside ConfirmActionProvider");
  return context;
}

const backdropStyle: CSSProperties = {
  position: "fixed",
  inset: 0,
  background: "rgba(15, 23, 42, 0.75)",
  display: "flex",
  alignItems: "center",
  justifyContent: "center",
  zIndex: 9999,
  backdropFilter: "blur(6px)",
  padding: "20px",
};

const modalStyle: CSSProperties = {
  background: "#fff",
  borderRadius: "20px",
  padding: "32px",
  width: "100%",
  maxWidth: "400px",
  boxShadow: "0 25px 50px -12px rgba(0, 0, 0, 0.25)",
  textAlign: "center",
};

const headerStyle: CSSProperties = {
  display: "flex",
  flexDirection: "column",
  alignItems: "center",
};

const titleStyle: CSSProperties = {
  margin: 0,
  fontSize: "1.25rem",
  fontWeight: 700,
  color: "#0F172A",
};

const messageStyle: CSSProperties = {
  margin: "8px 0 0 0",
  color: "#64748B",
  fontSize: "0.95rem",
  lineHeight: "1.5",
};

const buttonRowStyle: CSSProperties = {
  display: "grid",
  gridTemplateColumns: "1fr 1fr",
  gap: "12px",
  marginTop: "32px",
};

const cancelButtonStyle: CSSProperties = {
  padding: "12px",
  borderRadius: "12px",
  border: "1px solid #E2E8F0",
  background: "#fff",
  color: "#64748B",
  fontWeight: "600",
  cursor: "pointer",
};

const confirmButtonStyle: CSSProperties = {
  padding: "12px",
  borderRadius: "12px",
  border: "none",
  color: "#fff",
  fontWeight: "600",
  cursor: "pointer",
};