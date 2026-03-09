process.env.NEXT_PUBLIC_BACKEND_API_URL = "http://localhost:8000";

const mockFetch = jest.fn();
global.fetch = mockFetch as typeof fetch;

jest.mock("@/lib/globalVariables/messages", () => ({
  MessageStore: {
    push: jest.fn(),
    remove: jest.fn(),
    clear: jest.fn(),
  },
  useMessageStore: {
    getState: () => ({
      messages: [],
      push: jest.fn(),
      remove: jest.fn(),
      clear: jest.fn(),
    }),
    setState: jest.fn(),
  },
}));

import { api } from "@/lib/api/api";

beforeEach(() => {
  mockFetch.mockClear();
  Storage.prototype.getItem = jest.fn(() => null);
});

describe("API module", () => {
  it("makes GET request with correct URL", async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ data: "test" }),
    });

    const result = await api.get("/api/test");

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/test",
      expect.objectContaining({ method: "GET" })
    );
    expect(result).toEqual({ data: "test" });
  });

  it("makes POST request with JSON body", async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ success: true }),
    });

    await api.post("/api/login", { username: "admin", password: "pass" });

    expect(mockFetch).toHaveBeenCalledWith(
      "http://localhost:8000/api/login",
      expect.objectContaining({
        method: "POST",
        body: JSON.stringify({ username: "admin", password: "pass" }),
      })
    );
  });

  it("includes Content-Type header for JSON POST requests", async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({}),
    });

    await api.post("/api/data", { key: "value" });

    const callHeaders = mockFetch.mock.calls[0][1].headers as Record<string, string>;
    expect(callHeaders["Content-Type"]).toBe("application/json");
  });

  it("includes Authorization header when token exists", async () => {
    Storage.prototype.getItem = jest.fn(() => "test-token");

    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({}),
    });

    await api.get("/api/protected");

    const callHeaders = mockFetch.mock.calls[0][1].headers as Record<string, string>;
    expect(callHeaders.Authorization).toBe("Bearer test-token");
  });

  it("does not include Authorization when no token", async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({}),
    });

    await api.get("/api/public");

    const callHeaders = mockFetch.mock.calls[0][1].headers as Record<string, string>;
    expect(callHeaders.Authorization).toBeUndefined();
  });

  it("throws error on non-ok response", async () => {
    mockFetch.mockResolvedValueOnce({
      ok: false,
      status: 500,
      statusText: "Server Error",
      headers: { get: () => "text/plain" },
      text: async () => "Internal Server Error",
    });

    await expect(api.get("/api/broken")).rejects.toThrow("Internal Server Error");
  });

  it("returns undefined for 204 No Content", async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 204,
      headers: { get: () => null },
    });

    const result = await api.get("/api/empty");
    expect(result).toBeUndefined();
  });

  it("handles blob response type via getBlob", async () => {
    const testBlob = new Blob(["test"], { type: "application/pdf" });
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      headers: {
        get: (name: string) =>
          name === "content-disposition"
            ? 'attachment; filename="test.pdf"'
            : null,
      },
      blob: async () => testBlob,
    });

    const result = await api.getBlob("/api/download");
    expect(result).toHaveProperty("blob");
    expect(result).toHaveProperty("filename", "test.pdf");
  });

  it("handles POST without body", async () => {
    mockFetch.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({ ok: true }),
    });

    const result = await api.post("/api/action");
    expect(result).toEqual({ ok: true });
  });
});
