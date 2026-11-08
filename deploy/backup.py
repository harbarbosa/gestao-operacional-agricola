#!/usr/bin/env python3
"""Backup local exclusivo da base agrícola; executar como utilizador do projeto."""
from pathlib import Path
import datetime
import gzip
import json
import os
import shutil
import subprocess

shared = Path(__file__).resolve().parent
config = json.loads((shared / "deployment.json").read_text())
destination = shared / "backups"
destination.mkdir(mode=0o700, exist_ok=True)
if shutil.disk_usage(shared).free < 1024**3:
    raise SystemExit("Backup adiado: menos de 1 GiB disponível.")
client = shared / "database-client.cnf"
stamp = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
target = destination / ("agricola-db-" + stamp + ".sql.gz")
temporary = target.with_suffix(target.suffix + ".partial")
os.umask(0o077)
try:
    with temporary.open("wb") as output:
        process = subprocess.Popen(
            ["mariadb-dump", "--defaults-extra-file=" + str(client),
             "--single-transaction", "--quick", "--skip-lock-tables", config["database"]],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE,
        )
        with gzip.GzipFile(fileobj=output, mode="wb") as compressed:
            shutil.copyfileobj(process.stdout, compressed)
        error = process.stderr.read()
        if process.wait() != 0:
            raise RuntimeError("Falha na exportação da base de dados.")
    if temporary.stat().st_size == 0:
        raise RuntimeError("Backup vazio.")
    temporary.replace(target)
    cutoff = datetime.datetime.now().timestamp() - 7 * 86400
    for old in destination.glob("agricola-db-*.sql.gz"):
        if old != target and old.stat().st_mtime < cutoff:
            old.unlink()
    print("BACKUP_OK", target.name, target.stat().st_size)
except Exception:
    temporary.unlink(missing_ok=True)
    raise
