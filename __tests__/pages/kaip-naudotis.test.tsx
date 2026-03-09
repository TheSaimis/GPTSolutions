import { render, screen } from "@testing-library/react";
import KaipNaudotiPage from "@/app/kaip-naudotis/page";

jest.mock("next/link", () => {
  return ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
});

describe("Kaip naudotis page", () => {
  beforeEach(() => {
    render(<KaipNaudotiPage />);
  });

  it("renders page title", () => {
    expect(screen.getByRole("heading", { level: 1 })).toHaveTextContent(
      "Šablonų naudojimas"
    );
  });

  it("renders subtitle", () => {
    expect(
      screen.getByText("Kaip teisingai užpildyti kintamuosius")
    ).toBeInTheDocument();
  });

  it("renders back link to home", () => {
    const link = screen.getByText("Grįžti į pradžią").closest("a");
    expect(link).toHaveAttribute("href", "/");
  });

  it("renders variables table with headers", () => {
    expect(screen.getByText("Kintamasis")).toBeInTheDocument();
    expect(screen.getByText("Reikšmė")).toBeInTheDocument();
  });

  it("renders at least one variable row", () => {
    expect(screen.getByText("${kompanija}")).toBeInTheDocument();
    expect(screen.getByText("Įmonės pavadinimas")).toBeInTheDocument();
  });

  it("renders info box about template variables", () => {
    expect(
      screen.getByText(/Dokumentų šablonuose naudokite žemiau pateiktus kintamuosius/)
    ).toBeInTheDocument();
  });

  it("renders footer", () => {
    expect(
      screen.getByText("© 2026 Dokumentų Valdymo Sistema")
    ).toBeInTheDocument();
  });
});
