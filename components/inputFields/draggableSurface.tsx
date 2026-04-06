"use client";

import { useCallback, useRef, useState, type PointerEvent as ReactPointerEvent } from "react";
import styles from "./styles/draggableSurface.module.scss";

export type DraggableSurfaceProps = {
  children: React.ReactNode;
  /** Called with `true` when a drag starts and `false` when it ends (release / cancel). */
  onDraggingChange?: (isDragging: boolean) => void;
  /**
   * Optional strip/header that initiates dragging. Use this when `children` has buttons or links
   * so they stay clickable without starting a drag.
   */
  dragHandle?: React.ReactNode;
  className?: string;
  /** Applied to the translated layer that wraps the handle (if any) and `children`. */
  contentClassName?: string;
  disabled?: boolean;
  /** Initial pixel offset from the top-left of the surface. */
  defaultOffset?: { x: number; y: number };
};

export default function DraggableSurface({
  children,
  onDraggingChange,
  dragHandle,
  className,
  contentClassName,
  disabled = false,
  defaultOffset = { x: 0, y: 0 },
}: DraggableSurfaceProps) {
  const [offset, setOffset] = useState(defaultOffset);
  const dragSession = useRef<{
    pointerId: number;
    startClient: { x: number; y: number };
    originOffset: { x: number; y: number };
  } | null>(null);

  const setDragging = useCallback(
    (next: boolean) => {
      onDraggingChange?.(next);
    },
    [onDraggingChange],
  );

  const endDrag = useCallback(
    (target: HTMLElement | null, pointerId: number) => {
      if (dragSession.current === null) {
        return;
      }
      dragSession.current = null;
      setDragging(false);
      if (target?.hasPointerCapture?.(pointerId)) {
        try {
          target.releasePointerCapture(pointerId);
        } catch {
          // ignore
        }
      }
    },
    [setDragging],
  );

  const onPointerDown = useCallback(
    (e: ReactPointerEvent<HTMLDivElement>) => {
      if (disabled || e.button !== 0) {
        return;
      }
      e.preventDefault();
      const el = e.currentTarget;
      el.setPointerCapture(e.pointerId);
      dragSession.current = {
        pointerId: e.pointerId,
        startClient: { x: e.clientX, y: e.clientY },
        originOffset: { ...offset },
      };
      setDragging(true);
    },
    [disabled, offset, setDragging],
  );

  const onPointerMove = useCallback((e: ReactPointerEvent<HTMLDivElement>) => {
    const session = dragSession.current;
    if (!session || e.pointerId !== session.pointerId) {
      return;
    }
    const dx = e.clientX - session.startClient.x;
    const dy = e.clientY - session.startClient.y;
    setOffset({
      x: session.originOffset.x + dx,
      y: session.originOffset.y + dy,
    });
  }, []);

  const onPointerUp = useCallback(
    (e: ReactPointerEvent<HTMLDivElement>) => {
      if (dragSession.current?.pointerId !== e.pointerId) {
        return;
      }
      endDrag(e.currentTarget, e.pointerId);
    },
    [endDrag],
  );

  const onPointerCancel = useCallback(
    (e: ReactPointerEvent<HTMLDivElement>) => {
      if (dragSession.current?.pointerId !== e.pointerId) {
        return;
      }
      endDrag(e.currentTarget, e.pointerId);
    },
    [endDrag],
  );

  const pointerProps = {
    onPointerMove,
    onPointerUp,
    onPointerCancel,
    style: { touchAction: "none" as const },
  };

  const useHandle = dragHandle != null;

  return (
    <div className={`${styles.surface} ${className ?? ""}`}>
      <div
        className={`${styles.movable} ${contentClassName ?? ""}`}
        data-drag-mode={useHandle ? "handle" : "full"}
        style={{
          transform: `translate(${offset.x}px, ${offset.y}px)`,
          touchAction: "none",
        }}
        {...(!useHandle
          ? {
              onPointerDown,
              ...pointerProps,
            }
          : {})}
      >
        {useHandle ? (
          <div
            className={styles.dragHandle}
            onPointerDown={onPointerDown}
            {...pointerProps}
          >
            {dragHandle}
          </div>
        ) : null}
        {useHandle ? <div className={styles.body}>{children}</div> : children}
      </div>
    </div>
  );
}
