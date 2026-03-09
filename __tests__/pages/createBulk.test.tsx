import { render, screen } from "@testing-library/react";
import CreateBulkPage from "@/app/sablonai/createBulk/page";

jest.mock("@/lib/api/companies", () => ({
  CompanyApi: { getAll: jest.fn() },
}));
jest.mock("@/lib/api/templates", () => ({
  TemplateApi: {
    createDocument: jest.fn().mockResolvedValue({
      blob: new Blob(),
      filename: "out.docx",
    }),
  },
}));

const CompanyApi = require("@/lib/api/companies").CompanyApi;
const mockGetAll = CompanyApi.getAll;

jest.mock("next/link", () => {
  return ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
});

jest.mock("@/lib/globalVariables/directoriesToSend", () => ({
  useDirectoryStore: jest.fn((selector: (s: { selected: string[] }) => unknown) =>
    selector({ selected: ["folder/doc.docx"] })
  ),
  DirectoryStore: { clear: jest.fn() },
}));

describe("CreateBulk page", () => {
  beforeEach(() => {
    mockGetAll.mockResolvedValue([
      { id: 1, companyType: "UAB", companyName: "Test" },
    ]);
  });

  it("renders back link to templates", async () => {
    render(<CreateBulkPage />);
    const link = await screen.findByText("Grįžti į šablonus");
    expect(link.closest("a")).toHaveAttribute("href", "/sablonai");
  });

  it("renders create document button", async () => {
    render(<CreateBulkPage />);
    expect(
      await screen.findByRole("button", { name: /Sukurti dokumentą/ })
    ).toBeInTheDocument();
  });

  it("calls CompanyApi.getAll on mount", async () => {
    render(<CreateBulkPage />);
    await screen.findByText("Grįžti į šablonus");
    expect(mockGetAll).toHaveBeenCalled();
  });
});
