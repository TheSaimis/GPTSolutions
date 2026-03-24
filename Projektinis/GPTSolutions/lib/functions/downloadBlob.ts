export type DownloadResult = {
  blob: Blob;
  filename: string;
};

export function downloadBlob(file: DownloadResult) {
  const url = URL.createObjectURL(file.blob);

  const a = document.createElement("a");
  a.href = url;
  a.download = file.filename || "download";
  document.body.appendChild(a);

  a.click();

  requestAnimationFrame(() => {
    setTimeout(() => {
      a.remove();
      URL.revokeObjectURL(url);
    }, 500);
  });
}
