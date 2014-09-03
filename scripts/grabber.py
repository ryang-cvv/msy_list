from datetime import datetime, date, time
from BeautifulSoup import BeautifulSoup
import MySQLdb, urllib, re, string, sys

def replace_all(text, dic):
    for i, j in dic.iteritems():
        text = text.replace(i, j)
    return text

f = urllib.urlopen("http://www.msy.com.au/Parts/PARTS_W.HTM")
msg = f.info()
mod_date = msg.getheaders('Last-Modified')[0]

dt = datetime.strptime(mod_date,"%a, %d %b %Y %H:%M:%S %Z")
fix_date = dt.isoformat(' ')

conn = MySQLdb.connect(host = "HOSTNAME", user = "USER", passwd = "PASSWORD", db = "DBNAME")
cursor = conn.cursor()
cursor.execute("SELECT date FROM prices ORDER BY date DESC LIMIT 1")
row = cursor.fetchone()
last_mod = row[0]

force = False
if len(sys.argv) > 1 and sys.argv[1] == 'force':
    force = True
    print "Forcing update..."
    

if last_mod < dt or force:
    print "Last Modified: ", last_mod
    print "Needs updating.."
    soup = BeautifulSoup.BeautifulSoup(f.read())

    data =  soup.find('table', width=True)

    row = 0
    col = 0
    outText = []
    lineText = ''
    reps = {'\n':'', '&nbsp;':'', u'\xa1':'"', u'\xa8':'', u'\xb0':'', u'\xb1':'', u'\xaf':'', 
            u'\xa3':'', u'\xac':'-', u'\xa7':'', u'\xa6':'', u'\xa9':'', '\u2014':'-'}
    regEx = r'(\([a-zA-Z, ]*[Cc][rl]e[a-zA-Z, ]*\) *)'
    for x in data.findAll('tr'):
        outText.append([])
        for y in x.findAll('td'):
            if col > 1: col = 0
            lineText = ''
            for z in y.findAll('span',limit=1):
                rawText = z.findAll(text=True)
                cutText = [s.replace('\r\n ','') for s in rawText]
                clnText = [replace_all(s, reps) for s in cutText]
                if lineText != clnText: lineText += ''.join(clnText)
            try:
                outText[row].insert(col,str(lineText))
            except UnicodeError:
                print lineText
            else:
                pass
            if len(outText) < row: continue
            if len(outText[row]) < 1: continue
            if re.search(regEx,outText[row][0]):
                spltText = re.split(regEx, outText[row][0])
                for split in spltText:
                    if re.search(regEx,split):
                        outText[row].insert(2,split.strip())
                        continue
                    if len(split) > 5:
                        outText[row][0] = split.strip()
                        continue
            col += 1
        cursor.execute("""SELECT prod_id FROM models WHERE prod_desc LIKE %s LIMIT 1""", (outText[row][0],))
        sqlrow = cursor.fetchone()
        if sqlrow:
            # Product exists, use id
            prodId = sqlrow[0]
        else:
            # No product, insert
            cursor.execute("""INSERT INTO models (prod_desc) VALUES (%s) ON DUPLICATE KEY UPDATE count=count+1""",
              (outText[row][0],))
            prodId = cursor.lastrowid
        if len(outText[row]) < 3:
            cursor.execute("""INSERT IGNORE INTO prices (prod_id, price, date) VALUES (%s,%s,%s)""",
              (prodId, outText[row][1], fix_date))
        else:
            cursor.execute("""INSERT IGNORE INTO prices (prod_id, price, date, notes) VALUES (%s,%s,%s,%s)""",
              (prodId, outText[row][1], fix_date, outText[row][2]))
#        cursor.execute("INSERT IGNORE INTO pricelist (product, price, date) VALUES (%s,%s,%s)",
#            (outText[row][0], outText[row][1], fix_date))
        row += 1

cursor.close()
conn.close()
f.close()
