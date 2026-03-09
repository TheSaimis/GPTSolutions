import { render, screen } from "@testing-library/react";
import Header from "@/components/layout/header/header";

jest.mock("next/link", () => {
  return ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
});

jest.mock("next/image", () => {
  return (props: unknown) => (
    <img alt={(props as { alt: string }).alt} src={(props as { src: string }).src} />
  );
});

describe("Header", () => {
  beforeEach(() => {
    render(<Header />);
  });

  it("renders the logo image", () => {
    const logo = screen.getByAltText("Darbo specialistai");
    expect(logo).toBeInTheDocument();
    expect(logo).toHaveAttribute("src", "/logo-red.png");
  });

  it("renders navigation links", () => {
    expect(screen.getByText("Šablonai")).toBeInTheDocument();
    expect(screen.getByText("Įmonės")).toBeInTheDocument();
    expect(screen.getByText("Atsisiusti")).toBeInTheDocument();
  });

  it("logo links to home page", () => {
    const logoLink = screen.getByAltText("Darbo specialistai").closest("a");
    expect(logoLink).toHaveAttribute("href", "/");
  });

  it("nav links point to correct pages", () => {
    expect(screen.getByText("Šablonai").closest("a")).toHaveAttribute("href", "/sablonai");
    expect(screen.getByText("Įmonės").closest("a")).toHaveAttribute("href", "/imones");
    expect(screen.getByText("Atsisiusti").closest("a")).toHaveAttribute("href", "/atsisiusti");
  });

  it("renders inside a header element", () => {
    const { container } = render(<Header />);
    expect(container.querySelector("header")).toBeInTheDocument();
  });
});
