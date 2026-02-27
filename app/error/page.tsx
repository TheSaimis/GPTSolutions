"use client";

import React, { useEffect, useState } from "react";
import Error from "./error";
import { title } from "process";


export default function ErrorPanel() {

  const [errors, setErrors] = useState([]);

  useEffect(() => {
    setErrors(prevErrors => [...prevErrors, { title: "Klaida", message: "Pavyko prisijungti prie duomenu bazės.", backgroundColor: "Green" }, {title: "Klaida", message: "Pavyko prisijungti prie duomenu bazės."}]);
    
  }, []);

  return (
    <div style={{  position: "fixed", width: "100%", height: "100%", display: "flex", flexDirection: "column", gap: "10px", padding: "10px", alignItems: "end"}}>
      {errors.map((error, index) => (
        <Error key={index} title={error.title} message={error.message} backgroundColor={error?.backgroundColor} />
      ))}
    </div>
  );
}