#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
J-ALG (上善如水 アライアンス・リードジェネレーター)
[Cron 03:00 実行] クローリング・メール抽出＆特電法拒否判定無人バッチスクリプト
"""

import datetime
import json
import os
import re
import sys
import time
from urllib.parse import urljoin, urlparse

import pymysql
import requests
from bs4 import BeautifulSoup

sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from crawler.scraper_engine import check_opt_out_compliance, extract_contact_info

REQUEST_TIMEOUT = 12
BATCH_LIMIT = 50
USER_AGENT = "Mozilla/5.0 (compatible; J-ALG-Crawler/1.0; +https://pr.eie.tokyo)"


def load_env(env_path):
    env = {}
    if not os.path.exists(env_path):
        return env
    with open(env_path, "r", encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, v = line.split("=", 1)
            env[k.strip()] = v.strip().strip('"').strip("'")
    return env


def get_db_connection(env):
    return pymysql.connect(
        host=env.get("DB_HOST", "localhost"),
        port=int(env.get("DB_PORT", 3306)),
        user=env.get("DB_USERNAME", "mdo3_toolapp0001"),
        password=env.get("DB_PASSWORD", ""),
        database=env.get("DB_DATABASE", "mdo3_pr"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def fetch_html(url):
    try:
        resp = requests.get(
            url,
            headers={"User-Agent": USER_AGENT},
            timeout=REQUEST_TIMEOUT,
            allow_redirects=True,
        )
        ctype = resp.headers.get("Content-Type", "").lower()
        if ctype and not any(t in ctype for t in ("text/html", "application/xhtml", "text/plain")):
            return resp.status_code, ""
        if resp.encoding is None or resp.encoding.lower() == "iso-8859-1":
            resp.encoding = resp.apparent_encoding or "utf-8"
        return resp.status_code, resp.text
    except Exception:
        return 0, ""


def find_sub_urls(base_url, html_text):
    """トップページから会社概要・お問い合わせページ候補（最大2URL）を抽出する"""
    if not html_text:
        return [], None

    base_host = urlparse(base_url).netloc.lower()
    soup = BeautifulSoup(html_text, "html.parser")

    about_url = None
    contact_url = None
    about_re = re.compile(r"(about|company|gaiyo|profile|outline)", re.I)
    contact_re = re.compile(r"(contact|inquiry|toiawase|mail|form)", re.I)

    for a in soup.find_all("a", href=True):
        href = a["href"].strip()
        if not href or href.startswith(("mailto:", "tel:", "javascript:", "#")):
            continue
        full_url = urljoin(base_url, href)
        parsed = urlparse(full_url)
        if parsed.scheme not in ("http", "https"):
            continue
        if parsed.netloc.lower() != base_host:
            continue
        if full_url.rstrip("/") == base_url.rstrip("/"):
            continue

        link_text = a.get_text(" ", strip=True)
        if not contact_url and (contact_re.search(href) or "問い合わせ" in link_text or "問合せ" in link_text):
            contact_url = full_url
        elif not about_url and (about_re.search(href) or "会社概要" in link_text or "企業情報" in link_text):
            about_url = full_url

        if about_url and contact_url:
            break

    sub_urls = []
    if about_url:
        sub_urls.append(about_url)
    if contact_url and contact_url != about_url:
        sub_urls.append(contact_url)
    return sub_urls, contact_url


def log_crawl(cursor, company_id, target_url, http_status, email, fax, keywords, snippet):
    sql = """
        INSERT INTO crawl_logs
            (company_id, target_url, http_status, extracted_email, extracted_fax, detected_optout_keywords, raw_text_snippet)
        VALUES
            (%s, %s, %s, %s, %s, %s, %s)
    """
    kw_str = json.dumps(keywords, ensure_ascii=False) if keywords else None
    cursor.execute(
        sql,
        (
            company_id,
            target_url[:512],
            http_status if http_status else None,
            email[:255] if email else None,
            fax[:50] if fax else None,
            kw_str,
            snippet[:1000] if snippet else None,
        ),
    )


def process_company(cursor, comp):
    cid = comp["id"]
    official_url = comp["official_url"].strip()
    found_email = None
    found_fax = None

    status_code, top_html = fetch_html(official_url)
    sub_urls, contact_url = find_sub_urls(official_url, top_html)
    pages = [(official_url, status_code, top_html)]

    for sub in sub_urls[:2]:
        time.sleep(0.5)
        sc, html = fetch_html(sub)
        pages.append((sub, sc, html))

    for url, sc, html in pages:
        email, fax = extract_contact_info(html) if html else (None, None)
        is_opt, kws, snip = check_opt_out_compliance(html) if html else (False, [], "")

        log_crawl(cursor, cid, url, sc, email, fax, kws, snip)

        if is_opt:
            cursor.execute(
                """
                UPDATE companies
                SET is_opt_out = 1, opt_out_reason = 'keyword_detected', status = 'rejected', updated_at = NOW()
                WHERE id = %s
                """,
                (cid,),
            )
            return "opt_out", kws

        if email and not found_email:
            found_email = email
        if fax and not found_fax:
            found_fax = fax

    is_dup = 0
    if found_email:
        cursor.execute(
            "SELECT id FROM companies WHERE email = %s AND id != %s LIMIT 1",
            (found_email, cid),
        )
        if cursor.fetchone():
            is_dup = 1
            cursor.execute(
                "UPDATE companies SET is_duplicate_email = 1, updated_at = NOW() WHERE email = %s AND id != %s",
                (found_email, cid),
            )

    cursor.execute(
        """
        UPDATE companies
        SET email = COALESCE(%s, email),
            fax = COALESCE(%s, fax),
            contact_url = COALESCE(%s, contact_url),
            is_duplicate_email = GREATEST(is_duplicate_email, %s),
            status = 'crawled',
            updated_at = NOW()
        WHERE id = %s
        """,
        (found_email, found_fax, contact_url, is_dup, cid),
    )
    return "crawled", (found_email, found_fax, is_dup)


def main():
    now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print("[{}] Starting Cron: Crawling & Anti-Spam Compliance Batch...".format(now))

    root_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    env = load_env(os.path.join(root_dir, ".env"))

    conn = get_db_connection(env)
    try:
        with conn.cursor() as cursor:
            cursor.execute(
                """
                SELECT id, name, official_url
                FROM companies
                WHERE status = 'pending'
                  AND official_url IS NOT NULL
                  AND official_url != ''
                  AND is_opt_out = 0
                ORDER BY id ASC
                LIMIT %s
                """,
                (BATCH_LIMIT,),
            )
            targets = cursor.fetchall()

            if not targets:
                print("[{}] No pending companies with official_url ready for crawling.".format(now))
                return

            crawled_cnt = 0
            optout_cnt = 0
            for comp in targets:
                result, detail = process_company(cursor, comp)
                if result == "opt_out":
                    optout_cnt += 1
                    print("  [OPT-OUT] #{} {} -> {}".format(comp["id"], comp["name"], detail))
                else:
                    crawled_cnt += 1
                    dup_tag = " [DUP-EMAIL]" if detail[2] else ""
                    print("  [CRAWLED]{} #{} {} -> email={}, fax={}".format(
                        dup_tag, comp["id"], comp["name"], detail[0], detail[1]
                    ))
                time.sleep(0.5)

        done_at = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        print("[{}] Crawling Batch completed. Crawled: {}, OptOut: {}".format(
            done_at, crawled_cnt, optout_cnt
        ))
    finally:
        conn.close()


if __name__ == "__main__":
    main()
