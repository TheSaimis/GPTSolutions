"use client";

import { useMessageStore } from "@/lib/globalVariables/messages";
import Message from "./message";


export default function MessagePanel() {

  const messages = useMessageStore((s) => s.messages);
  const remove = useMessageStore((s) => s.remove);

  return (
    <div style={{ pointerEvents: "none", zIndex: 9998, position: "fixed", width: "100%", height: "100%", display: "flex", flexDirection: "column", padding: "10px", alignItems: "end" }}>
      {messages.map((msg) => (
        <Message key={msg.id} title={msg.title} message={msg.message} backgroundColor={msg?.backgroundColor} />
      ))}
    </div>
  );
}