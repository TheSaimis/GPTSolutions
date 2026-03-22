"use client";

import { useEffect, useRef, useState } from "react";
import styles from "./contextMenu.module.scss";
import { useContextMenu } from "./menuComponents/contextMenuProvider";

export default function ContextMenu() {
  const { menu, closeMenu } = useContextMenu();
  const menuRef = useRef<HTMLDivElement>(null);
  const [position, setPosition] = useState({ x: menu.x, y: menu.y });

  useEffect(() => {
    if (!menu.open) return;

    const element = menuRef.current;
    if (!element) return;

    const rect = element.getBoundingClientRect();

    const x = Math.min(menu.x, window.innerWidth - rect.width - 8);
    const y = Math.min(menu.y, window.innerHeight - rect.height - 8);

    setPosition({
      x: Math.max(8, x),
      y: Math.max(8, y),
    });
  }, [menu.open, menu.x, menu.y, menu.items]);

  if (!menu.open) return null;

  return (
    <div
      ref={menuRef}
      className={styles.menu}
      style={{ left: position.x, top: position.y }}
      onContextMenu={(e) => e.preventDefault()}
      onClick={(e) => e.stopPropagation()}
    >
      {menu.items.map((item) => (
        <button
          key={item.id}
          type="button"
          className={styles.menuItem}
          disabled={item.disabled}
          onClick={() => {
            if (item.disabled) return;
            item.onClick?.();
            closeMenu();
          }}
        >
          {item.label}
        </button>
      ))}
    </div>
  );
}