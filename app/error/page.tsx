"use client";

import React, { useEffect, useState } from "react";

const NOTIFICATION_BG_COLOR = "#e53e3e";
const NOTIFICATION_TITLE = "Klaida";
const NOTIFICATION_MESSAGE = "Įvyko klaida";

export default function ErrorPage() {
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    document.title = "Klaida";
    const timer = setTimeout(() => setVisible(false), 7500);
    return () => clearTimeout(timer);
  }, []);

  return (
    <div>
      {visible && (
        <div style={{ position: "fixed", top: "16px", right: "16px", zIndex: 9999, display: "flex", alignItems: "center", gap: "12px", backgroundColor: NOTIFICATION_BG_COLOR, color: "#fff", borderRadius: "8px", padding: "12px 20px", minWidth: "300px", maxWidth: "420px", boxShadow: "0 4px 16px rgba(0,0,0,0.25)", animation: "slideIn 0.3s ease-out" }}>
          <div style={{ flex: 1 }}>
            <strong style={{ fontSize: "14px", display: "block", marginBottom: "2px" }}>{NOTIFICATION_TITLE}</strong>
            <span style={{ fontSize: "13px", opacity: 0.9 }}>{NOTIFICATION_MESSAGE}</span>
          </div>
          <button onClick={() => setVisible(false)} style={{ background: "none", border: "none", color: "#fff", fontSize: "18px", cursor: "pointer", padding: "0 4px", opacity: 0.8 }}>✕</button>
        </div>
      )}
      <style>{`@keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }`}</style>
    </div>
  );
}
