"use client";

import React, { useEffect, useState } from "react";
import Error from "./error";


export default function ErrorPanel() {
  return (
    <div style={{  position: "fixed", width: "100%", height: "100%", display: "flex", flexDirection: "column", gap: "10px", padding: "10px", alignItems: "end"}}>
        <Error title="Klaida" message="Nepavyko prisijungti prie duomenu bazės."/>
        <Error title="Klaida" message="Nepavyko prisijungti prie duomenu bazės."/>
        <Error title="Klaida" message="Nepavyko prisijungti prie duomenu bazės."/>
    </div>
  );
}