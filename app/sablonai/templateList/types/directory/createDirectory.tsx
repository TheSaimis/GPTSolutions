"use client";

import InputFieldText from "@/components/inputFields/inputFieldText";
import { useEffect, useState } from "react";

type List = {
    directory?: string
    onUpload: (folderName: string) => void
}

export default function CreateDirectory({ directory, onUpload }: List) {

    useEffect (() => {
        console.log("hi im create direcotry");
    }, []);

    const [folderName, setFolderName] = useState('');

    return (
        <div>
            hi im crate folder
            <InputFieldText value={folderName} onChange={setFolderName}/>
        </div>
    );
}