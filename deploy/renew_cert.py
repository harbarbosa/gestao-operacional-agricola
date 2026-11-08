#!/usr/bin/env python3
"""Renova apenas o certificado do domínio informado; instalar como arquivo de root."""
from pathlib import Path
import argparse
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument("user")
parser.add_argument("domain")
args = parser.parse_args()
certificate = Path("/home") / args.user / "conf/web" / args.domain / "ssl" / (args.domain + ".crt")
check = ["openssl", "x509", "-in", str(certificate), "-noout", "-checkend", str(30 * 86400)]
if certificate.exists() and subprocess.run(check, capture_output=True).returncode == 0:
    print("CERTIFICATE_VALID_MORE_THAN_30_DAYS")
    raise SystemExit(0)
result = subprocess.run(
    ["/usr/local/hestia/bin/v-add-letsencrypt-domain", args.user, args.domain, ""],
    capture_output=True, text=True,
)
if result.returncode != 0 or subprocess.run(check, capture_output=True).returncode != 0:
    raise SystemExit("Falha ao renovar o certificado do domínio.")
for command in [["nginx", "-t"], ["apache2ctl", "configtest"]]:
    subprocess.run(command, check=True, capture_output=True)
for service in ["nginx", "apache2"]:
    subprocess.run(["systemctl", "reload", service], check=True, capture_output=True)
print("CERTIFICATE_RENEWED")
