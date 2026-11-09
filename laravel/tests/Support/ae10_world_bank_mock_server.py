from http.server import ThreadingHTTPServer,BaseHTTPRequestHandler
from pathlib import Path
import json,time,sys,socket
R=Path(sys.argv[1]).resolve();D=R/'evidence/mock';D.mkdir(parents=True,exist_ok=True)
base=json.loads((Path(__file__).resolve().parents[2]/'resources/sifi/ae09-wb-response.json').read_text())
base[0]['lastupdated']='2026-10-25'
for i,row in enumerate(base[1]):row['value']=[1.25,2.5,3.75][i]
class Handler(BaseHTTPRequestHandler):
 protocol_version='HTTP/1.1'
 def log_message(self,*args):pass
 def do_GET(self):
  scenario=(D/'scenario.txt').read_text().strip() if (D/'scenario.txt').exists() else 'valid'
  with (D/'requests.jsonl').open('a') as f:f.write(json.dumps({'scenario':scenario,'path':self.path,'time':time.monotonic()})+'\n')
  if scenario=='disconnect':self.connection.shutdown(socket.SHUT_RDWR);self.connection.close();return
  if scenario=='slow_headers':time.sleep(6)
  data=json.loads(json.dumps(base));status=200;kind='application/json; charset=utf-8';extra={}
  if scenario=='revised':data[0]['lastupdated']='2026-10-26';data[1][0]['value']=1.75
  if scenario=='same_version_conflict':data[1][0]['value']=1.75
  if scenario=='stale':data[0]['lastupdated']='2020-01-01'
  if scenario=='missing':data[0].pop('lastupdated')
  if scenario=='schema':data[1][0]['new_field']='drift'
  if scenario=='too_many':data[0]['total']=4;data[1].append(data[1][0])
  if scenario=='redirect':status=302;extra['Location']='http://127.0.0.1:39601/forbidden-redirect-target'
  if scenario=='rate':status=429;extra['Retry-After']='60'
  if scenario=='http_error':status=500
  if scenario=='html':kind='text/html'
  if scenario=='encoding':extra['Content-Encoding']='gzip'
  body=json.dumps(data,separators=(',',':')).encode()
  if scenario=='malformed':body=b'{bad'
  if scenario=='oversize_length':body=b'x'*100000
  chunked=scenario in ['oversize_chunked','slow_body']
  self.send_response(status);self.send_header('Content-Type',kind)
  for key,value in extra.items():self.send_header(key,value)
  if chunked:self.send_header('Transfer-Encoding','chunked')
  else:self.send_header('Content-Length',str(len(body)))
  self.end_headers()
  try:
   if scenario=='oversize_chunked':
    for _ in range(2048):self.wfile.write(b'400\r\n'+b'x'*1024+b'\r\n');self.wfile.flush()
    self.wfile.write(b'0\r\n\r\n')
   elif scenario=='slow_body':
    self.wfile.write(b'1\r\n[\r\n');self.wfile.flush();time.sleep(6);self.wfile.write(b'1\r\n]\r\n0\r\n\r\n')
   else:self.wfile.write(body)
  except (BrokenPipeError,ConnectionResetError,OSError):pass
server=ThreadingHTTPServer(('127.0.0.1',39601),Handler);server.daemon_threads=True;server.serve_forever()
