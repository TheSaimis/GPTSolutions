import { RequireAdmin } from "@/components/auth/RequireAdmin";

export default function ImoneLayout({ children }: { children: React.ReactNode }) {
  return <RequireAdmin>{children}</RequireAdmin>;
}
