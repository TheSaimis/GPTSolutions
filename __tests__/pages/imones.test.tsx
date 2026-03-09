import { render, screen } from "@testing-library/react";
import ImonesPage from "@/app/imones/page";

jest.mock("next/link", () => {
  return ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
});

jest.mock("@/lib/api/companies", () => ({
  CompanyApi: {
    companyCreate: jest.fn(),
  },
}));

describe("Imones page", () => {
  beforeEach(() => {
    render(<ImonesPage />);
  });

  it("renders page title", () => {
    expect(screen.getByText("Pridėti įmonę")).toBeInTheDocument();
  });

  it("renders subtitle", () => {
    expect(screen.getByText("Užpildykite įmonės duomenis")).toBeInTheDocument();
  });

  it("renders back link", () => {
    const link = screen.getByText("Grįžti į pradžią").closest("a");
    expect(link).toHaveAttribute("href", "/");
  });

  it("renders company name field", () => {
    expect(screen.getByText("Įmones pavadinimas")).toBeInTheDocument();
  });

  it("renders address field", () => {
    expect(screen.getByText("Adresas")).toBeInTheDocument();
  });

  it("renders company code field", () => {
    expect(screen.getByText("Įmonės kodas")).toBeInTheDocument();
  });

  it("renders first name field", () => {
    expect(screen.getByText("Vardas")).toBeInTheDocument();
  });

  it("renders last name field", () => {
    expect(screen.getByText("Pavardė")).toBeInTheDocument();
  });

  it("renders position field", () => {
    expect(screen.getByText("Pareigos")).toBeInTheDocument();
  });

  it("renders company type selector", () => {
    const headings = screen.getAllByRole("heading", { level: 2 });
    const typeHeading = headings.find((h) => h.textContent?.includes("Įmonės tipas"));
    expect(typeHeading).toBeDefined();
  });

  it("renders submit button", () => {
    expect(screen.getByText("Išsaugoti")).toBeInTheDocument();
  });
});
