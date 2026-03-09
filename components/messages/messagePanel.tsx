"use client";

import { useMessageStore } from "@/lib/globalVariables/messages";
import { useLoadingStore } from "@/lib/globalVariables/isLoading";
import Message from "./message";
import LoadingMessage from "./loadingMessage";
import { useEffect } from "react";


export default function MessagePanel() {

  const messages = useMessageStore((s) => s.messages);
  const loading = useLoadingStore((s) => s.loading);
  const loadingMessage = useLoadingStore((s) => s.message);

  return (
    <div style={{ pointerEvents: "none", zIndex: 9998, position: "fixed", width: "100%", height: "100%", display: "flex", flexDirection: "column", padding: "10px", alignItems: "end" }}>
      {loading &&
        <LoadingMessage message={loadingMessage ?? "Kazkas kraunama"} />
      }
      {messages.map((msg) => (
        <Message key={msg.id} title={msg.title} message={msg.message} backgroundColor={msg?.backgroundColor} />
      ))}
    </div>
  );
}