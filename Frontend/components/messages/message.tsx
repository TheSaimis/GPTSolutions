"use client";

import { useEffect, useState } from "react";

type MessageProps = {
  title: string;
  message: string;
  backgroundColor?: string;
  autoCloseMs?: number; // set 0/undefined to disable
};

export default function Message({
  title,
  message,
  backgroundColor,
  autoCloseMs = 7500,
}: MessageProps) {
  const [mounted, setMounted] = useState(false); // for enter animation
  const [closing, setClosing] = useState(false);
  const [visible, setVisible] = useState(true);

  useEffect(() => {
    // trigger enter animation next frame
    const id = requestAnimationFrame(() => setMounted(true));
    return () => cancelAnimationFrame(id);
  }, []);

  function close() {
    if (closing) return;
    setClosing(true);
    setTimeout(() => setVisible(false), 320);
  }

  useEffect(() => {
    if (!autoCloseMs) return;
    const t = setTimeout(close, autoCloseMs);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [autoCloseMs]);

  if (!visible) return null;

  const wrapClass = `toastWrap ${mounted ? "mounted" : ""} ${closing ? "closing" : ""}`;
  const toastClass = `toast ${mounted ? "mounted" : ""} ${closing ? "closing" : ""}`;

  return (
    <>
      <div className={wrapClass}>
        <div className={toastClass} style={{ backgroundColor: backgroundColor ?? "#e53e3e" }}>
          <div className="content">
            <strong className="title">{title}</strong>
            <span className="msg">{message}</span>
          </div>

          <button className="close" onClick={close} aria-label="Close">
            ✕
          </button>
        </div>
      </div>

      <style>{`
        /* Wrapper controls layout (height/margins) */
        .toastWrap {
          overflow: hidden;
          max-height: 0px;
          margin-block: 0px;
          transition: max-height 0.32s ease, margin 0.32s ease;
        }

        /* Enter: expand layout */
        .toastWrap.mounted {
          max-height: 140px; /* must be >= toast height; increase if messages can be taller */
          margin-block: 5px;
        }

        /* Exit: collapse layout */
        .toastWrap.closing {
          max-height: 0px;
          margin-block: 0px;
        }

        /* Toast controls visual animation (slide/fade) */
        .toast {
          pointer-events: all;
          display: flex;
          align-items: center;
          gap: 12px;

          color: #fff;
          border-radius: 8px;
          padding: 12px 20px;
          min-width: 300px;
          max-width: 420px;
          box-shadow: 0 4px 16px rgba(0,0,0,0.25);

          opacity: 0;
          transform: translateX(20%);
          transition: transform 0.32s ease, opacity 0.32s ease;
        }

        /* Enter: slide in */
        .toast.mounted {
          opacity: 1;
          transform: translateX(0);
        }

        /* Exit: slide out */
        .toast.closing {
          opacity: 0;
          transform: translateX(20%);
        }

        .content { flex: 1; }
        .title { font-size: 14px; display: block; margin-bottom: 2px; }
        .msg { font-size: 13px; opacity: 0.9; }

        .close {
          background: none;
          border: none;
          color: #fff;
          font-size: 18px;
          cursor: pointer;
          padding: 0 4px;
          opacity: 0.8;
        }
        .close:hover { opacity: 1; }
      `}</style>
    </>
  );
}