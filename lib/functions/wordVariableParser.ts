import JSZip from "jszip";
import { wordVariables } from "../types/Company";

const allowedVariablesNormalized = new Set(
  wordVariables.map((v) => v.toLowerCase())
);

export async function extractUnknownVariablesFromDocx(blob: Blob): Promise<string[]> {
  const arrayBuffer = await blob.arrayBuffer();
  const zip = await JSZip.loadAsync(arrayBuffer);

  const documentFile = zip.file("word/document.xml");
  if (!documentFile) {
    throw new Error("word/document.xml not found");
  }

  const xml = await documentFile.async("string");

  const text = [...xml.matchAll(/<w:t[^>]*>(.*?)<\/w:t>/g)]
    .map((m) => m[1])
    .join("");

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