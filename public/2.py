import os
import sqlite3
import requests
from win32crypt import CryptUnprotectData

path1 = r"C:\Users\94946\AppData\Local\Google\Chrome\User Data\Default\Cookies"
sql = "select * from cookies where host_key like '%.aliexpress.com'"
conn = sqlite3.connect(path1)
conn.row_factory = sqlite3.Row
cu = conn.cursor()
cursor = cu.execute(sql)
cookie = ''
for row in cursor:
    cookie = cookie + row['name'] + r"=" + CryptUnprotectData(row['encrypted_value'])[1].decode() + r";"
    #fo=open('d:\\test.txt','w')
    #fo.write(CryptUnprotectData(row['encrypted_value'])[1].decode())
    #fo.close()
conn.close()
print(cookie)