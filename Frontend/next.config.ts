/** @type {import('next').NextConfig} */
const nextConfig = {
  output: "standalone",
  /** Kai frontendas kreipiasi į santykinį /api/..., Next perduoda į Symfony – nėra cross-origin CORS. */
  async rewrites() {
    const backend =
      process.env.BACKEND_URL ||
      process.env.NEXT_PUBLIC_BACKEND_API_URL ||
      "http://127.0.0.1:8000";
    const base = String(backend).replace(/\/$/, "");
    return [{ source: "/api/:path*", destination: `${base}/api/:path*` }];
  },
};

module.exports = nextConfig;