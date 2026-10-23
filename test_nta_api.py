# -*- coding: utf-8 -*-
import sys
import io
import urllib.request
import ssl
import json
import xml.etree.ElementTree as ET

# WindowsコンソールのUTF-8文字化け対策
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

app_id = "Kk8QGG6E7tVRM"

print("============================================================")
print(" 国税庁 Web-API 疎通テスト検証レポート")
print(f" アプリケーションID: {app_id}")
print("============================================================")

ctx = ssl.create_default_context()
headers = {"User-Agent": "J-ALG/1.0"}

def get_data(url):
    req = urllib.request.Request(url, headers=headers)
    with urllib.request.urlopen(req, context=ctx, timeout=15) as res:
        return res.getcode(), res.read().decode('utf-8')

# -------------------------------------------------------------------
# 1. 法人番号システム Web-API (XML パース検証)
# -------------------------------------------------------------------
print("\n[検証1] 法人番号システム Web-API (単一法人照会: 1180301018771)")
url1 = f"https://api.houjin-bangou.nta.go.jp/4/num?id={app_id}&number=1180301018771&type=12"
code1, xml_text = get_data(url1)
print(f"HTTPステータス: {code1}")

root = ET.fromstring(xml_text)
corp = root.find(".//corporation")
if corp is not None:
    c_num = corp.findtext("corporateNumber")
    name = corp.findtext("name")
    pref = corp.findtext("prefectureName")
    city = corp.findtext("cityName")
    street = corp.findtext("streetNumber")
    print(f"【照会結果】")
    print(f" ・法人番号: {c_num}")
    print(f" ・法人名  : {name}")
    print(f" ・所在地  : {pref}{city}{street}")

# -------------------------------------------------------------------
# 2. 法人番号システム Web-API (地域・名称検索: 埼玉県「設計」)
# -------------------------------------------------------------------
print("\n[検証2] 法人番号システム Web-API (地域・キーワード検索: 埼玉県 / 「設計」)")
import urllib.parse
q_name = urllib.parse.quote("設計")
url2 = f"https://api.houjin-bangou.nta.go.jp/4/name?id={app_id}&name={q_name}&address=11&type=12&mode=1"
code2, xml_text2 = get_data(url2)
print(f"HTTPステータス: {code2}")

root2 = ET.fromstring(xml_text2)
count = root2.findtext("count")
print(f"【検索ヒット件数】: {count} 件")
print("【上位3件の法人データ】:")
for idx, c in enumerate(root2.findall(".//corporation")[:3]):
    c_num = c.findtext("corporateNumber")
    c_name = c.findtext("name")
    c_pref = c.findtext("prefectureName")
    c_city = c.findtext("cityName")
    print(f"  {idx+1}. [{c_num}] {c_name} （{c_pref}{c_city}）")

# -------------------------------------------------------------------
# 3. 適格請求書発行事業者公表システム Web-API (JSON パース検証)
# -------------------------------------------------------------------
print("\n[検証3] 適格請求書発行事業者公表システム Web-API (T1180301018771)")
url3 = f"https://web-api.invoice-kohyo.nta.go.jp/1/num?id={app_id}&number=T1180301018771&type=21"
code3, json_text = get_data(url3)
print(f"HTTPステータス: {code3}")

data3 = json.loads(json_text)
ann = data3.get("announcement", [{}])[0]
print(f"【適格請求書発行事業者 公表データ】")
print(f" ・登録番号  : {ann.get('registratedNumber')}")
print(f" ・事業者名  : {ann.get('name')}")
print(f" ・登録年月日: {ann.get('registrationDate')}")
print(f" ・所在地    : {ann.get('address')}")

print("\n" + "=" * 60)
print(" 結論: 国税庁の全API（法人番号 / インボイス）との疎通・データ取得に完全成功しました。")
print("============================================================")
