"use client";

import { useEffect, useState, useRef } from "react";
import { HelpCircle, Info, Copy, CheckCircle2, X, ZoomIn, Search, ChevronDown, Globe, BookOpen, Layers, Shield, FileSpreadsheet } from "lucide-react";
import PageBackBar from "@/components/navigation/PageBackBar";
import Image from "next/image";
import Link from "next/link";
import styles from "./page.module.scss";

import beforeImg from "./before.png";
import afterImg from "./after.png";

type Kintamasis = {
    kodas: string;
    aprasymas: string;
    pastaba?: string;
};

type Kategorija = {
    pavadinimas: string;
    aprasymas: string;
    kintamieji: Kintamasis[];
};

const kategorijos: Kategorija[] = [
    {
        pavadinimas: "Įmonės duomenys",
        aprasymas: "Pagrindiniai įmonės rekvizitai",
        kintamieji: [
            { kodas: "${kompanija}", aprasymas: "Įmonės pavadinimas", pastaba: 'Alternatyva: ${companyName}' },
            { kodas: "${tipas}", aprasymas: "Įmonės tipas (UAB, AB, MB...)" },
            { kodas: "${tipasPilnas}", aprasymas: "Pilnas kategorijos pavadinimas", pastaba: 'Pvz.: Uždaroji akcinė bendrovė. Taip pat veikia ${TIPASPILNAS}' },
            { kodas: "${adresas}", aprasymas: "Įmonės registracijos adresas" },
            { kodas: "${kodas}", aprasymas: "Įmonės kodas", pastaba: 'Alternatyva: ${code}' },
        ],
    },
    {
        pavadinimas: "Data",
        aprasymas: "Dokumento datos formatai pagal kalbą",
        kintamieji: [
            { kodas: "${data}", aprasymas: "Dokumento data pagal šablono kalbą", pastaba: 'LT: 2026 m. kovo 12 d. | EN: 12 March 2026 | RU: 12 марта 2026 г.' },
            { kodas: "${documentDate}", aprasymas: 'Dokumento data (alternatyva ${data})' },
            { kodas: "${dataSkaitmenimis}", aprasymas: "Data skaitmenimis", pastaba: "Formatas: 2026-03-12" },
        ],
    },
    {
        pavadinimas: "Vadovas",
        aprasymas: "Vadovo duomenys ir pareigos",
        kintamieji: [
            { kodas: "${vadovas}", aprasymas: "Vadovo vardas ir pavardė" },
            { kodas: "${vardas}", aprasymas: "Vadovo vardas" },
            { kodas: "${pavarde}", aprasymas: "Vadovo pavardė" },
            { kodas: "${role}", aprasymas: "Pasirašančio asmens pareigos", pastaba: "EN/RU šablonuose automatiškai verčiama" },
            { kodas: "${lytis}", aprasymas: "Vadovo lytis", pastaba: "LT: Vyras/Moteris | EN: Male/Female | RU: Мужской/Женский" },
        ],
    },
    {
        pavadinimas: "LT linksniai – pareigos",
        aprasymas: "Lietuviški pareigų linksniai (tik LT šablonams)",
        kintamieji: [
            { kodas: "${vadovo}", aprasymas: "Kilmininkas (ko?)", pastaba: 'Pvz.: direktoriaus. Alternatyva: ${vadoves}' },
            { kodas: "${vadovasNom}", aprasymas: "Vardininkas (kas?)", pastaba: "Pvz.: direktorius" },
            { kodas: "${vadovui}", aprasymas: "Naudininkas (kam?)", pastaba: 'Alternatyva: ${vadovei}' },
            { kodas: "${vadovą}", aprasymas: "Galininkas (ką?)", pastaba: 'Alternatyva: ${vadovę}' },
            { kodas: "${vadovu}", aprasymas: "Įnagininkas (kuo?)" },
            { kodas: "${vadove}", aprasymas: "Vietininkas (kur?)", pastaba: 'Alternatyva: ${vadovėje}' },
            { kodas: "${vadovasKreip}", aprasymas: "Šauksmininkas (kreipinys)", pastaba: 'Alternatyva: ${vadovai}' },
        ],
    },
    {
        pavadinimas: "LT linksniai – vardas",
        aprasymas: "Lietuviški vardo linksniai (tik LT šablonams)",
        kintamieji: [
            { kodas: "${vardo}", aprasymas: "Kilmininkas (ko?)", pastaba: 'Alternatyva: ${vardes}' },
            { kodas: "${vardui}", aprasymas: "Naudininkas (kam?)" },
            { kodas: "${vardą}", aprasymas: "Galininkas (ką?)" },
            { kodas: "${vardu}", aprasymas: "Įnagininkas (kuo?)" },
            { kodas: "${vardviet}", aprasymas: "Vietininkas (kur?)" },
            { kodas: "${varde}", aprasymas: "Šauksmininkas" },
        ],
    },
    {
        pavadinimas: "LT linksniai – pavardė",
        aprasymas: "Lietuviški pavardės linksniai (tik LT šablonams)",
        kintamieji: [
            { kodas: "${pavardes}", aprasymas: "Kilmininkas (ko?)", pastaba: 'Alternatyva: ${pavardo}' },
            { kodas: "${pavardui}", aprasymas: "Naudininkas (kam?)" },
            { kodas: "${pavardą}", aprasymas: "Galininkas (ką?)" },
            { kodas: "${pavardu}", aprasymas: "Įnagininkas (kuo?)" },
            { kodas: "${pavardviet}", aprasymas: "Vietininkas (kur?)" },
            { kodas: "${pavardeS}", aprasymas: "Šauksmininkas" },
        ],
    },
];

export default function KaipNaudotiPage() {
    const [searchTerm, setSearchTerm] = useState("");
    const [copiedIndex, setCopiedIndex] = useState<string | null>(null);
    const [selectedImg, setSelectedImg] = useState<any>(null);
    const [openCategories, setOpenCategories] = useState<Set<number>>(new Set([0, 1, 2, 3]));

    const timeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        document.title = "Šablonų instrukcija | Sistema";
    }, []);

    const toggleCategory = (index: number) => {
        setOpenCategories(prev => {
            const next = new Set(prev);
            if (next.has(index)) next.delete(index);
            else next.add(index);
            return next;
        });
    };

    const copyToClipboard = (text: string, key: string) => {
        navigator.clipboard.writeText(text).then(() => {
            setCopiedIndex(key);
            if (timeoutRef.current) clearTimeout(timeoutRef.current);
            timeoutRef.current = setTimeout(() => setCopiedIndex(null), 1500);
        });
    };

    useEffect(() => {
        return () => { if (timeoutRef.current) clearTimeout(timeoutRef.current); };
    }, []);

    const matchesSearch = (item: Kintamasis) =>
        item.kodas.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.aprasymas.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (item.pastaba?.toLowerCase().includes(searchTerm.toLowerCase()) ?? false);

    const filteredKategorijos = kategorijos.map(k => ({
        ...k,
        kintamieji: k.kintamieji.filter(matchesSearch),
    })).filter(k => k.kintamieji.length > 0);

    const totalCount = kategorijos.reduce((sum, k) => sum + k.kintamieji.length, 0);

    return (
        <div className={styles.page}>
            {selectedImg && (
                <div className={styles.modalOverlay} onClick={() => setSelectedImg(null)}>
                    <div className={styles.modalContent} onClick={(e) => e.stopPropagation()}>
                        <button className={styles.closeButton} onClick={() => setSelectedImg(null)}>
                            <X size={32} />
                        </button>
                        <Image src={selectedImg} alt="Padidintas vaizdas" priority />
                    </div>
                </div>
            )}

            <div className={styles.topBar}>
                <PageBackBar />
            </div>

            {/* Instrukcijų sekcija */}
            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><BookOpen size={24} /></div>
                    <div>
                        <h1 className={styles.title}>Kaip sukurti šabloną</h1>
                        <p className={styles.subtitle}>Žingsnis po žingsnio</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.steps}>
                    <div className={styles.step}>
                        <div className={styles.stepNumber}>1</div>
                        <div>
                            <h3>Sukurkite Word dokumentą</h3>
                            <p>Atidarykite Microsoft Word ir sukurkite dokumentą su norimu tekstu ir formatavimu (.docx arba .xlsx).</p>
                        </div>
                    </div>
                    <div className={styles.step}>
                        <div className={styles.stepNumber}>2</div>
                        <div>
                            <h3>Įterpkite kintamuosius</h3>
                            <p>Ten, kur turi atsirasti įmonės duomenys, įrašykite kintamuosius formatu <code>${"${...}"}</code>, pvz.: <code>${"${kompanija}"}</code>, <code>${"${kodas}"}</code>, <code>${"${data}"}</code>.</p>
                        </div>
                    </div>
                    <div className={styles.step}>
                        <div className={styles.stepNumber}>3</div>
                        <div>
                            <h3>Įkelkite šabloną į sistemą</h3>
                            <p>
                                Eikite į <Link href="/sablonai">Šablonų katalogą</Link> → dešiniu pelės mygtuku ant aplanko →{" "}
                                <strong>„Sukurti failą“</strong> ir pasirinkite .docx arba .xlsx failą. Kelis šablonus galite pažymėti medyje ir viršuje spausti{" "}
                                <strong>Kurti dokumentus</strong> — atsidarys masinio kūrimo vedimas.
                            </p>
                        </div>
                    </div>
                    <div className={styles.step}>
                        <div className={styles.stepNumber}>4</div>
                        <div>
                            <h3>Generuokite dokumentus</h3>
                            <p>
                                Atidarykite <Link href="/sablonai/sukurtiDokumentai">Dokumentų katalogą</Link>, pasirinkite šabloną ir užpildykite vedimą (įmonė, data ir kt.) — sistema pakeis kintamuosius tikrais duomenimis. Sukurtus failus rasite tame pačiame kataloge; senesnius galite peržiūrėti{" "}
                                <Link href="/sablonai/archyvas">archyve</Link>.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {/* Kur kas yra sistemoje */}
            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><Layers size={24} /></div>
                    <div>
                        <h2 className={styles.title}>Kur kas yra sistemoje</h2>
                        <p className={styles.subtitle}>Šablonai, dokumentai, specialūs moduliai</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.navGuide}>
                    <div>
                        <h3>Katalogai</h3>
                        <ul>
                            <li>
                                <Link href="/sablonai">Šablonų katalogas</Link> — šablonai dokumentams kurti; viršuje galite atsisiųsti visą katalogą ZIP formatu.
                            </li>
                            <li>
                                <Link href="/sablonai/sukurtiDokumentai">Dokumentų katalogas</Link> — sugeneruoti failai; galima atsisiųsti visą katalogą arba atidaryti{" "}
                                <Link href="/sablonai/archyvas">Sukurtų dokumentų archyvą</Link>.
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3>Paieška ir filtrai</h3>
                        <p>
                            Šablonų ir dokumentų puslapiuose yra paieška. Filtrai (įmonė, tipas, kalba, data ir kt.) plačiame ekrane rodomi šone, o siaurame — atidaromi mygtuku{" "}
                            <strong>Filtrai</strong>. Dokumentų kataloge datos filtras veikia pagal <strong>naujausią</strong> žinomą sukūrimo arba redagavimo datą.
                        </p>
                    </div>
                    <div>
                        <h3>Specialūs dokumentų moduliai</h3>
                        <p>Tai atskiros formos ir vedliai (ne tik paprastas šablono pasirinkimas):</p>
                        <ul>
                            <li>
                                <Link href="/sablonai/kiti/pazyma">Sveikatos tikrinimo pažymos ir kenksmingų faktorių nustatymo pažyma</Link> — vienas modulis (Word šablonai, rizikos, darbuotojų tipai); ne maišomas su AAP priemonių sąrašu.
                            </li>
                            <li>
                                <Link href="/sablonai/kiti/Nemokamai-isduodamu-priemoniu-sarasas">AAP Kortelės+Žiniaraščiai</Link> — Word dokumentai iš jūsų šablonų: sąrašas (pareigybė, priemonė, terminas) ir atskiras kortelių/žiniaraščių šablonas.
                            </li>
                            <li>
                                <Link href="/sablonai/kiti/AAP">AAP</Link> — profesinės rizikos lentelė (Excel).
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {/* Kalbų palaikymas */}
            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><Globe size={24} /></div>
                    <div>
                        <h2 className={styles.title}>Kalbų palaikymas</h2>
                        <p className={styles.subtitle}>LT, EN ir RU šablonai</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.infoBox}>
                    <Info size={20} />
                    <div>
                        <p>Šablono kalba nustatoma automatiškai pagal failo pavadinimą:</p>
                        <ul className={styles.langList}>
                            <li><strong>Lietuvių (LT)</strong> — numatytoji kalba, jei pavadinime nėra EN/RU žymės. Veikia visi linksniai.</li>
                            <li><strong>Anglų (EN)</strong> — failo pavadinime turi būti <code>EN</code>, pvz.: <code>Įsakymas EN.docx</code></li>
                            <li><strong>Rusų (RU)</strong> — failo pavadinime turi būti <code>RU</code>, pvz.: <code>Приказ RU.docx</code></li>
                        </ul>
                        <p>EN/RU šablonuose linksniai neveikia — vietoj jų naudojama pagrindinė forma, o pareigos, tipas ir lytis verčiami automatiškai.</p>
                        <p>
                            <strong>Įmonės forma:</strong> kuriant ar redaguojant įmonę (<Link href="/imones">Pridėti įmonę</Link>, sąraše — redagavimas) galite pasirinkti kalbos mygtukus{" "}
                            <strong>LT</strong>, <strong>EN</strong> arba <strong>RU</strong>. LT rodo visus laukus (kaip anksčiau). EN ir RU palieka tik pagrindinius: pavadinimą, adresą, miestą/rajoną, vardą, pavardę ir pareigas; vardas ir pareigos įrašomi į atitinkamus anglų arba rusų kalbos laukus daugiakalbiams šablonams.
                        </p>
                    </div>
                </div>
            </div>

            {/* Administratoriams */}
            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><Shield size={24} /></div>
                    <div>
                        <h2 className={styles.title}>Administratoriams</h2>
                        <p className={styles.subtitle}>ROLE_ADMIN</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.navGuide}>
                    <p>
                        Administratoriaus paskyroje prieinama <Link href="/admin">administravimo skydelis</Link>: ištrintų naudotojų ir įmonių peržiūra bei <strong>atkūrimas</strong> (kol duomenys dar saugomi), ištrintų failų katalogo peržiūra, audito įrašai. Ištrynimas dažniausiai yra „minkštas“ — įrašas pažymimas ištrintu ir po tam tikro laikotarpio gali būti galutinai pašalintas.
                    </p>
                    <p>
                        Įmonių ir naudotojų <Link href="/imones/sarasas">sąrašuose</Link> bei <Link href="/naudotojai/sarasas">naudotojų sąraše</Link> galite filtruoti aktyvius ir ištrintus įrašus.
                    </p>
                </div>
            </div>

            {/* Kintamųjų sąrašas */}
            <div className={`${styles.card} ${styles.wideCard}`}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><HelpCircle size={24} /></div>
                    <div>
                        <h2 className={styles.title}>Kintamųjų sąrašas</h2>
                        <p className={styles.subtitle}>{totalCount} kintamieji, {kategorijos.length} kategorijos</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.searchWrapper}>
                    <Search size={18} className={styles.searchIcon} />
                    <input
                        type="text"
                        placeholder="Ieškoti kintamojo..."
                        value={searchTerm}
                        onChange={(e) => setSearchTerm(e.target.value)}
                        className={styles.searchInput}
                    />
                </div>

                <div className={styles.categoriesList}>
                    {filteredKategorijos.map((kat, katIndex) => {
                        const originalIndex = kategorijos.findIndex(k => k.pavadinimas === kat.pavadinimas);
                        const isOpen = searchTerm !== "" || openCategories.has(originalIndex);

                        return (
                            <div key={kat.pavadinimas} className={styles.category}>
                                <button
                                    className={`${styles.categoryHeader} ${isOpen ? styles.categoryOpen : ""}`}
                                    onClick={() => toggleCategory(originalIndex)}
                                >
                                    <div>
                                        <span className={styles.categoryTitle}>{kat.pavadinimas}</span>
                                        <span className={styles.categoryCount}>{kat.kintamieji.length}</span>
                                    </div>
                                    <ChevronDown size={18} className={`${styles.chevron} ${isOpen ? styles.chevronOpen : ""}`} />
                                </button>

                                {isOpen && (
                                    <div className={styles.categoryBody}>
                                        <p className={styles.categoryDesc}>{kat.aprasymas}</p>
                                        <table className={styles.varTable}>
                                            <thead>
                                                <tr>
                                                    <th>Kintamasis</th>
                                                    <th>Reikšmė</th>
                                                    <th style={{ width: "40px" }}></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {kat.kintamieji.map((item, i) => {
                                                    const key = `${katIndex}-${i}`;
                                                    return (
                                                        <tr key={key}>
                                                            <td><code>{item.kodas}</code></td>
                                                            <td>
                                                                {item.aprasymas}
                                                                {item.pastaba && <span className={styles.pastaba}>{item.pastaba}</span>}
                                                            </td>
                                                            <td>
                                                                <button onClick={() => copyToClipboard(item.kodas, key)} className={styles.copyButton}>
                                                                    {copiedIndex === key ? <CheckCircle2 size={16} className={styles.successIcon} /> : <Copy size={16} />}
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {/* Kenksmingų faktorių pažyma ir AAP dokumentai */}
            <div className={styles.card}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><FileSpreadsheet size={24} /></div>
                    <div>
                        <h2 className={styles.title}>Kenksmingų faktorių pažyma ir AAP dokumentai</h2>
                        <p className={styles.subtitle}>Atskirti moduliai: sveikatos rizikos, AAP priemonės, AAP Excel</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.navGuide}>
                    <div>
                        <h3>Kenksmingų faktorių nustatymo pažyma (Word, .docx)</h3>
                        <p>
                            Modulis: <Link href="/sablonai/kiti/pazyma">Šablonai → sveikatos ir rizikų pažymos</Link> (skiltis „Dokumentų kūrimas“). Tai <strong>ne</strong> AAP asmeninių apsaugos priemonių sąrašas. Naudojamas <strong>jūsų paruoštas Word šablonas</strong> (įskaitant įkėlimą per modulio skirtuką „Šablonas“). Sistema užpildo įmonės rekvizitus ir lentelę pagal priskirtus darbuotojų tipus bei jų rizikų duomenis.
                        </p>
                        <ul>
                            <li>
                                <strong>Kodėl svarbi 3-ioji lentelės eilutė:</strong> pirmoje eilutėje paprastai būna stulpelių antraštės („Eil. Nr.“, „Pareigybė“ ir t. t.), antroje — stulpelių numeracija ar kita techninė eilutė. <strong>Trečiojoje eilutėje</strong> turi būti kintamieji, pvz.{" "}
                                <code>${"${eilNr}"}</code>, <code>${"${pareigybe}"}</code>, <code>${"${veiksniai}"}</code>, <code>${"${sifrai}"}</code>, <code>${"${periodiskumas}"}</code>. Būtent ši eilutė kopijuojama kiekvienam įrašui (PhpWord <em>clone row</em> logika). Jei šiuos laukus įrašysite kitoje eilutėje, dokumentas generuosis neteisingai.
                            </li>
                            <li>
                                <strong>Stulpelių plotis:</strong> Word lentelėje nustatytas stulpelių plotis ir išdėstymas tiesiogiai veikia galutinio PDF/Word vaizdą — tekstas laužomas pagal jūsų lentelės geometriją.
                            </li>
                            <li>
                                Dokumento pradžioje ir pabaigoje naudokite tuos pačius bendrus kintamuosius kaip ir kituose šablonuose, pvz. <code>${"${Kompanija}"}</code>, <code>${"${TIPAS}"}</code>, <code>${"${kodas}"}</code>, <code>${"${adresas}"}</code>, <code>${"${Miestas}"}</code>, linksnius <code>${"${Vadovo}"}</code>, <code>${"${Vardo}"}</code>, <code>${"${Pavardo}"}</code>, <code>${"${data}"}</code>, apačioje <code>${"${Role}"}</code>, <code>${"${Vadovas}"}</code> ir kt. Tikslų sąrašą žr. aukščiau esančiame kintamųjų kataloge.
                            </li>
                        </ul>
                    </div>
                    <div>
                        <h3>AAP Kortelės+Žiniaraščiai (Word, .docx / .doc)</h3>
                        <p>
                            Modulis: <Link href="/sablonai/kiti/Nemokamai-isduodamu-priemoniu-sarasas">AAP Kortelės+Žiniaraščiai</Link>. Generuojami <strong>du atskiri</strong> Word failai pagal jūsų pasirinkimą: <strong>sąrašas</strong> (lentelėje{" "}
                            <code>${"${pareigybe}"}</code>, <code>${"${priemones}"}</code>, <code>${"${terminas}"}</code> — duomenys iš įmonės darbuotojų tipų ir jiems priskirtų priemonių) ir <strong>kortelės / žiniaraščiai</strong> (antras šablonas serveryje). Įmonės laukai kaip šablone, pvz.{" "}
                            <code>${"${kompanija}"}</code>, <code>${"${Kompanija}"}</code>, <code>${"${TIPASKOMPAKTISKAS}"}</code>, <code>${"${kodas}"}</code>, <code>${"${tipas}"}</code>, <code>${"${role}"}</code>, <code>${"${data}"}</code>. Abu šablonai laikomi kataloge{" "}
                            <code>templates/otherTemplates/aap-korteles-ziniarasciai/</code> (<code>sarasas-aap</code> ir <code>korteles-ziniarasciai</code>).
                        </p>
                    </div>
                    <div>
                        <h3>AAP — profesinės rizikos lentelė (Excel, .xlsx)</h3>
                        <p>
                            Modulis: <Link href="/sablonai/kiti/AAP">AAP</Link>. Generuojamas <strong>Excel</strong> failas pagal šablono struktūrą serveryje. Tai nėra „paprastas“ vieno lapo Word šablonas: kiekvienam darbuotojui kopijuojamas fiksuoto <strong>aukščio blokas</strong> (eilučių skaičius šablone), užpildomi rizikų taškai pagal duomenų bazėje sukonfigūruotas eilutes ir stulpelius.
                        </p>
                        <ul>
                            <li>
                                <strong>Stulpelių plotis ir išdėstymas:</strong> ypač svarbu parašų zonoms ir rizikų lentelės tinkleliui — per siauri ar perkelti stulpeliai lūžta teksto išdėstymas. Rekomenduojama pradėti nuo standartinio šablono ir keisti atsargiai.
                            </li>
                            <li>
                                <strong>Savo šablonas:</strong> failas paprastai laikomas serverio aplanke (pvz. <code>otherTemplates/AAP/AAP.xlsx</code>) arba administratoriaus nustatytu keliu. Jei keičiate šabloną, jį reikia suderinti su esama generavimo logika — kitaip gali sutrikti blokų kopijavimas ir laukų užpildymas.
                            </li>
                            <li>
                                Rizikos stulpelių susiejimas su duomenų baze naudoja šablono eilučių numeraciją (techniškai rizikų subkategorijos dažnai pradedamos nuo trečios eilutės ir toliau) — todėl „trečia eilutė“ kaip pradžia duomenims yra būdinga ir čia, tik Excel kontekste.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            {/* Pavyzdys */}
            <div className={styles.exampleCard}>
                <div className={styles.cardHeader}>
                    <div className={styles.fileIcon}><Info size={24} /></div>
                    <div>
                        <h2 className={styles.title}>Pavyzdys: kenksmingų faktorių pažyma</h2>
                        <p className={styles.subtitle}>Šablonas su kintamaisiais ir sugeneruotas dokumentas — paspauskite nuotrauką, kad padidintumėte</p>
                    </div>
                </div>

                <div className={styles.divider} />

                <div className={styles.exampleGrid}>
                    <div className={styles.exampleItem}>
                        <h3>Šablonas (Word, ${"${...}"} kintamieji)</h3>
                        <div className={styles.imageWrapper} onClick={() => setSelectedImg(beforeImg)}>
                            <div className={styles.zoomIcon}><ZoomIn size={24} /></div>
                            <div className={styles.imageHeightFix}>
                                <Image src={beforeImg} alt="Kenksmingų faktorių pažymos šablonas su kintamaisiais" fill className={styles.imgContain} sizes="(max-width: 900px) 100vw, 50vw" />
                            </div>
                        </div>
                    </div>
                    <div className={styles.exampleItem}>
                        <h3>Užpildytas dokumentas</h3>
                        <div className={styles.imageWrapper} onClick={() => setSelectedImg(afterImg)}>
                            <div className={styles.zoomIcon}><ZoomIn size={24} /></div>
                            <div className={styles.imageHeightFix}>
                                <Image src={afterImg} alt="Sugeneruota kenksmingų faktorių pažyma su duomenimis" fill className={styles.imgContain} sizes="(max-width: 900px) 100vw, 50vw" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer className={styles.footer}>
                <p>&copy; 2026 Dokumentų Valdymo Sistema</p>
            </footer>
        </div>
    );
}
