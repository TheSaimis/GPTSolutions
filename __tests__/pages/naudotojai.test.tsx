import { render, screen } from "@testing-library/react";
import NaudotojaiPage from "@/app/naudotojai/page";

jest.mock("next/link", () => {
  return ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
});

describe("Naudotojai page", () => {
  beforeEach(() => {
    render(<NaudotojaiPage />);
  });

  it("renders page title", () => {
    expect(screen.getByRole("heading", { level: 1 })).toHaveTextContent(
      "Pridėti naudotoją"
    );
  });

  it("renders subtitle", () => {
    expect(
      screen.getByText("Užpildykite naudotojo duomenis")
    ).toBeInTheDocument();
  });

  it("renders back link to home", () => {
    const link = screen.getByText("Grįžti į pradžią").closest("a");
    expect(link).toHaveAttribute("href", "/");
  });

  it("renders username field", () => {
    expect(screen.getByPlaceholderText("Vardas")).toBeInTheDocument();
  });

  it("renders rights selector heading", () => {
    const headings = screen.getAllByRole("heading", { level: 2 });
    const teisesHeading = headings.find((h) =>
      h.textContent?.includes("Teises")
    );
    expect(teisesHeading).toBeDefined();
  });

  it("renders password field", () => {
    expect(screen.getByPlaceholderText("Slaptazodis")).toBeInTheDocument();
  });

  it("renders submit button", () => {
    expect(screen.getByText("Išsaugoti")).toBeInTheDocument();
  });

  it("renders rights options", () => {
    expect(screen.getByText("Administratorius")).toBeInTheDocument();
    expect(screen.getByText("Vartotojas")).toBeInTheDocument();
  });
});
