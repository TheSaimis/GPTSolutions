import { render, screen } from "@testing-library/react";
import TemplateDetailPage from "@/app/sablonai/[...template]/page";

jest.mock("next/navigation", () => ({
  useParams: () => ({ template: "sutartis.docx" }),
}));
jest.mock("next/link", () => {
  return ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
});

jest.mock("@/lib/api/companies", () => ({
  CompanyApi: { getAll: jest.fn() },
}));
jest.mock("@/lib/api/templates", () => ({
  TemplateApi: {
    createDocument: jest.fn(),
  },
}));

const CompanyApi = require("@/lib/api/companies").CompanyApi;
const TemplateApi = require("@/lib/api/templates").TemplateApi;
const mockGetAll = CompanyApi.getAll;
const mockCreateDocument = TemplateApi.createDocument;

describe("Template detail page ([...template])", () => {
  beforeEach(() => {
    mockGetAll.mockResolvedValue([
      { id: 1, companyType: "UAB", companyName: "Test" },
    ]);
    mockCreateDocument.mockResolvedValue({
      blob: new Blob(),
      filename: "document.docx",
    });
  });

  it("renders template name in title", async () => {
    render(<TemplateDetailPage />);
    expect(
      await screen.findByRole("heading", { name: "sutartis.docx" })
    ).toBeInTheDocument();
  });

  it("renders subtitle", async () => {
    render(<TemplateDetailPage />);
    expect(
      await screen.findByText("Pasirinkite Įmone ir sugeneruokite dokumentą")
    ).toBeInTheDocument();
  });

  it("renders back link to templates", async () => {
    render(<TemplateDetailPage />);
    const link = await screen.findByText("Grįžti į šablonus");
    expect(link.closest("a")).toHaveAttribute("href", "/sablonai");
  });

  it("renders company selector and create button", async () => {
    render(<TemplateDetailPage />);
    expect(
      await screen.findByRole("button", { name: /Sukurti dokumentą/ })
    ).toBeInTheDocument();
  });

  it("calls CompanyApi.getAll on mount", async () => {
    render(<TemplateDetailPage />);
    await screen.findByText("Grįžti į šablonus");
    expect(mockGetAll).toHaveBeenCalled();
  });
});
