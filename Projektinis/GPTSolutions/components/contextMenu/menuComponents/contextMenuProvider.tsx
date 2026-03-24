"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

export type ContextMenuItem = {
  id: string;
  label: string;
  onClick?: () => void;
  disabled?: boolean;
};

type ContextMenuState = {
  open: boolean;
  x: number;
  y: number;
  items: ContextMenuItem[];
};

type ContextMenuContextValue = {
  menu: ContextMenuState;
  openMenu: (params: {
    x: number;
    y: number;
    items: ContextMenuItem[];
  }) => void;
  openMenuFromEvent: (
    e: React.MouseEvent,
    items: ContextMenuItem[]
  ) => void;
  closeMenu: () => void;
};

const ContextMenuContext = createContext<ContextMenuContextValue | null>(null);

export function ContextMenuProvider({
  children,
}: {
  children: ReactNode;
}) {
  const [menu, setMenu] = useState<ContextMenuState>({
    open: false,
    x: 0,
    y: 0,
    items: [],
  });

  const closeMenu = useCallback(() => {
    setMenu((prev) => ({
      ...prev,
      open: false,
      items: [],
    }));
  }, []);

  const openMenu = useCallback(
    ({
      x,
      y,
      items,
    }: {
      x: number;
      y: number;
      items: ContextMenuItem[];
    }) => {
      setMenu({
        open: true,
        x,
        y,
        items,
      });
    },
    []
  );

  const openMenuFromEvent = useCallback(
    (e: React.MouseEvent, items: ContextMenuItem[]) => {
      e.preventDefault();
      e.stopPropagation();

      setMenu({
        open: true,
        x: e.clientX,
        y: e.clientY,
        items,
      });
    },
    []
  );

  useEffect(() => {
    function handleWindowClick() {
      closeMenu();
    }

    function handleEscape(e: KeyboardEvent) {
      if (e.key === "Escape") {
        closeMenu();
      }
    }

    function handleScroll() {
      closeMenu();
    }

    window.addEventListener("click", handleWindowClick);
    window.addEventListener("keydown", handleEscape);
    window.addEventListener("scroll", handleScroll, true);

    return () => {
      window.removeEventListener("click", handleWindowClick);
      window.removeEventListener("keydown", handleEscape);
      window.removeEventListener("scroll", handleScroll, true);
    };
  }, [closeMenu]);

  const value = useMemo(
    () => ({
      menu,
      openMenu,
      openMenuFromEvent,
      closeMenu,
    }),
    [menu, openMenu, openMenuFromEvent, closeMenu]
  );

  return (
    <ContextMenuContext.Provider value={value}>
      {children}
    </ContextMenuContext.Provider>
  );
}

export function useContextMenu() {
  const context = useContext(ContextMenuContext);

  if (!context) {
    throw new Error("useContextMenu must be used inside ContextMenuProvider");
  }

  return context;
}