"use client";

import { useState } from "react";

type MenuData = {
  directory?: string;
  name?: string;
};

export function useContextMenu() {
  const [open, setOpen] = useState(false);
  const [position, setPosition] = useState({ x: 0, y: 0 });
  const [data, setData] = useState<MenuData | null>(null);

  function openMenu(
    e: React.MouseEvent,
    menuData?: MenuData
  ) {
    e.preventDefault();
    e.stopPropagation();

    setPosition({
      x: e.clientX,
      y: e.clientY,
    });

    setData(menuData ?? null);
    setOpen(true);
  }

  function closeMenu() {
    setOpen(false);
    setData(null);
  }

  return {
    open,
    position,
    data,
    openMenu,
    closeMenu,
  };
}