import { RequireAdmin } from "@/components/auth/RequireAdmin";

export default function NaudotojaiLayout({ children }: { children: React.ReactNode }) {
  return <RequireAdmin>{children}</RequireAdmin>;
}
