import { render, screen, fireEvent } from "@testing-library/react";
import Home from "@/app/page";

jest.mock("next/link", () => {
  return ({ children, href }: { children: React.ReactNode; href: string }) => (
    <a href={href}>{children}</a>
  );
});

jest.mock("@/lib/api/generatedFiles", () => ({
  GeneratedFilesApi: {
    getAll: jest.fn(),
  },
}));

describe("Home page", () => {
  beforeEach(() => {
    Storage.prototype.getItem = jest.fn(() => null);
    render(<Home />);
  });

  it("renders welcome heading", () => {
    expect(screen.getByText("Sveiki atvykę")).toBeInTheDocument();
  });

  it("renders subtitle", () => {
    expect(
      screen.getByText("Pasirinkite veiksmą, kurį norite atlikti šiandien")
    ).toBeInTheDocument();
  });

  it("renders template catalog card", () => {
    expect(screen.getByText("Šablonų katalogas")).toBeInTheDocument();
  });

  it("renders add company card", () => {
    expect(screen.getByText("Pridėti įmonę")).toBeInTheDocument();
  });

  it("renders download catalog button", () => {
    expect(screen.getByText("Atsisiųsti katalogą")).toBeInTheDocument();
  });

  it("template catalog links to /sablonai", () => {
    const link = screen.getByText("Šablonų katalogas").closest("a");
    expect(link).toHaveAttribute("href", "/sablonai");
  });

  it("add company links to /imones", () => {
    const link = screen.getByText("Pridėti įmonę").closest("a");
    expect(link).toHaveAttribute("href", "/imones");
  });

  it("download catalog is a button (not link)", () => {
    const button = screen.getByText("Atsisiųsti katalogą").closest("button");
    expect(button).toBeInTheDocument();
  });

  it("does not show admin card when role is not ROLE_ADMIN", () => {
    expect(screen.queryByText("Pridėti naudotoją")).not.toBeInTheDocument();
  });
});

describe("Home page (admin)", () => {
  it("shows admin card when role is ROLE_ADMIN", () => {
    Storage.prototype.getItem = jest.fn((key: string) =>
      key === "role" ? "ROLE_ADMIN" : null
    );
    render(<Home />);
    expect(screen.getByText("Pridėti naudotoją")).toBeInTheDocument();
  });
});

describe("Home page (download catalog)", () => {
  it("calls GeneratedFilesApi.getAll when Atsisiųsti katalogą clicked", async () => {
    const GeneratedFilesApi = require("@/lib/api/generatedFiles").GeneratedFilesApi;
    GeneratedFilesApi.getAll.mockResolvedValue({
      blob: new Blob(),
      filename: "generated.zip",
    });
    global.URL.createObjectURL = jest.fn(() => "blob:mock");
    global.URL.revokeObjectURL = jest.fn();

    Storage.prototype.getItem = jest.fn(() => null);
    render(<Home />);
    const button = screen.getByText("Atsisiųsti katalogą").closest("button");
    if (button) fireEvent.click(button);
    expect(GeneratedFilesApi.getAll).toHaveBeenCalled();
  });
});

describe("Home page (links)", () => {
  beforeEach(() => {
    Storage.prototype.getItem = jest.fn(() => null);
  });

  it("Kaip naudotis links to /kaip-naudotis", () => {
    render(<Home />);
    const link = screen.getByText("Kaip naudotis?").closest("a");
    expect(link).toHaveAttribute("href", "/kaip-naudotis");
  });

  it("Registruoti įmonę link points to /imones", () => {
    render(<Home />);
    const link = screen.getByText("Registruoti įmonę").closest("a");
    expect(link).toHaveAttribute("href", "/imones");
  });

  it("Peržiūrėti šablonus link points to /sablonai", () => {
    render(<Home />);
    const link = screen.getByText("Peržiūrėti šablonus").closest("a");
    expect(link).toHaveAttribute("href", "/sablonai");
  });
});
