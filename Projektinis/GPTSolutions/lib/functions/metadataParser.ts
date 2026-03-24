import JSZip from "jszip";

type DocxMetadata = {
  core: Record<string, string>;
  custom: Record<string, string>;
};

export async function readDocxMetadataFromBlob(blob: Blob): Promise<DocxMetadata> {
  const zip = await JSZip.loadAsync(await blob.arrayBuffer());

  const core: Record<string, string> = {};
  const custom: Record<string, string> = {};

  const coreXml = await zip.file("docProps/core.xml")?.async("string");
  if (coreXml) {
    const doc = new DOMParser().parseFromString(coreXml, "application/xml");

    const creator = doc.getElementsByTagName("dc:creator")[0]?.textContent;
    const lastModifiedBy = doc.getElementsByTagName("cp:lastModifiedBy")[0]?.textContent;
    const created = doc.getElementsByTagName("dcterms:created")[0]?.textContent;
    const modified = doc.getElementsByTagName("dcterms:modified")[0]?.textContent;

    if (creator) core.creator = creator;
    if (lastModifiedBy) core.lastModifiedBy = lastModifiedBy;
    if (created) core.created = created;
    if (modified) core.modified = modified;
  }

  const customXml = await zip.file("docProps/custom.xml")?.async("string");
  if (customXml) {
    const doc = new DOMParser().parseFromString(customXml, "application/xml");
    const props = Array.from(doc.getElementsByTagName("property"));

    for (const prop of props) {
      const name = prop.getAttribute("name");
      const value = Array.from(prop.children)[0]?.textContent ?? "";

      if (name) {
        custom[name] = value;
      }
    }
  }

  return { core, custom };
}