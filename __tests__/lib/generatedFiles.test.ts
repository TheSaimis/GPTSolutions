import { GeneratedFilesApi } from "@/lib/api/generatedFiles";

const mockGetBlob = jest.fn();
jest.mock("@/lib/api/api", () => ({
  api: {
    getBlob: (...args: unknown[]) => mockGetBlob(...args),
  },
}));

describe("GeneratedFilesApi", () => {
  beforeEach(() => {
    mockGetBlob.mockClear();
  });

  it("getAll calls getBlob with correct path", async () => {
    mockGetBlob.mockResolvedValue({
      blob: new Blob(),
      filename: "generated.zip",
    });
    await GeneratedFilesApi.getAll();
    expect(mockGetBlob).toHaveBeenCalledWith("/api/generated/all/zip");
  });

  it("returns blob and filename", async () => {
    const blob = new Blob(["content"]);
    mockGetBlob.mockResolvedValue({ blob, filename: "files.zip" });
    const result = await GeneratedFilesApi.getAll();
    expect(result).toEqual({ blob, filename: "files.zip" });
  });
});
