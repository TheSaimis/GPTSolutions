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

// idk what types to think of go crazy ig
type ConfirmType = "delete" | "edit" | "info"; 

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

  const value = useMemo(
    () => ({
      confirmAction,
    }),
    [confirmAction]
  );

  const Icon = confirmState.icon;

  return (
    <ConfirmActionContext.Provider value={value}>
      {children}

      {confirmState.open && (
        <div style={backdropStyle}>
          <div style={modalStyle}>
            <div style={headerStyle}>
              {Icon && <Icon size={20} />}
              <h3 style={titleStyle}>{confirmState.title}</h3>
            </div>

            {confirmState.message && (
              <p style={messageStyle}>{confirmState.message}</p>
            )}

            <div style={buttonRowStyle}>
              <button
                type="button"
                onClick={() => closeModal(false)}
                style={cancelButtonStyle}
              >
                {confirmState.cancelText}
              </button>

              <button
                type="button"
                onClick={() => closeModal(true)}
                style={confirmButtonStyle}
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

  if (!context) {
    throw new Error("useConfirmAction must be used inside ConfirmActionProvider");
  }

  return context;
}

const backdropStyle: CSSProperties = {
  position: "fixed",
  inset: 0,
  background: "rgba(0, 0, 0, 0.4)",
  display: "flex",
  alignItems: "center",
  justifyContent: "center",
  zIndex: 9999,
};

const modalStyle: CSSProperties = {
  background: "#fff",
  borderRadius: "12px",
  padding: "24px",
  minWidth: "320px",
  maxWidth: "480px",
  boxShadow: "0 10px 30px rgba(0,0,0,0.15)",
};

const headerStyle: CSSProperties = {
  display: "flex",
  alignItems: "center",
  gap: "10px",
  marginBottom: "12px",
};

const titleStyle: CSSProperties = {
  margin: 0,
};

const messageStyle: CSSProperties = {
  margin: 0,
};

const buttonRowStyle: CSSProperties = {
  display: "flex",
  justifyContent: "flex-end",
  gap: "10px",
  marginTop: "20px",
};

const cancelButtonStyle: CSSProperties = {
  padding: "8px 14px",
  borderRadius: "8px",
  border: "1px solid #ccc",
  background: "#fff",
  cursor: "pointer",
};

const confirmButtonStyle: CSSProperties = {
  padding: "8px 14px",
  borderRadius: "8px",
  border: "none",
  background: "#111",
  color: "#fff",
  cursor: "pointer",
};