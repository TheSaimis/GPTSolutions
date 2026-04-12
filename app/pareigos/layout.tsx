import { RequireAdmin } from "@/components/auth/RequireAdmin";

export default function PareigosLayout({ children }: { children: React.ReactNode }) {
  return <RequireAdmin>{children}</RequireAdmin>;
}
