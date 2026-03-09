import { CatalougeApi } from "@/lib/api/catalouges";

const mockPost = jest.fn();
jest.mock("@/lib/api/api", () => ({
  api: {
    post: (...args: unknown[]) => mockPost(...args),
  },
}));

describe("CatalougeApi", () => {
  beforeEach(() => {
    mockPost.mockClear();
  });

  it("catalougeCreate calls POST with directory and folderName", async () => {
    mockPost.mockResolvedValue("ok");
    await CatalougeApi.catalougeCreate("Templates", "NewFolder");
    expect(mockPost).toHaveBeenCalledWith(
      "/api/catalogue/template/create",
      { directory: "Templates", folderName: "NewFolder" }
    );
  });

  it("accepts optional errorMessage and errorTitle", async () => {
    mockPost.mockResolvedValue("created");
    await CatalougeApi.catalougeCreate("Dir", "Folder", "Error msg", "Error title");
    expect(mockPost).toHaveBeenCalledWith(
      "/api/catalogue/template/create",
      { directory: "Dir", folderName: "Folder" }
    );
  });
});
