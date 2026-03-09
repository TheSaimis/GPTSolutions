import { render, screen } from "@testing-library/react";
import PdfViewer from "@/components/pdfViewer/pdfViewer";

let mockBlob: Blob | null = null;
jest.mock("@/lib/globalVariables/pdfToView", () => ({
  usePDFToView: (selector: (s: { blob: Blob | null }) => unknown) =>
    selector({ blob: mockBlob }),
}));

describe("PdfViewer", () => {
  beforeEach(() => {
    mockBlob = null;
    global.URL.createObjectURL = jest.fn(() => "blob:mock");
    global.URL.revokeObjectURL = jest.fn();
  });

  it("returns null when no blob", () => {
    const { container } = render(<PdfViewer />);
    expect(container.firstChild).toBeNull();
  });

  it("renders iframe when blob is set", () => {
    mockBlob = new Blob(["%PDF-1.4"], { type: "application/pdf" });
    render(<PdfViewer />);
    expect(screen.getByTitle("PDF preview")).toBeInTheDocument();
  });
});
