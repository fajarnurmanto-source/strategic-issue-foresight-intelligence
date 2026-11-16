#!/usr/bin/env python3
"""Open the latest local-only SIFI UAT email verification URL in Windows browser.

Does not print, transmit, or upload the signed verification URL.
Only reads the acceptance mail log; never modifies database or user verification.
"""
import base64
from html import unescape
from pathlib import Path
import re
import shutil
import subprocess
import sys
import time
from urllib.parse import parse_qs, urlsplit

app = Path("/home/fajar/sifi-r7-acceptance/rc-abb58f6-boot-01").resolve()
log = app / "storage/logs/laravel.log"
if not log.is_file() or log.is_symlink():
    sys.exit("BLOCKED_EMAIL_LOG_NOT_FOUND_RESEND_ONCE")

# Laravel log mailer emits a plain-text URL after 'Verify Email Address:'.
# Do not print log contents: it can contain personal data and bearer links.
content = log.read_text(encoding="utf-8", errors="replace")
links = re.findall(r"Verify Email Address:\s+(https?://[^\s<>]+)", content)
valid = []
for raw in links:
    url = unescape(raw).rstrip("]).,;")
    try:
        target = urlsplit(url)
        params = parse_qs(target.query)
        if (target.scheme == "http" and target.hostname == "localhost"
                and target.port == 18821
                and re.fullmatch(r"/email/verify/\d+/[0-9a-f]{40}", target.path)
                and int(params.get("expires", ["0"])[0]) > time.time()
                and len(params.get("signature", [""])[0]) == 64):
            valid.append(url)
    except (ValueError, TypeError, OverflowError):
        continue

if not valid:
    sys.exit("BLOCKED_NO_UNEXPIRED_LOCAL_VERIFICATION_LINK_RESEND_ONCE")

url = valid[-1]
if not shutil.which("powershell.exe"):
    sys.exit("BLOCKED_WINDOWS_POWERSHELL_NOT_AVAILABLE")

# Pass the URL encoded to PowerShell without echoing it to terminal or chat.
command = "Start-Process -FilePath '" + url.replace("'", "''") + "'"
encoded = base64.b64encode(command.encode("utf-16le")).decode("ascii")
result = subprocess.run(
    ["powershell.exe", "-NoProfile", "-EncodedCommand", encoded],
    capture_output=True, text=True, timeout=30
)
if result.returncode:
    sys.exit("BLOCKED_LOCAL_BROWSER_OPEN_FAILED")

print("SIFI_UAT_VERIFICATION_LINK_OPENED_IN_WINDOWS_BROWSER=YES")
print("TOKEN_PRINTED=NO")
print("DATABASE_MODIFIED_BY_HELPER=NO")
