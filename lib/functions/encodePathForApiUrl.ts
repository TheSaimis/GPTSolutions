/**
 * Encodes each segment of a relative file path for use in a URL path
 * (e.g. GET /api/generated/pdf/...). Avoids raw "+" being mishandled by proxies
 * and matches PHP rawurldecode on the server.
 */
export function encodePathForApiUrl(relativePath: string): string {
    const trimmed = relativePath.replace(/^\/+/, "").replace(/\/+$/, "");
    if (trimmed === "") {
        return "";
    }
    return trimmed.split("/").map((segment) => encodeURIComponent(segment)).join("/");
}
