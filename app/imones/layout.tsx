import { RequireAdmin } from "@/components/auth/RequireAdmin";

export default function ImonesLayout({ children }: { children: React.ReactNode }) {
  return <RequireAdmin>{children}</RequireAdmin>;
}
