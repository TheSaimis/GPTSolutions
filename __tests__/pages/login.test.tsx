import { render, screen, fireEvent } from "@testing-library/react";
import Login from "@/app/prisijungimas/page";

const mockPush = jest.fn();
jest.mock("next/navigation", () => ({
  useRouter: () => ({
    push: mockPush,
  }),
}));

jest.mock("@/lib/api/auth", () => ({
  AuthApi: {
    login: jest.fn().mockResolvedValue("mock-token"),
  },
}));

describe("Login page", () => {
  beforeEach(() => {
    mockPush.mockClear();
    render(<Login />);
  });

  it("renders login heading", () => {
    expect(
      screen.getByText("Įveskite savo prisijungimo duomenis")
    ).toBeInTheDocument();
  });

  it("renders username field", () => {
    expect(screen.getByText("Vartotojo vardas")).toBeInTheDocument();
  });

  it("renders password field", () => {
    expect(screen.getByText("Slaptažodis")).toBeInTheDocument();
  });

  it("renders login button", () => {
    expect(screen.getByText("Prisijungti")).toBeInTheDocument();
  });

  it("allows typing in username field", () => {
    const input = screen.getByPlaceholderText("Vartotojo vardas") as HTMLInputElement;
    fireEvent.change(input, { target: { value: "admin" } });
    expect(input.value).toBe("admin");
  });

  it("allows typing in password field", () => {
    const input = screen.getByPlaceholderText("Slaptažodis") as HTMLInputElement;
    fireEvent.change(input, { target: { value: "secret123" } });
    expect(input.value).toBe("secret123");
  });

  it("calls login API and redirects on submit", async () => {
    const { AuthApi } = await import("@/lib/api/auth");

    const usernameInput = screen.getByPlaceholderText("Vartotojo vardas");
    const passwordInput = screen.getByPlaceholderText("Slaptažodis");
    const button = screen.getByText("Prisijungti");

    fireEvent.change(usernameInput, { target: { value: "admin" } });
    fireEvent.change(passwordInput, { target: { value: "pass" } });
    fireEvent.click(button);

    await screen.findByText("Prisijungti");

    expect(AuthApi.login).toHaveBeenCalledWith("admin", "pass");
    expect(mockPush).toHaveBeenCalledWith("/");
  });
});
