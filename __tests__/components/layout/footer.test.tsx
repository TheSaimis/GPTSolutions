import { render, screen } from "@testing-library/react";
import Footer from "@/components/layout/footer/footer";

jest.mock("next/image", () => {
  return (props: unknown) => <img alt={(props as { alt: string }).alt} src={(props as { src: string }).src} />;
});

describe("Footer", () => {
  it("renders copyright text", () => {
    render(<Footer />);
    expect(
      screen.getByText("© 2026 Darbo specialistai. Visos teisės saugomos.")
    ).toBeInTheDocument();
  });

  it("renders inside a footer element", () => {
    const { container } = render(<Footer />);
    expect(container.querySelector("footer")).toBeInTheDocument();
  });

  it("renders the logo image", () => {
    render(<Footer />);
    const logo = screen.getByAltText("Darbo specialistai");
    expect(logo).toBeInTheDocument();
    expect(logo).toHaveAttribute("src", "/logo-red.png");
  });
});
