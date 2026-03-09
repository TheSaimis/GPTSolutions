import { TemplateApi } from "@/lib/api/templates";

const mockGet = jest.fn();
const mockGetBlob = jest.fn();
const mockPost = jest.fn();
const mockPostBlob = jest.fn();
jest.mock("@/lib/api/api", () => ({
  api: {
    get: (...args: unknown[]) => mockGet(...args),
    getBlob: (...args: unknown[]) => mockGetBlob(...args),
    post: (...args: unknown[]) => mockPost(...args),
    postBlob: (...args: unknown[]) => mockPostBlob(...args),
  },
}));

describe("TemplateApi", () => {
  beforeEach(() => {
    mockGet.mockClear();
    mockGetBlob.mockClear();
    mockPost.mockClear();
    mockPostBlob.mockClear();
  });

  it("getAll calls GET /api/templates/all", async () => {
    mockGet.mockResolvedValue([]);
    await TemplateApi.getAll();
    expect(mockGet).toHaveBeenCalledWith("/api/templates/all");
  });

  it("getTemplatePDF calls getBlob with path", async () => {
    mockGetBlob.mockResolvedValue({ blob: new Blob(), filename: "preview.pdf" });
    await TemplateApi.getTemplatePDF("folder/doc.pdf");
    expect(mockGetBlob).toHaveBeenCalledWith("/api/templates/pdf/folder/doc.pdf");
  });

  it("getTemplatesZip calls getBlob", async () => {
    mockGetBlob.mockResolvedValue({ blob: new Blob(), filename: "templates.zip" });
    await TemplateApi.getTemplatesZip();
    expect(mockGetBlob).toHaveBeenCalledWith("/api/templates/zip");
  });

  it("createDocument calls postBlob with companyId and templates", async () => {
    mockPostBlob.mockResolvedValue({ blob: new Blob(), filename: "out.docx" });
    await TemplateApi.createDocument(1, ["a.docx", "b.docx"]);
    expect(mockPostBlob).toHaveBeenCalledWith("/api/template/fillFileBulk", {
      companyId: 1,
      templates: ["a.docx", "b.docx"],
    });
  });

  it("createTemplate sends FormData with file and directory", async () => {
    mockPost.mockResolvedValue({ status: "SUCCESS" });
    const file = new File(["x"], "doc.docx", {
      type: "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    });
    await TemplateApi.createTemplate(file, "Folder");
    expect(mockPost).toHaveBeenCalledWith(
      "/api/template/create",
      expect.any(FormData)
    );
    const formData = mockPost.mock.calls[0][1] as FormData;
    expect(formData.get("template")).toEqual(file);
    expect(formData.get("directory")).toBe("Folder");
  });

  it("renameTemplate calls POST /api/template/rename", async () => {
    mockPost.mockResolvedValue({ status: "OK" });
    await TemplateApi.renameTemplate("old/path", "newName");
    expect(mockPost).toHaveBeenCalledWith("/api/template/rename", {
      directory: "old/path",
      name: "newName",
    });
  });

  it("deleteTemplate calls POST /api/template/delete", async () => {
    mockPost.mockResolvedValue({ status: "OK" });
    await TemplateApi.deleteTemplate("path/to/dir");
    expect(mockPost).toHaveBeenCalledWith("/api/template/delete", {
      directory: "path/to/dir",
    });
  });
});
