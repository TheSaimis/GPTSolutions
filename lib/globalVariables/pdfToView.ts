import { create } from "zustand";

export interface PDFToViewState {
  blob: Blob | null;
  filename?: string;
}

export const usePDFToView = create<PDFToViewState>(() => ({
  blob: null,
  filename: undefined,
}));

export const setPDFToView = (payload: { blob: Blob; filename?: string }) => {
  usePDFToView.setState({ blob: payload.blob, filename: payload.filename });
};