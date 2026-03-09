import { render, screen, fireEvent } from "@testing-library/react";
import TemplatePage from "@/app/sablonai/page";
import { ContextMenuProvider } from "@/components/contextMenu/menuComponents/contextMenuProvider";

jest.mock("@/lib/api/templates", () => ({
  TemplateApi: {
    getAll: jest.fn(),
    getTemplatesZip: jest.fn(),
  },
}));

const TemplateApi = require("@/lib/api/templates").TemplateApi;
const mockGetAll = TemplateApi.getAll;
const mockGetTemplatesZip = TemplateApi.getTemplatesZip;

jest.mock("next/link", () => {
  return ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
});
jest.mock("next/navigation", () => ({
  useRouter: () => ({ push: jest.fn() }),
}));

function renderSablonai() {
  return render(
    <ContextMenuProvider>
      <TemplatePage />
    </ContextMenuProvider>
  );
}

describe("Šablonai page", () => {
  beforeEach(() => {
    mockGetAll.mockResolvedValue([
      { name: "sutartis.docx", type: "file", children: undefined },
      { name: "Folderis", type: "directory", children: [] },
    ]);
    mockGetTemplatesZip.mockResolvedValue({
      blob: new Blob(),
      filename: "templates.zip",
    });
  });

  it("renders page title and subtitle", async () => {
    renderSablonai();
    await screen.findByRole("heading", { name: "Šablonai" });
    expect(
      screen.getByText("Pasirinkite šabloną dokumentui sukurti")
    ).toBeInTheDocument();
  });

  it("calls TemplateApi.getAll on mount", async () => {
    renderSablonai();
    await screen.findByRole("heading", { name: "Šablonai" });
    expect(mockGetAll).toHaveBeenCalled();
  });

  it("renders download catalog button", async () => {
    renderSablonai();
    await screen.findByRole("heading", { name: "Šablonai" });
    expect(
      screen.getByRole("button", { name: /Atsisiųsti šablonų katalogą/ })
    ).toBeInTheDocument();
  });

  it("calls getTemplatesZip when download button clicked", async () => {
    global.URL.createObjectURL = jest.fn(() => "blob:mock");
    global.URL.revokeObjectURL = jest.fn();

    renderSablonai();
    await screen.findByRole("heading", { name: "Šablonai" });

    const btn = screen.getByRole("button", {
      name: /Atsisiųsti šablonų katalogą/,
    });
    fireEvent.click(btn);

    await screen.findByRole("heading", { name: "Šablonai" });
    expect(mockGetTemplatesZip).toHaveBeenCalled();
  });
});
