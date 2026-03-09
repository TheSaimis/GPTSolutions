import { AuthApi } from "@/lib/api/auth";

const mockPost = jest.fn();
jest.mock("@/lib/api/api", () => ({
  api: { post: (...args: unknown[]) => mockPost(...args) },
}));

describe("AuthApi", () => {
  beforeEach(() => {
    mockPost.mockClear();
    Storage.prototype.setItem = jest.fn();
    Storage.prototype.getItem = jest.fn(() => null);
  });

  it("calls api.post with /api/login and credentials", async () => {
    mockPost.mockResolvedValue({
      token:
        "header." +
        btoa(JSON.stringify({ username: "admin", roles: ["ROLE_ADMIN"] })) +
        ".sig",
    });
    await AuthApi.login("admin", "password123");
    expect(mockPost).toHaveBeenCalledWith("/api/login", {
      username: "admin",
      password: "password123",
    });
  });

  it("stores token and username in localStorage", async () => {
    const token =
      "h." + btoa(JSON.stringify({ username: "john", roles: ["ROLE_USER"] })) + ".s";
    mockPost.mockResolvedValue({ token });
    await AuthApi.login("john", "pass");
    expect(localStorage.setItem).toHaveBeenCalledWith("token", token);
    expect(localStorage.setItem).toHaveBeenCalledWith("username", "john");
    expect(localStorage.setItem).toHaveBeenCalledWith("role", "ROLE_USER");
  });

  it("returns the token", async () => {
    const token = "h." + btoa(JSON.stringify({ username: "x", roles: ["R"] })) + ".s";
    mockPost.mockResolvedValue({ token });
    const result = await AuthApi.login("x", "y");
    expect(result).toBe(token);
  });
});
