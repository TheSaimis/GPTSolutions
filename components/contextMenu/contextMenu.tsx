// "use client";

// import React, { createContext, useContext, useEffect, useMemo, useRef, useState } from "react";
// import styles from "./contextMenu.module.scss";
// import DirectoriesMenu from "./menuComponents/directories";
// import FileMenu from "./menuComponents/file";

// export type Target =
//   | { kind: "directory"; path: string; name: string }
//   | { kind: "file"; path: string; name: string };

// export type DirAction = "upload" | "new-folder" | "rename" | "delete";
// export type FileAction = "open" | "rename" | "delete" | "download";

// export type ActionEvent =
//   | { target: Extract<Target, { kind: "directory" }>; action: DirAction }
//   | { target: Extract<Target, { kind: "file" }>; action: FileAction };

// type Pos = { x: number; y: number };

// type Api = {
//   open: boolean;
//   target: Target | null;
//   pos: Pos;

//   openMenu: (e: React.MouseEvent, target: Target) => void;
//   closeMenu: () => void;

//   dispatchDir: (action: DirAction) => void;
//   dispatchFile: (action: FileAction) => void;
// };

// const Ctx = createContext<Api | null>(null);

// export function useContextMenu() {
//   const v = useContext(Ctx);
//   if (!v) throw new Error("useContextMenu must be used inside ContextMenuProvider");
//   return v;
// }

// export default function ContextMenuProvider({
//   children,
//   onAction,
// }: {
//   children: React.ReactNode;
//   onAction: (event: ActionEvent) => void;
// }) {
//   const [open, setOpen] = useState(false);
//   const [target, setTarget] = useState<Target | null>(null);
//   const [pos, setPos] = useState<Pos>({ x: 0, y: 0 });
//   const menuRef = useRef<HTMLDivElement | null>(null);

//   const closeMenu = () => {
//     setOpen(false);
//     setTarget(null);
//   };

//   const openMenu = (e: React.MouseEvent, t: Target) => {
//     e.preventDefault();
//     e.stopPropagation();
//     setPos({ x: e.clientX, y: e.clientY });
//     setTarget(t);
//     setOpen(true);
//   };

//   const dispatchDir = (action: DirAction) => {
//     if (target?.kind !== "directory") return;
//     onAction({ target, action });
//     closeMenu();
//   };

//   const dispatchFile = (action: FileAction) => {
//     if (target?.kind !== "file") return;
//     onAction({ target, action });
//     closeMenu();
//   };

//   // close on outside click / esc / scroll / resize
//   useEffect(() => {
//     if (!open) return;

//     const onMouseDown = (e: MouseEvent) => {
//       if (!menuRef.current) return;
//       if (!menuRef.current.contains(e.target as Node)) closeMenu();
//     };
//     const onKeyDown = (e: KeyboardEvent) => {
//       if (e.key === "Escape") closeMenu();
//     };

//     document.addEventListener("mousedown", onMouseDown);
//     document.addEventListener("keydown", onKeyDown);
//     window.addEventListener("scroll", closeMenu, true);
//     window.addEventListener("resize", closeMenu);

//     return () => {
//       document.removeEventListener("mousedown", onMouseDown);
//       document.removeEventListener("keydown", onKeyDown);
//       window.removeEventListener("scroll", closeMenu, true);
//       window.removeEventListener("resize", closeMenu);
//     };
//   }, [open]);

//   const value = useMemo<Api>(
//     () => ({ open, target, pos, openMenu, closeMenu, dispatchDir, dispatchFile }),
//     [open, target, pos]
//   );

//   return (
//     <Ctx.Provider value={value}>
//       {children}

//       {open && target && (
//         <div
//           className={styles.menu}
//           ref={menuRef}
//           style={{ left: pos.x, top: pos.y }}
//           onContextMenu={(e) => e.preventDefault()}
//         >
//           <div className={`${styles.menuItems} ${styles.content}`}>
//             {target.kind === "directory" && <DirectoriesMenu onSelect={dispatchDir} />}
//             {target.kind === "file" && <FileMenu onSelect={dispatchFile} />}
//           </div>
//         </div>
//       )}
//     </Ctx.Provider>
//   );
// }