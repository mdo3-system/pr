#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
J-ALG (上善如水 アライアンス・リードジェネレーター)
特電法準拠・WEB情報抽出＆拒否判定クローラーエンジン (v1.0.1)
"""

import re
import sys

# BeautifulSoup4 の安全なインポート
try:
    from bs4 import BeautifulSoup
    HAS_BS4 = True
except ImportError:
    HAS_BS4 = False

# メールアドレス抽出正規表現
EMAIL_REGEX = r'[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+'

# FAX番号抽出正規表現
FAX_REGEX = r'(?:FAX|Fax|fax|ファックス|ﾌｧｯｸｽ)[\s:：]*([0-9]{2,4}[-\s][0-9]{2,4}[-\s][0-9]{3,4})'

# 【聖域ロジック】特電法・拒否判定キーワード辞書
OPTOUT_KEYWORDS = [
    "営業お断り", "セールスお断り", "勧誘お断り", "営業メールはお断り",
    "営業のご案内はお控え", "セールス等はお断り", "営業等はご遠慮",
    "特定電子メール", "営業のご連絡はご遠慮", "セールスご遠慮",
    "売込みお断り", "売り込みお断り", "一切お断りしております",
    "営業目的のお問い合わせは", "営業メール等はご遠慮"
]

# 無効メールアドレス除外ドメイン・キーワード
IGNORED_EMAIL_DOMAINS = ['wix.com', 'sentry.io', 'example.com', 'xxx@', 'domain.com', '.jpg', '.png', '.css']

def extract_contact_info(html_text: str):
    """
    HTML本文からメールアドレスおよびFAX番号を抽出する
    """
    raw_emails = set(re.findall(EMAIL_REGEX, html_text))
    clean_emails = [
        e for e in raw_emails 
        if not any(x in e.lower() for x in IGNORED_EMAIL_DOMAINS)
    ]
    
    faxes = re.findall(FAX_REGEX, html_text)
    clean_fax = faxes[0] if faxes else None
    
    return (clean_emails[0] if clean_emails else None), clean_fax

def check_opt_out_compliance(html_text: str) -> tuple[bool, list[str], str]:
    """
    特定電子メール法に基づき、営業メール受信拒否の記述を検知する
    Returns: (is_opt_out: bool, detected_keywords: list[str], snippet: str)
    """
    if HAS_BS4:
        soup = BeautifulSoup(html_text, 'html.parser')
        for script in soup(["script", "style"]):
            script.decompose()
        text = soup.get_text(separator=' ')
    else:
        # BS4がない場合のフォールバック（HTMLタグ除去）
        no_script = re.sub(r'<script.*?>.*?</script>', ' ', html_text, flags=re.DOTALL | re.IGNORECASE)
        no_style = re.sub(r'<style.*?>.*?</style>', ' ', no_script, flags=re.DOTALL | re.IGNORECASE)
        text = re.sub(r'<[^>]+>', ' ', no_style)

    detected = []
    snippet = ""
    for kw in OPTOUT_KEYWORDS:
        if kw in text:
            detected.append(kw)
            idx = text.find(kw)
            start = max(0, idx - 30)
            end = min(len(text), idx + len(kw) + 30)
            snippet = text[start:end].strip()
            break
            
    return (len(detected) > 0), detected, snippet

if __name__ == "__main__":
    sample_html = """
    <html>
    <body>
        <h1>株式会社 川越建築設計事務所</h1>
        <p>お問い合わせ: info@kawagoe-arch.example.com</p>
        <p>FAX: 049-123-4567</p>
        <div>※当店への営業お断りしております。セールス目的の特定電子メールはご遠慮ください。</div>
    </body>
    </html>
    """
    email, fax = extract_contact_info(sample_html)
    is_opt_out, kws, snip = check_opt_out_compliance(sample_html)
    
    print(f"Extracted Email: {email}")
    print(f"Extracted FAX: {fax}")
    print(f"Is Opt Out: {is_opt_out}")
    print(f"Detected Keywords: {kws}")
    print(f"Snippet: {snip}")
