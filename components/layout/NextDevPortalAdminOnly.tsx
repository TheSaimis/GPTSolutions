"use client";

import { useEffect } from "react";

const HTML_CLASS = "user-is-admin";

/**
 * Next.js injects `<nextjs-portal>` (dev tools / indicators). We only add the class that
 * reveals it when `localStorage.role === ROLE_ADMIN`; see `globals.scss`.
 */
export function NextDevPortalAdminOnly() {
  useEffect(() => {
    const role = localStorage.getItem("role") ?? "";
    if (role === "ROLE_ADMIN") {
      document.documentElement.classList.add(HTML_CLASS);
    }
    return () => {
      document.documentElement.classList.remove(HTML_CLASS);
    };
  }, []);

  return null;
}
