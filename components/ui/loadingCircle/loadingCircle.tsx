import React from "react";

export default function LoadingCircle() {
  return (
    <div style={styles.container}>
      <div style={styles.spinner}></div>
    </div>
  );
}

const styles: Record<string, React.CSSProperties> = {
  container: {
    display: "flex",
    justifyContent: "center",
    alignItems: "center",
  },
  spinner: {
    width: "32px",
    height: "32px",
    border: "4px solid rgba(0,0,0,0.1)",
    borderTop: "4px solid #000",
    borderRadius: "50%",
    animation: "spin 1s linear infinite",
  },
};

/* Add this once globally (e.g. in index.css)

@keyframes spin {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

*/