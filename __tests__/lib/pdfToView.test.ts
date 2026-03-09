import { usePDFToView, setPDFToView } from "@/lib/globalVariables/pdfToView";

describe("pdfToView store", () => {
  beforeEach(() => {
    usePDFToView.setState({ blob: null, filename: undefined });
  });

  it("starts with null blob", () => {
    expect(usePDFToView.getState().blob).toBeNull();
  });

  it("setPDFToView sets blob and optional filename", () => {
    const blob = new Blob(["%PDF"], { type: "application/pdf" });
    setPDFToView({ blob, filename: "preview.pdf" });
    expect(usePDFToView.getState().blob).toBe(blob);
    expect(usePDFToView.getState().filename).toBe("preview.pdf");
  });

  it("setState can clear blob", () => {
    const blob = new Blob();
    setPDFToView({ blob });
    usePDFToView.setState({ blob: null });
    expect(usePDFToView.getState().blob).toBeNull();
  });
});
