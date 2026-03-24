"use client";

// probably not going to be used anywhere since viewing .docx as pdf is easier

import { useEffect, useState } from "react";
import { wordVariables } from "@/lib/types/Company";
import styles from "./wordViewer.module.scss";
import mammoth from "mammoth";

type Props = {
  blob: Blob;
};

export default function WordPreview({ blob }: Props) {
  const [html, setHtml] = useState<string>("");

  useEffect(() => {
    async function convert() {
      const arrayBuffer = await blob.arrayBuffer();
      const result = await mammoth.convertToHtml({ arrayBuffer });
      setHtml(result.value);
    }
    convert();
  }, [blob]);

  return (
    <></>
  );
}