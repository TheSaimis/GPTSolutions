import JSZip from "jszip";
import { wordVariables } from "../types/Company";

const allowedVariablesNormalized = new Set(
  wordVariables.map((v) => v.toLowerCase())
);

function extractVariablesFromText(text: string): string[] {
  const matches = [...text.matchAll(/\$\{([^}]+)\}/g)];

  const result: string[] = [];
  const seen = new Set<string>();

  for (const match of matches) {
    const fullVariable = match[0];
    const variableName = match[1];

    if (!allowedVariablesNormalized.has(fullVariable.toLowerCase())) {
      const dedupeKey = variableName.toLowerCase();

      if (!seen.has(dedupeKey)) {
        seen.add(dedupeKey);
        result.push(variableName);
      }
    }
  }

  return result;
}

function decodeXmlText(xml: string): string {
  return xml
    .replace(/&amp;/g, "&")
    .replace(/&lt;/g, "<")
    .replace(/&gt;/g, ">")
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'");
}

async function extractFromDocx(zip: JSZip): Promise<string[]> {
  const documentFile = zip.file("word/document.xml");
  if (!documentFile) {
    throw new Error("word/document.xml not found");
  }

  const xml = await documentFile.async("string");

  const text = [...xml.matchAll(/<w:t[^>]*>(.*?)<\/w:t>/g)]
    .map((m) => decodeXmlText(m[1]))
    .join("");

  return extractVariablesFromText(text);
}

async function extractFromXlsx(zip: JSZip): Promise<string[]> {
  const collectedText: string[] = [];

  const sharedStringsFile = zip.file("xl/sharedStrings.xml");
  if (sharedStringsFile) {
    const sharedStringsXml = await sharedStringsFile.async("string");

    const sharedTexts = [...sharedStringsXml.matchAll(/<t[^>]*>(.*?)<\/t>/g)]
      .map((m) => decodeXmlText(m[1]));

    collectedText.push(sharedTexts.join(""));
  }

  const worksheetFiles = Object.values(zip.files).filter(
    (file) =>
      !file.dir &&
      /^xl\/worksheets\/sheet\d+\.xml$/i.test(file.name)
  );

  for (const sheetFile of worksheetFiles) {
    const sheetXml = await sheetFile.async("string");

    const inlineTexts = [...sheetXml.matchAll(/<t[^>]*>(.*?)<\/t>/g)]
      .map((m) => decodeXmlText(m[1]))
      .join("");

    collectedText.push(inlineTexts);
  }

  return extractVariablesFromText(collectedText.join(""));
}

export async function extractUnknownVariablesFromOfficeFile(
  blob: Blob
): Promise<string[]> {
  const arrayBuffer = await blob.arrayBuffer();
  const zip = await JSZip.loadAsync(arrayBuffer);

  if (zip.file("word/document.xml")) {
    return extractFromDocx(zip);
  }

  if (zip.file("xl/workbook.xml")) {
    return extractFromXlsx(zip);
  }

  throw new Error("Unsupported file type. Only .docx and .xlsx are supported");
}