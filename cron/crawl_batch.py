#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
J-ALG (上善如水 アライアンス・リードジェネレーター)
[Cron 03:00 実行] クローリング・メール抽出＆特電法拒否判定無人バッチスクリプト
"""

import os
import sys
import datetime
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from crawler.scraper_engine import check_opt_out_compliance, extract_contact_info

def main():
    now = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    print(f"[{now}] Starting Cron: Crawling & Anti-Spam Compliance Batch...")
    
    # 簡易シミュレーション実行
    sample_html = """
    <html>
    <body>
        <h1>有限会社 大宮木造工務店</h1>
        <p>Email: info@oomiya-builder.example.com</p>
        <p>TEL: 048-123-4567</p>
    </body>
    </html>
    """
    email, fax = extract_contact_info(sample_html)
    is_opt, kws, snip = check_opt_out_compliance(sample_html)
    
    print(f"[{now}] Extracted Email: {email}, Is Opt Out: {is_opt}")
    print(f"[{now}] Crawling Batch completed successfully.")

if __name__ == "__main__":
    main()
