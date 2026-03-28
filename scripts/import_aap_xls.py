import datetime
import re
import sys

import pymysql
import xlrd


def norm(value):
    if value is None:
        return ""
    s = str(value).strip()
    s = re.sub(r"\s+", " ", s)
    return s


def is_lowercase_word(text):
    letters = [ch for ch in text if ch.isalpha()]
    if not letters:
        return False
    return all(ch == ch.lower() for ch in letters)


def parse_risk_columns(sheet):
    cols = []
    group = ""
    category = ""
    for c in range(2, 34):
        gv = norm(sheet.cell_value(4, c))
        if gv:
            group = gv

        cv = norm(sheet.cell_value(5, c))
        if cv:
            category = cv

        sv = norm(sheet.cell_value(6, c))
        if not group or not category:
            continue

        if sv:
            cols.append(
                {
                    "col": c,
                    "group": group,
                    "category": category,
                    "subcategory": sv,
                    "line_number": c + 1,
                }
            )
        else:
            cols.append(
                {
                    "col": c,
                    "group": group,
                    "category": None,
                    "subcategory": category,
                    "line_number": c + 1,
                }
            )
    return cols


def parse_body_rows(sheet):
    rows = []
    for r in range(7, 22):
        part_name = norm(sheet.cell_value(r, 1))
        if not part_name:
            continue
        rows.append(
            {
                "row": r,
                "raw_category": norm(sheet.cell_value(r, 0)),
                "name": part_name,
                "line_number": len(rows) + 1,
            }
        )

    first_cat = ""
    for row in rows:
        if row["raw_category"]:
            first_cat = row["raw_category"]
            break

    categories = [row["raw_category"] for row in rows]
    for i in range(1, len(categories)):
        if (
            categories[i]
            and categories[i - 1]
            and is_lowercase_word(categories[i])
            and not is_lowercase_word(categories[i - 1])
        ):
            merged = categories[i - 1] + " " + categories[i]
            categories[i - 1] = merged
            categories[i] = merged

    last = first_cat
    for i, row in enumerate(rows):
        cat = categories[i]
        if cat:
            last = cat
        else:
            cat = last

        if row["name"] in {
            "Oda",
            "Liemuo/ pilvas",
            "Poodiniai audiniai",
            "Visas kūnas",
            "Dalis kūno",
        }:
            cat = "Įvairios"

        row["category"] = cat

    return rows


def parse_blocks(sheet, body_count, risk_cols):
    starts = []
    for r in range(sheet.nrows):
        title = norm(sheet.cell_value(r, 8)).lower()
        if "profesinės rizikos veiksnių įvertinimo" in title:
            starts.append(r)

    blocks = []
    for start in starts:
        meta = start + 2
        company = norm(sheet.cell_value(meta, 1)) or 'UAB "XXXXX"'

        worker = ""
        for c in range(2, 34):
            v = norm(sheet.cell_value(meta, c))
            if not v:
                continue
            if "darbo vietoje" in v.lower():
                continue
            if len(v) > len(worker):
                worker = v
        if not worker:
            worker = f"Darbuotojas {len(blocks) + 1}"

        pluses = []
        for i in range(body_count):
            rr = start + 7 + i
            part_name = norm(sheet.cell_value(rr, 1))
            for rc in risk_cols:
                mark = norm(sheet.cell_value(rr, rc["col"]))
                if "+" in mark:
                    pluses.append({"part": part_name, "col": rc["col"]})

        blocks.append({"company": company, "worker": worker, "pluses": pluses})

    return blocks


def get_id(cur, sql, params):
    cur.execute(sql, params)
    row = cur.fetchone()
    return row[0] if row else None


def ensure_group(cur, name, line_number):
    gid = get_id(cur, "SELECT id FROM risk_groups WHERE name=%s LIMIT 1", (name,))
    if gid:
        return gid
    cur.execute(
        "INSERT INTO risk_groups (name, line_number) VALUES (%s,%s)",
        (name, line_number),
    )
    return cur.lastrowid


def ensure_category(cur, group_id, name, line_number):
    cid = get_id(
        cur,
        "SELECT id FROM risk_categories WHERE group_id=%s AND name=%s LIMIT 1",
        (group_id, name),
    )
    if cid:
        return cid
    cur.execute(
        "INSERT INTO risk_categories (name, line_number, group_id) VALUES (%s,%s,%s)",
        (name, line_number, group_id),
    )
    return cur.lastrowid


def ensure_subcategory(cur, group_id, category_id, name, line_number):
    if category_id is None:
        sid = get_id(
            cur,
            "SELECT id FROM risk_subcategories WHERE name=%s AND category_id IS NULL AND group_id=%s LIMIT 1",
            (name, group_id),
        )
        if sid:
            return sid
        cur.execute(
            "INSERT INTO risk_subcategories (name, line_number, category_id, group_id) VALUES (%s,%s,NULL,%s)",
            (name, line_number, group_id),
        )
        return cur.lastrowid

    sid = get_id(
        cur,
        "SELECT id FROM risk_subcategories WHERE name=%s AND category_id=%s LIMIT 1",
        (name, category_id),
    )
    if sid:
        return sid
    cur.execute(
        "INSERT INTO risk_subcategories (name, line_number, category_id, group_id) VALUES (%s,%s,%s,NULL)",
        (name, line_number, category_id),
    )
    return cur.lastrowid


def ensure_body_category(cur, name, line_number):
    cid = get_id(cur, "SELECT id FROM body_part_category WHERE name=%s LIMIT 1", (name,))
    if cid:
        return cid
    cur.execute(
        "INSERT INTO body_part_category (name, line_number) VALUES (%s,%s)",
        (name, line_number),
    )
    return cur.lastrowid


def ensure_body_part(cur, category_id, name, line_number):
    pid = get_id(
        cur,
        "SELECT id FROM body_part WHERE category_id=%s AND name=%s LIMIT 1",
        (category_id, name),
    )
    if pid:
        return pid
    cur.execute(
        "INSERT INTO body_part (name, line_number, category_id) VALUES (%s,%s,%s)",
        (name, line_number, category_id),
    )
    return cur.lastrowid


def ensure_company(cur, name):
    cid = get_id(
        cur,
        "SELECT id FROM company_requisite WHERE company_name=%s AND deleted=0 LIMIT 1",
        (name,),
    )
    if cid:
        return cid
    now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    cur.execute(
        "INSERT INTO company_requisite (company_name, code, modified_at, created_at, deleted) VALUES (%s,NULL,%s,%s,0)",
        (name, now, now),
    )
    return cur.lastrowid


def ensure_worker(cur, name):
    wid = get_id(cur, "SELECT id FROM worker WHERE name=%s LIMIT 1", (name,))
    if wid:
        return wid
    cur.execute("INSERT INTO worker (name) VALUES (%s)", (name,))
    return cur.lastrowid


def ensure_company_worker(cur, company_id, worker_id):
    existing = get_id(
        cur,
        "SELECT id FROM company_worker WHERE company_id=%s AND worker_id=%s LIMIT 1",
        (company_id, worker_id),
    )
    if existing:
        return existing
    cur.execute(
        "INSERT INTO company_worker (company_id, worker_id) VALUES (%s,%s)",
        (company_id, worker_id),
    )
    return cur.lastrowid


def ensure_risk_list(cur, worker_id, body_part_id, risk_subcategory_id):
    existing = get_id(
        cur,
        "SELECT id FROM risk_list WHERE worker_id=%s AND body_part_id=%s AND risk_subcategory_id=%s LIMIT 1",
        (worker_id, body_part_id, risk_subcategory_id),
    )
    if existing:
        return False
    cur.execute(
        "INSERT INTO risk_list (body_part_id, risk_subcategory_id, worker_id) VALUES (%s,%s,%s)",
        (body_part_id, risk_subcategory_id, worker_id),
    )
    return True


def main():
    if len(sys.argv) < 2:
        print("Usage: python scripts/import_aap_xls.py <absolute-xls-path>")
        sys.exit(1)

    xls_path = sys.argv[1]
    wb = xlrd.open_workbook(xls_path)
    sheet = wb.sheet_by_index(0)

    risk_cols = parse_risk_columns(sheet)
    body_rows = parse_body_rows(sheet)
    blocks = parse_blocks(sheet, len(body_rows), risk_cols)

    conn = pymysql.connect(
        host="localhost",
        user="root",
        password="root",
        database="GPT-solutions",
        charset="utf8mb4",
        autocommit=False,
    )

    created_risk_lists = 0

    try:
        with conn.cursor() as cur:
            cur.execute("DELETE FROM risk_list")
            cur.execute("DELETE FROM company_worker")
            cur.execute("DELETE FROM worker")
            cur.execute("DELETE FROM body_part")
            cur.execute("DELETE FROM body_part_category")
            cur.execute("DELETE FROM risk_subcategories")
            cur.execute("DELETE FROM risk_categories")
            cur.execute("DELETE FROM risk_groups")

            sub_by_col = {}
            for rc in risk_cols:
                group_id = ensure_group(cur, rc["group"], rc["line_number"])
                category_id = None
                if rc["category"] is not None:
                    category_id = ensure_category(cur, group_id, rc["category"], rc["line_number"])
                sub_id = ensure_subcategory(cur, group_id, category_id, rc["subcategory"], rc["line_number"])
                sub_by_col[rc["col"]] = sub_id

            body_by_name = {}
            for br in body_rows:
                body_cat_id = ensure_body_category(cur, br["category"], br["line_number"])
                body_part_id = ensure_body_part(cur, body_cat_id, br["name"], br["line_number"])
                if br["name"] not in body_by_name:
                    body_by_name[br["name"]] = body_part_id

            for block in blocks:
                company_id = ensure_company(cur, block["company"])
                worker_id = ensure_worker(cur, block["worker"])
                ensure_company_worker(cur, company_id, worker_id)

                for plus in block["pluses"]:
                    part_id = body_by_name.get(plus["part"])
                    sub_id = sub_by_col.get(plus["col"])
                    if not part_id or not sub_id:
                        continue
                    if ensure_risk_list(cur, worker_id, part_id, sub_id):
                        created_risk_lists += 1

            conn.commit()

            cur.execute("SELECT COUNT(*) FROM risk_groups")
            groups = cur.fetchone()[0]
            cur.execute("SELECT COUNT(*) FROM risk_categories")
            categories = cur.fetchone()[0]
            cur.execute("SELECT COUNT(*) FROM risk_subcategories")
            subcategories = cur.fetchone()[0]
            cur.execute("SELECT COUNT(*) FROM body_part_category")
            body_categories = cur.fetchone()[0]
            cur.execute("SELECT COUNT(*) FROM body_part")
            body_parts = cur.fetchone()[0]
            cur.execute("SELECT COUNT(*) FROM worker")
            workers = cur.fetchone()[0]
            cur.execute("SELECT COUNT(*) FROM company_worker")
            company_workers = cur.fetchone()[0]
            cur.execute("SELECT COUNT(*) FROM risk_list")
            risk_list = cur.fetchone()[0]

        print("Import successful")
        print(
            f"groups={groups}, categories={categories}, subcategories={subcategories}, "
            f"body_part_categories={body_categories}, body_parts={body_parts}, "
            f"workers={workers}, company_workers={company_workers}, risk_list={risk_list}, "
            f"created_risk_list={created_risk_lists}"
        )
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    main()

