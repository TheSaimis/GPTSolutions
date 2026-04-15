"use client";

import { useEffect, useState } from "react";
import styles from "./page.module.scss";
import { EquipmentApi } from "@/lib/api/equipment";
import { EquipmentProvider, useEquipment } from "./equipmentContext";

import EquipmentController from "./tabs/equipmentController/equipmentController";
import EquipmentTable from "./tabs/documentController/table";
import EquipmentTemplate from "./tabs/template/template";
import AapEquipmentGroupsSection from "./tabs/aapGroups/aapEquipmentGroupsSection";

function HowToUseSection() {
  const [activeImage, setActiveImage] = useState<{
    src: string;
    alt: string;
  } | null>(null);

  return (
    <>
      <section className={styles.usageCard} aria-label="Kaip naudoti kintamuosius">
        <h2 className={styles.usageTitle}>Kaip naudoti</h2>
        <p className={styles.usageLead}>
          Šiame modulyje Word šablonai pildomi pagal įmonei priskirtas pareigybes ir
          AAP priemones. Žemiau – ką reiškia kiekvienas kintamasis ir kaip jie veikia.
        </p>

        <h3 className={styles.usageSubTitle}>Pagrindiniai AAP lentelės kintamieji</h3>
        <ul className={styles.usageList}>
          <li><code>${"{eilNr}"}</code> – eilutės numeris (1, 2, 3...).</li>
          <li><code>${"{pareigybe}"}</code> – viena pareigybė eilutėje.</li>
          <li><code>${"{pareigybes}"}</code> – pareigybių tekstas (kai kuriuose šablonuose naudojamas vietoje <code>${"{pareigybe}"}</code>).</li>
          <li><code>${"{priemones}"}</code> – priemonės pavadinimas.</li>
          <li><code>${"{terminas}"}</code> – priemonės dėvėjimo / naudojimo terminas.</li>
          <li><code>${"{kiekis}"}</code> – kiekis (kortelėse; jei tuščia, numatytai 1).</li>
          <li><code>${"{vnt}"}</code> – matavimo vienetas (pvz. vnt).</li>
          <li><code>${"{pagrindas}"}</code> – „Pagrindas išduoti“ tekstas kortelėse.</li>
        </ul>

        <h3 className={styles.usageSubTitle}>Kaip veikia eilučių klonavimas</h3>
        <ul className={styles.usageList}>
          <li>Klonavimas vyksta pagal markerio lauką toje pačioje lentelės eilutėje (pvz. <code>${"{pareigybe}"}</code> arba <code>${"{pareigybes}"}</code>).</li>
          <li><code>${"{eilNr}"}</code> pats eilučių nekuria – jis tik rodo numerį jau sugeneruotai eilutei.</li>
          <li>Jei vienoje eilutėje įdėsite <code>${"{eilNr}"}</code> du kartus, abu rodys tą patį tos eilutės numerį.</li>
          <li>Jei klonavimo markerio šablone nėra, reikšmės gali būti pildomos kaip paprastas tekstas, o ne atskiros lentelės eilutės.</li>
        </ul>

        <h3 className={styles.usageSubTitle}>Bendri dokumento kintamieji</h3>
        <ul className={styles.usageList}>
          <li><code>${"{kompanija}"}</code> ir <code>${"{imone}"}</code> – tas pats įmonės pavadinimas.</li>
          <li><code>${"{data}"}</code> – lokalizuota data (pagal dokumento kalbą).</li>
          <li><code>${"{dataSkaitmenimis}"}</code> – data skaitmenimis formatu YYYY-MM-DD.</li>
        </ul>

        <h3 className={styles.usageSubTitle}>AAP Kortelės+Žiniaraščiai pavyzdžiai</h3>
        <div className={styles.examplesGrid}>
          <figure className={styles.exampleCard}>
            <button
              type="button"
              className={styles.exampleButton}
              onClick={() =>
                setActiveImage({
                  src: "/images/aap-template-example.png",
                  alt: "Šablono pavyzdys su kintamaisiais",
                })
              }
            >
              <img
                className={styles.exampleImage}
                src="/images/aap-template-example.png"
                alt="Šablono pavyzdys su kintamaisiais"
              />
            </button>
            <figcaption className={styles.exampleCaption}>
              AAP Kortelės+Žiniaraščiai šablono pavyzdys: kaip atrodo dokumentas su kintamaisiais.
            </figcaption>
          </figure>

          <figure className={styles.exampleCard}>
            <button
              type="button"
              className={styles.exampleButton}
              onClick={() =>
                setActiveImage({
                  src: "/images/aap-generated-example.png",
                  alt: "Sugeneruoto dokumento pavyzdys",
                })
              }
            >
              <img
                className={styles.exampleImage}
                src="/images/aap-generated-example.png"
                alt="Sugeneruoto dokumento pavyzdys"
              />
            </button>
            <figcaption className={styles.exampleCaption}>
              AAP Kortelės+Žiniaraščiai sugeneruoto dokumento pavyzdys: kaip atrodo galutinis rezultatas.
            </figcaption>
          </figure>
        </div>

        <h3 className={styles.usageSubTitle}>AAP sąrašas pavyzdžiai</h3>
        <div className={styles.examplesGrid}>
          <figure className={styles.exampleCard}>
            <button
              type="button"
              className={styles.exampleButton}
              onClick={() =>
                setActiveImage({
                  src: "/images/aap-sarasas-template-example.png",
                  alt: "AAP sąrašo šablono pavyzdys su kintamaisiais",
                })
              }
            >
              <img
                className={styles.exampleImage}
                src="/images/aap-sarasas-template-example.png"
                alt="AAP sąrašo šablono pavyzdys su kintamaisiais"
              />
            </button>
            <figcaption className={styles.exampleCaption}>
              AAP sąrašo šablono pavyzdys: kaip atrodo neužpildytas šablonas su kintamaisiais.
            </figcaption>
          </figure>

          <figure className={styles.exampleCard}>
            <button
              type="button"
              className={styles.exampleButton}
              onClick={() =>
                setActiveImage({
                  src: "/images/aap-sarasas-generated-example.png",
                  alt: "AAP sąrašo sugeneruoto dokumento pavyzdys",
                })
              }
            >
              <img
                className={styles.exampleImage}
                src="/images/aap-sarasas-generated-example.png"
                alt="AAP sąrašo sugeneruoto dokumento pavyzdys"
              />
            </button>
            <figcaption className={styles.exampleCaption}>
              AAP sąrašo sugeneruoto dokumento pavyzdys: kaip atrodo galutinis rezultatas.
            </figcaption>
          </figure>
        </div>

        <h3 className={styles.usageSubTitle}>Kaip veikia grupės (paprastai)</h3>
        <p className={styles.usageLead}>
          Galvokite apie grupę kaip apie „rinkinį“: joje sudedate darbuotojų tipus
          (pareigybes) ir apsaugos priemones. Kai generuojate dokumentą, sistema paima
          būtent šiuos grupės duomenis ir iš jų užpildo lentelės eilutes.
        </p>
        <ul className={styles.usageList}>
          <li><strong>1. Pasirenkate įmonę.</strong> Sistema rodo tik tos įmonės grupes.</li>
          <li><strong>2. Sukuriate grupę</strong> (arba priskiriate jau sukurtą grupę įmonei).</li>
          <li><strong>3. Į grupę pridedate pareigybes</strong> (pvz. dažytojas, montuotojas).</li>
          <li><strong>4. Į tą pačią grupę pridedate priemones</strong> (su terminu, kiekiu, vienetu).</li>
          <li><strong>5. Generuojant dokumentą</strong> lentelės eilutės pildomos pagal grupės pareigybių + priemonių kombinacijas.</li>
          <li><strong>6. ${"{eilNr}"}</strong> tik sunumeruoja jau sugeneruotas eilutes; eilučių jis nekuria.</li>
        </ul>
        <p className={styles.usageLead}>
          Kitaip tariant: jei grupėje nėra pareigybių ar priemonių, dokumente nebus ir
          atitinkamų užpildytų eilučių. Todėl svarbu pirmiausia tvarkingai susidėti grupę,
          o tik tada generuoti galutinį failą.
        </p>

        <h3 className={styles.usageSubTitle}>Kaip veikia priemonės laukai (ką pildo šablone)</h3>
        <ul className={styles.usageList}>
          <li><strong>Priemonės pavadinimas</strong> → pildo <code>${"{priemones}"}</code>.</li>
          <li><strong>Tinkamumo / dėvėjimo terminas</strong> → pildo <code>${"{terminas}"}</code>.</li>
          <li><strong>Mato vienetas</strong> (pvz. vnt., pora) → pildo <code>${"{vnt}"}</code>.</li>
          <li><strong>Kiekis grupėje</strong> (nustatomas grupės priemonės eilutėje) → pildo <code>${"{kiekis}"}</code>.</li>
          <li><strong>Pagrindas (kortelėms)</strong> (laukas „Dokumento kūrimas“ skiltyje) → pildo <code>${"{pagrindas}"}</code>.</li>
          <li><strong>Pareigybė / darbuotojo tipas</strong> (iš grupės „Darbuotojų tipai“) → pildo <code>${"{pareigybe}"}</code> arba <code>${"{pareigybes}"}</code>.</li>
          <li><strong>Eilutės numeris</strong> generuojamas automatiškai → pildo <code>${"{eilNr}"}</code>.</li>
          <li><strong>EN/RU laukai priemonėje</strong> neprivalomi: jei tušti, EN/RU dokumente naudojama LT reikšmė.</li>
        </ul>
      </section>

      {activeImage && (
        <div
          className={styles.lightboxOverlay}
          role="dialog"
          aria-modal="true"
          aria-label="Padidintas paveikslėlis"
          onClick={() => setActiveImage(null)}
        >
          <button
            type="button"
            className={styles.lightboxClose}
            aria-label="Uždaryti"
            onClick={() => setActiveImage(null)}
          >
            ×
          </button>
          <img
            className={styles.lightboxImage}
            src={activeImage.src}
            alt={activeImage.alt}
            onClick={(e) => e.stopPropagation()}
          />
        </div>
      )}
    </>
  );
}

type EquipmentTab = "document" | "groups" | "equipment" | "template" | "howToUse";
const componentMap = {
  document: EquipmentTable,
  groups: AapEquipmentGroupsSection,
  equipment: EquipmentController,
  template: EquipmentTemplate,
  howToUse: HowToUseSection,
} satisfies Record<EquipmentTab, React.ComponentType>;

function EquipmentPageContent() {
  const { setEquipment, setWorkers } = useEquipment();
  const [activeTab, setActiveTab] = useState<EquipmentTab>("document");

  useEffect(() => {
    EquipmentApi.getAll().then(setEquipment).catch(() => undefined);
    import("@/lib/api/workers").then(({ WorkersApi }) => {
      WorkersApi.getAll().then(setWorkers).catch(() => undefined);
    });
  }, [setEquipment, setWorkers]);

  const ActiveComponent = componentMap[activeTab];

  return (
    <div className={styles.workflowShell}>
      <nav className={styles.workflowNav} aria-label="AAP darbo eiga">
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "document" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("document")}
          aria-current={activeTab === "document" ? "page" : undefined}
        >
          Dokumento kūrimas
        </button>
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "groups" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("groups")}
          aria-current={activeTab === "groups" ? "page" : undefined}
        >
          Grupės
        </button>
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "equipment" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("equipment")}
          aria-current={activeTab === "equipment" ? "page" : undefined}
        >
          Apsaugos priemonės
        </button>
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "template" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("template")}
          aria-current={activeTab === "template" ? "page" : undefined}
        >
          Šablonas
        </button>
        <button
          type="button"
          className={`${styles.workflowTab} ${activeTab === "howToUse" ? styles.workflowTabActive : ""}`}
          onClick={() => setActiveTab("howToUse")}
          aria-current={activeTab === "howToUse" ? "page" : undefined}
        >
          Kaip naudoti
        </button>
      </nav>

      <div className={styles.workflowContent}>
        <h1 className={styles.workflowPageTitle}>AAP Kortelės+Žiniaraščiai</h1>
        <ActiveComponent />
      </div>
    </div>
  );
}

export default function Page() {
  return (
    <EquipmentProvider>
      <EquipmentPageContent />
    </EquipmentProvider>
  );
}