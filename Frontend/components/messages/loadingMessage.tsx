"use client";

import { useEffect, useState } from "react";
import LoadingCircle from "../ui/loadingCircle/loadingCircle";

type LoadingMessageProps = {
  title?: string;
  message?: string;
  backgroundColor?: string;
};

export default function LoadingMessage({
  title = "Kraunama",
  message,
  backgroundColor,
}: LoadingMessageProps) {
  const [mounted, setMounted] = useState(false);
  const [closing, setClosing] = useState(false);
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, []);

  function close() {
    if (closing) return;
    setClosing(true);
    setTimeout(() => setVisible(false), 280);
  }

  if (!visible) return null;

  const wrapClass = `loadingWrap ${mounted ? "mounted" : ""} ${closing ? "closing" : ""}`;
  const cardClass = `loadingCard ${mounted ? "mounted" : ""} ${closing ? "closing" : ""}`;

  return (
    <>
      <div className={wrapClass}>
        <div
          className={cardClass}
          style={{ backgroundColor: backgroundColor ?? "rgba(255,255,255,0.88)" }}
        >
          <div className="leftAccent" />

          <div className="body">
            <div className="headerRow">
              <div className="spinnerWrap">
                <LoadingCircle />
              </div>

              <div className="textBlock">
                <div className="title">{title || "Kraunama"}</div>
                <div className="message">{message || "Kraunama..."}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <style>{`
        .loadingWrap {
          overflow: hidden;
          max-height: 0;
          opacity: 0;
          margin: 0;
          transition:
            max-height 0.28s ease,
            margin 0.28s ease,
            opacity 0.2s ease;
        }

        .loadingWrap.mounted {
          max-height: 160px;
          margin: 8px 0;
          opacity: 1;
        }

        .loadingWrap.closing {
          max-height: 0;
          margin: 0;
          opacity: 0;
        }

        .loadingCard {
          position: relative;
          display: flex;
          align-items: stretch;
          min-width: 320px;
          max-width: 460px;
          border-radius: 18px;
          overflow: hidden;

          border: 1px solid rgba(148, 163, 184, 0.18);
          box-shadow:
            0 10px 30px rgba(15, 23, 42, 0.10),
            0 2px 8px rgba(15, 23, 42, 0.06);

          backdrop-filter: blur(14px);
          -webkit-backdrop-filter: blur(14px);

          transform: translateY(10px) scale(0.98);
          opacity: 0;
          transition:
            transform 0.28s ease,
            opacity 0.28s ease;
        }

        .loadingCard.mounted {
          transform: translateY(0) scale(1);
          opacity: 1;
        }

        .loadingCard.closing {
          transform: translateY(-6px) scale(0.98);
          opacity: 0;
        }

        .leftAccent {
          width: 4px;
          flex-shrink: 0;
        }

        .body {
          flex: 1;
          padding: 14px 16px;
        }

        .headerRow {
          display: flex;
          align-items: center;
          gap: 12px;
        }

        .spinnerWrap {
          width: 36px;
          height: 36px;
          min-width: 36px;
          border-radius: 999px;
          display: flex;
          align-items: center;
          justify-content: center;
          background: rgba(56, 189, 248, 0.10);
          border: 1px solid rgba(56, 189, 248, 0.16);
        }

        .textBlock {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 3px;
          min-width: 0;
        }

        .title {
          font-size: 14px;
          font-weight: 700;
          color: #0f172a;
          line-height: 1.2;
          letter-spacing: -0.01em;
        }

        .message {
          font-size: 13px;
          line-height: 1.45;
          color: #475569;
          word-break: break-word;
        }

        .closeBtn {
          align-self: flex-start;
          margin: 10px 10px 0 0;
          width: 30px;
          height: 30px;
          border: none;
          border-radius: 999px;
          background: transparent;
          color: #64748b;
          display: flex;
          align-items: center;
          justify-content: center;
          cursor: pointer;
          transition:
            background 0.18s ease,
            color 0.18s ease,
            transform 0.18s ease;
        }

        .closeBtn:hover {
          background: rgba(148, 163, 184, 0.12);
          color: #0f172a;
          transform: scale(1.04);
        }

        .closeBtn:active {
          transform: scale(0.96);
        }

        .closeBtn span {
          font-size: 14px;
          line-height: 1;
        }
      `}</style>
    </>
  );
}