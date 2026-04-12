"use client";

import { useEffect, useState, type ReactNode } from "react";
import { notFound } from "next/navigation";

/** Client-only: reads `localStorage.role` and shows 404 if not `ROLE_ADMIN`. */
export function RequireAdmin({ children }: { children: ReactNode }) {
  const [ok, setOk] = useState(false);

  useEffect(() => {
    const role = localStorage.getItem("role") ?? "";
    if (role !== "ROLE_ADMIN") {
      notFound();
      return;
    }
    setOk(true);
  }, []);

  if (!ok) {
    return null;
  }

  return <>{children}</>;
}
