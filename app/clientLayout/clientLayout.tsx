"use client";

import ContextMenuProvider, {
    ActionEvent,
} from "@/components/contextMenu/contextMenu";

import Header from "@/components/layout/header/header";
import Footer from "@/components/layout/footer/footer";
import MessagePanel from "@/components/messages/messagePanel";
import PdfViewer from "@/components/pdfViewer/pdfViewer";
import { useState } from "react";

export default function ClientLayout({ children }: { children: React.ReactNode }) {
    function onAction(ev: ActionEvent) {
      if (ev.target.kind === "directory") {
        if (ev.action === "new-folder") {
          // open create folder modal with ev.target.path
        }
        if (ev.action === "rename") {
          // open rename modal with ev.target.path
        }
        if (ev.action === "delete") {
          // call delete API with ev.target.path
        }
        if (ev.action === "upload") {
          // trigger upload flow
        }
      }
  
      if (ev.target.kind === "file") {
        // file actions...
      }
    }
  
    return (
      <ContextMenuProvider onAction={onAction}>
        <PdfViewer />
        <Header />
        <MessagePanel />
        {children}
        <Footer />
      </ContextMenuProvider>
    );
  }