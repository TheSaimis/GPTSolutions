import {
  useDirectoryStore,
  DirectoryStore,
} from "@/lib/globalVariables/directoriesToSend";

describe("directoriesToSend store", () => {
  beforeEach(() => {
    useDirectoryStore.setState({ selected: [] });
  });

  it("starts with empty selected", () => {
    expect(useDirectoryStore.getState().selected).toEqual([]);
  });

  it("add adds path to selected", () => {
    DirectoryStore.add("folder/doc.docx");
    expect(useDirectoryStore.getState().selected).toEqual(["folder/doc.docx"]);
    DirectoryStore.add("other/file.docx");
    expect(useDirectoryStore.getState().selected).toContain("folder/doc.docx");
    expect(useDirectoryStore.getState().selected).toContain("other/file.docx");
  });

  it("add does not duplicate same path", () => {
    DirectoryStore.add("a.docx");
    DirectoryStore.add("a.docx");
    expect(useDirectoryStore.getState().selected).toEqual(["a.docx"]);
  });

  it("remove removes path", () => {
    DirectoryStore.add("a.docx");
    DirectoryStore.add("b.docx");
    DirectoryStore.remove("a.docx");
    expect(useDirectoryStore.getState().selected).toEqual(["b.docx"]);
  });

  it("clear empties selected", () => {
    DirectoryStore.add("a.docx");
    DirectoryStore.clear();
    expect(useDirectoryStore.getState().selected).toEqual([]);
  });

  it("isSelected returns true for selected path", () => {
    DirectoryStore.add("x.docx");
    expect(useDirectoryStore.getState().isSelected("x.docx")).toBe(true);
    expect(useDirectoryStore.getState().isSelected("y.docx")).toBe(false);
  });
});
